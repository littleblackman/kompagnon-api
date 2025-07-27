<?php

namespace App\Repository;

use App\Entity\NarrativeArc;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NarrativeArc>
 */
class NarrativeArcRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NarrativeArc::class);
    }
}