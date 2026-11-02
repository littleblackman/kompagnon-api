<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ProjectTodo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProjectTodoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectTodo::class);
    }

    /** @return ProjectTodo[] Les tâches visibles, de la plus ancienne à la plus récente */
    public function findVisible(Project $project): array
    {
        return $this->findBy(['project' => $project, 'dismissed' => false], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    /** @return string[] Empreintes déjà connues, écartées comprises */
    public function findDedupeKeys(Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.dedupeKey')
            ->where('t.project = :project')
            ->andWhere('t.dedupeKey IS NOT NULL')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleColumnResult();
    }
}
