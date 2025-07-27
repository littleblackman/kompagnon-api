<?php

namespace App\Repository;

use App\Entity\Personnage;
use App\Utils\SlugUtils;
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

    public function findBySlug(string $slug): ?Personnage
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findUniqueSlug(string $baseSlug, ?int $excludeId = null): string
    {
        $slug = $baseSlug;
        $counter = 1;
        
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug);
            
        if ($excludeId) {
            $qb->andWhere('p.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }
        
        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
