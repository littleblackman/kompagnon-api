<?php

namespace App\Repository;

use App\Entity\Part;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class PartRepository extends ServiceEntityRepository
{

    private ManagerRegistry $managerRegistry;
    private ProjectRepository $projectRepository;
    private StatusRepository $statusRepository;

    public function __construct(ManagerRegistry $registry, ProjectRepository $projectRepository, StatusRepository $statusRepository, EntityManagerInterface $em)
    {
        parent::__construct($registry, Part::class);
        $this->projectRepository = $projectRepository;
        $this->statusRepository = $statusRepository;
        $this->em = $em;
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

    public function bulkUpdatePositions(array $positions)
    {

        $caseStatements = [];
        $ids = [];
        foreach ($positions as $position => $id) {
            $caseStatements[] = 'WHEN p.id = '.$id.' THEN '.$position. ' ';
            $ids[] = $id;
        }

        $caseSql = implode(" ", $caseStatements);

        $query = $this->em->createQuery("
        UPDATE App\Entity\Part p
        SET p.position = CASE $caseSql ELSE p.position END
        WHERE p.id IN (:ids)
    ");

        $query->setParameter('ids', $ids);
        return $query->execute();
    }
}
