<?php

namespace App\Repository;

use App\Entity\Part;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PartRepository extends ServiceEntityRepository
{

    private ManagerRegistry $managerRegistry;
    private ProjectRepository $projectRepository;
    private StatusRepository $statusRepository;

    public function __construct(ManagerRegistry $registry, ProjectRepository $projectRepository, StatusRepository $statusRepository)
    {
        parent::__construct($registry, Part::class);
        $this->projectRepository = $projectRepository;
        $this->statusRepository = $statusRepository;
    }

    public function createOrUpdate(array $data): Part
    {
        $em = $this->getEntityManager();

        if(!isset($data['id'])) $data['id'] = 0;

        if(!$part = $this->find($data['id'])) {
            unset($data['id']);
            $part = new Part();
            $project = $this->projectRepository->find($data['project_id']);
            $status = $this->statusRepository->find(6);
        }

        $part->setName($data['name'])
            ->setDescription($data['description'] ?? '')
            ->setPosition($data['order'] ?? 0)
            ->setUpdatedAt(new \DateTimeImmutable());
        if (!$part->getId()) {
            $part->setProject($project)
                ->setStatus($status)
                ->setCreatedAt(new \DateTimeImmutable());
        }

        $em->persist($part);

        $em->flush();

        return $part;
    }
}
