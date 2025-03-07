<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

class ProjectRepository extends ServiceEntityRepository
{

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Trouve un projet par son slug.
     */
    public function findBySlug(string $slug): ?Project
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getProjectWithDetails(string $slug): ?array
    {
        $query = $this->getEntityManager()->createQuery("
            SELECT p, parts, sequences, scenes, sequenceCriterias, sequencePersonnages, personnage, criteria
            FROM App\Entity\Project p
            LEFT JOIN p.parts parts
            LEFT JOIN parts.sequences sequences
            LEFT JOIN sequences.scenes scenes
            LEFT JOIN sequences.sequenceCriterias sequenceCriterias
            LEFT JOIN sequenceCriterias.criteria criteria
            LEFT JOIN sequences.sequencePersonnages sequencePersonnages
            LEFT JOIN sequencePersonnages.personnage personnage
            WHERE p.slug = :slug
            ORDER BY parts.order ASC, sequences.order ASC, scenes.order ASC
        ")->setParameter('slug', $slug);

        return $query->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);
    }
}
