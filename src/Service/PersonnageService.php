<?php

namespace App\Service;

use App\Entity\Personnage;
use App\Entity\SequencePersonnage;
use App\Repository\ProjectRepository;
use App\Repository\PersonnageRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SequencePersonnageRepository;

class PersonnageService
{
    private ProjectRepository $projectRepository;
    private PersonnageRepository $personnageRepository;
    private EntityManagerInterface $em;
    private SequencePersonnageRepository $sequencePersonnageRepository;

    public function __construct(
        ProjectRepository $projectRepository,
        PersonnageRepository $personnageRepository,
        EntityManagerInterface $em,
        SequencePersonnageRepository $sequencePersonnageRepository
    )
    {
        $this->projectRepository = $projectRepository;
        $this->personnageRepository = $personnageRepository;
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

        $this->em->persist($personnage);
        $this->em->flush();

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
}