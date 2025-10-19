<?php

namespace App\Repository;

use App\Entity\SubgenreDramaticFunction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubgenreDramaticFunction>
 */
class SubgenreDramaticFunctionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubgenreDramaticFunction::class);
    }
}
