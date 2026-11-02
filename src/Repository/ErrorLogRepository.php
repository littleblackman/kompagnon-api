<?php

namespace App\Repository;

use App\Entity\ErrorLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ErrorLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ErrorLog::class);
    }

    /**
     * Liste filtrée, la plus récente d'abord.
     *
     * @param array{source?: ?string, level?: ?string, resolved?: ?bool, q?: ?string} $filters
     * @return array{items: ErrorLog[], total: int}
     */
    public function search(array $filters, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('e');

        if (!empty($filters['source'])) {
            $qb->andWhere('e.source = :source')->setParameter('source', $filters['source']);
        }
        if (!empty($filters['level'])) {
            $qb->andWhere('e.level = :level')->setParameter('level', $filters['level']);
        }
        if (isset($filters['resolved'])) {
            $qb->andWhere('e.resolved = :resolved')->setParameter('resolved', $filters['resolved']);
        }
        if (!empty($filters['q'])) {
            $qb->andWhere('e.message LIKE :q OR e.url LIKE :q OR e.userEmail LIKE :q OR e.exceptionClass LIKE :q')
                ->setParameter('q', '%' . addcslashes($filters['q'], '%_') . '%');
        }

        $total = (int) (clone $qb)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();

        $items = $qb->orderBy('e.lastSeenAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Compteurs pour l'en-tête de l'onglet : non résolues, par niveau.
     */
    public function countUnresolvedByLevel(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.level AS level, COUNT(e.id) AS total')
            ->where('e.resolved = false')
            ->groupBy('e.level')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys(ErrorLog::LEVELS, 0);
        foreach ($rows as $row) {
            $counts[$row['level']] = (int) $row['total'];
        }
        return $counts;
    }

    public function deleteResolved(): int
    {
        return $this->createQueryBuilder('e')
            ->delete()
            ->where('e.resolved = true')
            ->getQuery()
            ->execute();
    }
}
