<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Part;
use App\Entity\Sequence;
use App\Entity\Personnage;
use App\Entity\PersonnageDramaticFunction;
use App\Entity\PersonnageNarrativeArc;
use App\Repository\PartRepository;
use App\Repository\TypeRepository;
use App\Repository\ProjectRepository;
use App\Repository\SequenceRepository;
use App\Repository\StatusRepository;
use App\Repository\DramaticFunctionRepository;
use App\Repository\NarrativeArcRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;


class ProjectService
{
    private ProjectRepository $projectRepository;
    private PartRepository $partRepository;
    private SequenceRepository $sequenceRepository;
    private TypeRepository $typeRepository;
    private StatusRepository $statusRepository;
    private DramaticFunctionRepository $dramaticFunctionRepository;
    private NarrativeArcRepository $narrativeArcRepository;
    private EntityManagerInterface $entityManager;
    private SluggerInterface $slugger;
    private Security $security;

    public function __construct(
        ProjectRepository $projectRepository,
        PartRepository $partRepository,
        SequenceRepository $sequenceRepository,
        TypeRepository $typeRepository,
        StatusRepository $statusRepository,
        DramaticFunctionRepository $dramaticFunctionRepository,
        NarrativeArcRepository $narrativeArcRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        Security $security

    ) {
        $this->projectRepository = $projectRepository;
        $this->partRepository = $partRepository;
        $this->sequenceRepository = $sequenceRepository;
        $this->typeRepository = $typeRepository;
        $this->statusRepository = $statusRepository;
        $this->dramaticFunctionRepository = $dramaticFunctionRepository;
        $this->narrativeArcRepository = $narrativeArcRepository;
        $this->entityManager = $entityManager;
        $this->slugger = $slugger;
        $this->security = $security;
    }

    public function getProjectWithDetails(string $slug): ?array
    {
        // retrieve the project by slug
        $project = $this->projectRepository->getProjectWithDetails( $slug);
        if (!$project) {
            return null;
        }

        return $project;
    }

