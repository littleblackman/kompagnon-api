<?php

namespace App\Repository;

use App\Entity\Part;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PartRepository extends ServiceEntityRepository
{

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Part::class);
    }

    public function createOrUpdate(array $data): Part
    {
        $em = $this->getEntityManager();

        if(!$part = $this->find($data['id'])) $part = new Part();

        $part->setName($data['name'])
            ->setDescription($data['description'] ?? '')
            ->setPosition($data['order'] ?? 0)
            ->setUpdatedAt(new \DateTimeImmutable());
        if (!$part->getId()) {
            $part->setCreatedAt(new \DateTimeImmutable());
            $em->persist($part);
        }

        $em->flush();

        return $part;
    }
}
