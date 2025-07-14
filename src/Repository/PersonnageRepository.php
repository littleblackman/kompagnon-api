<?php

namespace App\Repository;

use App\Entity\Personnage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PersonnageRepository extends ServiceEntityRepository
{
    private ProjectRepository $projectRepository;

    public function __construct(ManagerRegistry $registry, ProjectRepository $projectRepository)
    {
        parent::__construct($registry, Personnage::class);
        $this->projectRepository = $projectRepository;
    }

    public function findByProject(int $projectId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.project = :project')
            ->setParameter('project', $projectId)
            ->orderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