    public function getProjectBySlug(string $slug): ?Project
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        return $this->projectRepository->findOneBy([
            'slug' => $slug,
            'user' => $user
        ]);
    }

    public function createOrUpdate(?array $data): ?Project
    {
        $em = $this->entityManager;
        $user = $this->security->getUser();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if(isset($data['id']))  {
            $project = $this->projectRepository->findOneBy([
                'id' => $data['id'],
                'user' => $user
            ]);

            if (!$project) {
                throw new \Exception('Project not found or access denied');
            }
        } else {
            $project = new Project();
            $slug = $this->generateUniqueSlug($data['name'] ?? 'new-project');
            $project->setSlug($slug);
            $project->setUser($user);
        }

        $project->setName($data['name']);
        $project->setDescription($data['description']);

        // Store reference narrative components if provided (creation or update)
        if (isset($data['genre_id']) || isset($data['subgenre_id']) || isset($data['narrative_structure_id'])) {
            $referenceComponents = $project->getReferenceNarrativeComponents() ?? [];

            if (isset($data['genre_id'])) {
                $referenceComponents['genre_id'] = $data['genre_id'];
            }
            if (isset($data['subgenre_id'])) {
                $referenceComponents['subgenre_id'] = $data['subgenre_id'];
            }
            if (isset($data['narrative_structure_id'])) {
                $referenceComponents['narrative_structure_id'] = $data['narrative_structure_id'];
            }

            $project->setReferenceNarrativeComponents($referenceComponents);
        }

        // Handle type if provided
        if (isset($data['type_id'])) {
            $type = $this->typeRepository->find($data['type_id']);
            if ($type) {
                $project->setType($type);
            }
        } elseif (isset($data['type']) && is_array($data['type']) && isset($data['type']['id'])) {
            // Support legacy format
            $type = $this->typeRepository->find($data['type']['id']);
            if ($type) {
                $project->setType($type);
            }
        }

        $em->persist($project);
        $em->flush();

        // Créer les personnages avec leurs dramatic functions si fournis
        if (isset($data['characters']) && is_array($data['characters']) && !isset($data['id'])) {
            $characterIndex = 1;
            foreach ($data['characters'] as $characterData) {
                if (empty($characterData['name']) || empty($characterData['dramaticFunctionId'])) {
                    continue;
                }

                // Créer le personnage
                $personnage = new Personnage();
                $personnage->setFirstName($characterData['name']);
                $personnage->setLastName(''); // Par défaut vide
                $personnage->setProject($project);
                // Générer un slug unique avec index pour éviter les doublons
                $baseSlug = strtolower($this->slugger->slug($characterData['name']));
                $personnage->setSlug($baseSlug . '-' . $project->getId() . '-' . $characterIndex);
                $characterIndex++;

                // Récupérer la dramatic function
                $dramaticFunction = $this->dramaticFunctionRepository->find($characterData['dramaticFunctionId']);
                if (!$dramaticFunction) {
                    continue;
                }

                // Créer la liaison personnage-dramatic_function
                $pdf = new PersonnageDramaticFunction();
                $pdf->setPersonnage($personnage);
                $pdf->setDramaticFunction($dramaticFunction);
                $pdf->setWeight(100); // Poids par défaut

                $personnage->addPersonnageDramaticFunction($pdf);

                $em->persist($personnage);
                $em->persist($pdf);

                // Créer la liaison personnage-narrative_arc si un arc est sélectionné
                if (!empty($characterData['narrativeArcId'])) {
                    $narrativeArc = $this->narrativeArcRepository->find($characterData['narrativeArcId']);
                    if ($narrativeArc) {
                        $pna = new PersonnageNarrativeArc();
                        $pna->setPersonnage($personnage);
                        $pna->setNarrativeArc($narrativeArc);
                        $pna->setWeight(50); // Poids par défaut
                        // fromSequence et toSequence sont null pour l'instant, l'utilisateur pourra les définir plus tard

                        $em->persist($pna);
                    }
                }
            }

            $em->flush();
        }

        // Créer les parties et séquences depuis la structure narrative personnalisée
        if (isset($data['narrativeSections']) && is_array($data['narrativeSections']) && !isset($data['id'])) {
            // Récupérer le status par défaut (ID 6)
            $defaultStatus = $this->statusRepository->find(6);

            $partPosition = 1;
            foreach ($data['narrativeSections'] as $section) {
                if (empty($section['sectionName'])) {
                    continue;
                }

                // Créer la Part depuis le nom de section (ex: "Montée", "Bascule", etc.)
                $part = new Part();
                $part->setName($section['sectionName']);
                $part->setDescription(''); // Description vide par défaut
                $part->setProject($project);
                $part->setPosition($partPosition);
                if ($defaultStatus) {
                    $part->setStatus($defaultStatus);
                }

                $em->persist($part);
                $em->flush(); // Flush pour obtenir l'ID de la Part

                // Créer les Sequences depuis les events de cette section
                if (isset($section['events']) && is_array($section['events'])) {
                    $sequencePosition = 1;
                    foreach ($section['events'] as $event) {
                        if (empty($event['name'])) {
                            continue;
                        }

                        $sequence = new Sequence();
                        $sequence->setName($event['name']);
                        $sequence->setDescription($event['description'] ?? '');
                        $sequence->setPart($part);
                        $sequence->setPosition($sequencePosition);
                        if ($defaultStatus) {
                            $sequence->setStatus($defaultStatus);
                        }

                        $em->persist($sequence);
                        $sequencePosition++;
                    }
                }

                $partPosition++;
            }

            $em->flush();
        }

        // LEGACY: Créer les parties depuis les events si fournis (compatibilité)
        if (isset($data['events']) && is_array($data['events']) && !isset($data['id']) && !isset($data['narrativeSections'])) {
            // Récupérer le status par défaut (ID 6)
            $defaultStatus = $this->statusRepository->find(6);

            // Créer une partie pour chaque event
            foreach ($data['events'] as $eventData) {
                if (empty($eventData['name'])) {
                    continue;
                }

                $part = new Part();
                $part->setName($eventData['name']);
                $part->setDescription($eventData['description'] ?? '');
                $part->setProject($project);
                $part->setPosition($eventData['position'] ?? 1);
                if ($defaultStatus) {
                    $part->setStatus($defaultStatus);
                }

                $em->persist($part);
            }

            $em->flush();
        }

        return $project;
    }

    public function delete(int $id): void
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $project = $this->projectRepository->findOneBy([
            'id' => $id,
            'user' => $user
        ]);

        if (!$project) {
            throw new \Exception('Project not found or access denied');
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = strtolower($this->slugger->slug($name));
        $slug = $baseSlug;
        $i = 2;

        while ($this->projectRepository->slugExists($slug))  {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        return $slug;
    }

}