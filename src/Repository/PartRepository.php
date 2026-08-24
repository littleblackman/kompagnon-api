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
    private EntityManagerInterface $em;

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
            ->setUpdatedAt(new \DateTimeImmutable());
        if (!$part->getId()) {
            // La position définitive est posée juste après par PartService::reOrderPart.
            // Ne jamais la réécrire sur un update : renommer une partie ne doit
            // pas la faire remonter en tête du projet.
            $part->setPosition($data['order'] ?? 0)
                ->setProject($project)
                ->setStatus($status)
                ->setCreatedAt(new \DateTimeImmutable());
        }

        $em->persist($part);

        $em->flush();

        return $part;
    }

    /**
     * Réécrit les positions en 1..N à partir d'une liste d'ids ordonnée.
     *
     * @param array<int, int> $positions ids des parties, dans l'ordre voulu
     */
    public function bulkUpdatePositions(array $positions)
    {
        if (empty($positions)) {
            return 0;
        }

        $caseStatements = [];
        $ids = [];
        $params = [];

        foreach (array_values($positions) as $index => $id) {
            // Base 1, pour s'aligner sur les séquences et les scènes
            $caseStatements[] = "WHEN p.id = :id$index THEN :pos$index";
            $params["id$index"]  = $id;
            $params["pos$index"] = $index + 1;
            $ids[] = $id;
        }

        $caseSql = implode(" ", $caseStatements);

        $query = $this->em->createQuery("
        UPDATE App\Entity\Part p
        SET p.position = CASE $caseSql ELSE p.position END
        WHERE p.id IN (:ids)
    ");

        foreach ($params as $key => $value) {
            $query->setParameter($key, $value);
        }
        $query->setParameter('ids', $ids);

        return $query->execute();
    }
}
