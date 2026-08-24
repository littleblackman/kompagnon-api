<?php

namespace App\Service;

use App\Repository\ProjectRepository;
use App\Repository\PartRepository;
use App\Entity\Part;
use Doctrine\ORM\EntityManagerInterface;


class PartService
{

    private ProjectRepository $projectRepository;
    private PartRepository $partRepository;
    private EntityManagerInterface $entityManager;


    public function __construct(
        ProjectRepository $projectRepository,
        PartRepository $partRepository,
        EntityManagerInterface $entityManager
    )
    {
        $this->projectRepository = $projectRepository;
        $this->partRepository = $partRepository;
        $this->entityManager = $entityManager;
    }

    public function createOrUpdate(array $data): ?array
    {
        // retrieve the project by slug
        $part = $this->partRepository->createOrUpdate( $data);
        if (!$part) {
            return null;
        }

        // reorder the position
        (isset($data['afterPartId'])) ? $afterPartId = $data['afterPartId'] : $afterPartId= null;
        // return the part updated and array of positions
        $result = $this->reOrderPart($part, $afterPartId);
        $part = $result['part'];
        $positions = $result['positions'];


        return [
            'part' => $part,
            'positions' => $positions ?? []
        ];
    }

    /**
     * Supprime une partie (et sa hiérarchie, en cascade) puis renumérote
     * les parties restantes du projet.
     *
     * @return array<int, int> les ids des parties restantes, dans l'ordre
     */
    public function delete(int $id): array
    {
        $part = $this->partRepository->find($id);
        if (!$part) {
            throw new \Exception('La partie n\'existe pas');
        }

        $project = $part->getProject();

        $this->entityManager->remove($part);
        $this->entityManager->flush();

        // Combler le trou laissé par la partie supprimée
        $remaining = $this->partRepository->findBy(
            ['project' => $project],
            ['position' => 'ASC']
        );

        $ids = array_map(fn (Part $p) => $p->getId(), $remaining);
        $this->partRepository->bulkUpdatePositions($ids);

        return $ids;
    }

    public function reOrderPart(Part $currentPart, ?int $afterPartId = null): ?array
    {
        // retrieve all parts of the project
        $project = $this->projectRepository->find($currentPart->getProject());
        if(!$project) {
            return null;
        }

        // reorder position
        $datas = [];

        // if afterPartId is null, we add the current part at the beginning
        if ($afterPartId === null) $datas[] = $currentPart->getId();

        foreach($project->getParts() as $part) {
            // skip the current part in every iteration
            if($part->getId() === $currentPart->getId()) continue;
            // in all other cases, we add the part to the list
            $datas[] = $part->getId();
            // if we find the afterPartId, we add the current part after it
            if($part->getId() === $afterPartId) $datas[] = $currentPart->getId();
        }

        // persist the new order
        $this->partRepository->bulkUpdatePositions($datas);

        // return the updated part and alls positions
        return [
            'part' => $currentPart,
            'positions' => $datas
        ];
    }
}