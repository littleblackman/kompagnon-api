<?php

namespace App\Repository;

use App\Entity\LanguageLevel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LanguageLevel>
 */
class LanguageLevelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LanguageLevel::class);
    }

    /** @return LanguageLevel[] */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC']);
    }
}
