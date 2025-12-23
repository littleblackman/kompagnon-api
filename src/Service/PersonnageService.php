<?php

namespace App\Service;

use App\Entity\Personnage;
use App\Entity\PersonnageDramaticFunction;
use App\Entity\SequencePersonnage;
use App\Repository\ProjectRepository;
use App\Repository\PersonnageRepository;
use App\Repository\DramaticFunctionRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SequencePersonnageRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PersonnageService
{
    private ProjectRepository $projectRepository;
    private PersonnageRepository $personnageRepository;
    private DramaticFunctionRepository $dramaticFunctionRepository;
    private EntityManagerInterface $em;
    private SequencePersonnageRepository $sequencePersonnageRepository;

    public function __construct(
        ProjectRepository $projectRepository,
        PersonnageRepository $personnageRepository,
        DramaticFunctionRepository $dramaticFunctionRepository,
        EntityManagerInterface $em,
        SequencePersonnageRepository $sequencePersonnageRepository
    )
    {
        $this->projectRepository = $projectRepository;
        $this->personnageRepository = $personnageRepository;
        $this->dramaticFunctionRepository = $dramaticFunctionRepository;
        $this->em = $em;
        $this->sequencePersonnageRepository = $sequencePersonnageRepository;
    }

    public function createOrUpdate(array $data): ?Personnage
    {
        // Validation des données requises
        if (empty($data['firstName']) && empty($data['lastName'])) {
            return null;
        }

        if (empty($data['project_id'])) {
            return null;
        }

        $personnage = $this->hydrate($data);

        // Generate unique slug
        $baseSlug = $personnage->generateSlug()->getSlug();
        $uniqueSlug = $this->personnageRepository->findUniqueSlug($baseSlug, $personnage->getId());
        $personnage->setSlug($uniqueSlug);

        $this->em->persist($personnage);
        $this->em->flush();

        // Gérer les dramatic functions
        if (isset($data['dramaticFunctionIds']) && is_array($data['dramaticFunctionIds'])) {
            // Supprimer les anciennes relations
            foreach ($personnage->getPersonnageDramaticFunctions() as $pdf) {
                $this->em->remove($pdf);
            }
            $this->em->flush();

            // Créer les nouvelles relations
            foreach ($data['dramaticFunctionIds'] as $functionId) {
                $dramaticFunction = $this->dramaticFunctionRepository->find($functionId);
                if ($dramaticFunction) {
                    $pdf = new PersonnageDramaticFunction();
                    $pdf->setPersonnage($personnage);
                    $pdf->setDramaticFunction($dramaticFunction);
                    $pdf->setWeight(100); // Poids par défaut

                    $personnage->addPersonnageDramaticFunction($pdf);
                    $this->em->persist($pdf);
                }
            }
            $this->em->flush();
        }


        // is sequenceId is set, create or update the link with SequencePersonnage
        if (isset($data['sequenceId']) && $data['sequenceId']) {
            
            $sequence = $this->em->getReference('App\Entity\Sequence', $data['sequenceId']);

            if(!$link = $this->sequencePersonnageRepository->findOneBy(['sequence' => $sequence, 'personnage' => $personnage]) ) {
                $link = new SequencePersonnage();
                $link->setSequence($sequence)
                    ->setPersonnage($personnage);
                $this->em->persist($link);
                $this->em->flush();
            } 
        
        }

        return $personnage;
        
    }

    private function hydrate( array $data): Personnage
    {

        if(isset($data['id'])) {
            $personnage = $this->personnageRepository->find($data['id']);
            if (!$personnage) {
                throw new \Exception('Personnage not found');
            }
        } else {
            $personnage = new Personnage();
            $project = $this->em->getReference('App\Entity\Project', $data['project_id']);
            $personnage->setProject($project)
                ->setCreatedAt(new \DateTimeImmutable());
        }


        $personnage->setFirstName($data['firstName'] ?? $personnage->getFirstName() ?? '')
            ->setLastName($data['lastName'] ?? $personnage->getLastName() ?? '')
            ->setBackground($data['background'] ?? $personnage->getBackground() ?? '')
            ->setAge($data['age'] ?? $personnage->getAge())
            ->setOrigin($data['origin'] ?? $personnage->getOrigin() ?? '')
            ->setAvatar($data['avatar'] ?? $personnage->getAvatar() ?? '')
            ->setLevel($data['level'] ?? $personnage->getLevel())
            ->setAnalysis($data['analysis'] ?? $personnage->getAnalysis() ?? '')
            ->setStrength($data['strength'] ?? $personnage->getStrength() ?? '')
            ->setWeakness($data['weakness'] ?? $personnage->getWeakness() ?? '')
            ->setUpdatedAt(new \DateTimeImmutable());

        return $personnage;
    }

    public function getPersonnagesByProject(int $projectId): array
    {
        return $this->personnageRepository->findByProject($projectId);
    }

    public function deletePersonnage(int $id): bool
    {
        $personnage = $this->personnageRepository->find($id);
        if (!$personnage) {
            return false;
        }

        // Supprimer toutes les relations SequencePersonnage
        $links = $this->sequencePersonnageRepository->findBy(['personnage' => $personnage]);
        foreach ($links as $link) {
            $this->em->remove($link);
        }

        $this->em->remove($personnage);
        $this->em->flush();

        return true;
    }

    /**
     * Upload images for a personnage
     * @param int $personnageId
     * @param UploadedFile[] $files
     * @return array Array of uploaded image URLs
     */
    public function uploadImages(int $personnageId, array $files): array
    {
        $personnage = $this->personnageRepository->find($personnageId);
        if (!$personnage) {
            throw new \Exception('Personnage non trouvé');
        }

        // Create upload directory if it doesn't exist
        $uploadDir = 'uploads/personnages';
        $publicDir = __DIR__ . '/../../public/' . $uploadDir;
        
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }

        $uploadedUrls = [];
        $currentImages = $personnage->getImagesArray();

        foreach ($files as $file) {
            // Generate unique filename
            $filename = 'perso-' . $personnageId . '-' . uniqid() . '.' . $file->guessExtension();
            $filePath = $publicDir . '/' . $filename;
            
            // Move file
            $file->move($publicDir, $filename);
            
            // Store relative URL
            $relativeUrl = $uploadDir . '/' . $filename;
            $uploadedUrls[] = $relativeUrl;
            $currentImages[] = $relativeUrl;
        }

        // Update personnage with new images
        $personnage->setImagesArray($currentImages);
        $this->em->flush();

        return $uploadedUrls;
    }

    /**
     * Reorder images for a personnage (for avatar selection)
     */
    public function reorderImages(int $personnageId, array $orderedUrls): bool
    {
        $personnage = $this->personnageRepository->find($personnageId);
        if (!$personnage) {
            return false;
        }

        $personnage->setImagesArray($orderedUrls);
        $this->em->flush();

        return true;
    }

    /**
     * Get detailed information about a personnage including stats
     */
    public function getPersonnageDetails(int $personnageId): ?array
    {
        $personnage = $this->personnageRepository->find($personnageId);
        if (!$personnage) {
            return null;
        }

        // Get sequence appearances count
        $sequenceCount = $this->sequencePersonnageRepository->createQueryBuilder('sp')
            ->select('COUNT(sp.id)')
            ->where('sp.personnage = :personnage')
            ->setParameter('personnage', $personnage)
            ->getQuery()
            ->getSingleScalarResult();

        // Get project info
        $project = $personnage->getProject();

        // Build response
        return [
            'id' => $personnage->getId(),
            'firstName' => $personnage->getFirstName(),
            'lastName' => $personnage->getLastName(),
            'slug' => $personnage->getSlug(),
            'age' => $personnage->getAge(),
            'level' => $personnage->getLevel(),
            'background' => $personnage->getBackground(),
            'strength' => $personnage->getStrength(),
            'weakness' => $personnage->getWeakness(),
            'analysis' => $personnage->getAnalysis(),
            'images' => $personnage->getImagesArray(),
            'avatar' => $personnage->getImagesArray()[0] ?? null,
            'project' => [
                'id' => $project?->getId(),
                'name' => $project?->getName(),
                'slug' => $project?->getSlug(),
            ],
            'stats' => [
                'sequenceCount' => (int) $sequenceCount,
                'imageCount' => count($personnage->getImagesArray()),
            ]
        ];
    }

    /**
     * Get detailed information about a personnage by slug including stats
     */
    public function getPersonnageDetailsBySlug(string $slug): ?array
    {
        $personnage = $this->personnageRepository->findBySlug($slug);
        if (!$personnage) {
            return null;
        }

        return $this->getPersonnageDetails($personnage->getId());
    }
}