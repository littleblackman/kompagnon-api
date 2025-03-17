<?php

namespace App\Service;

use App\Repository\ProjectRepository;
use App\Repository\PartRepository;
use App\Entity\Part;


class PartService
{

    private ProjectRepository $projectRepository;
    private PartRepository $partRepository;


    public function __construct(
        ProjectRepository $projectRepository,
        PartRepository $partRepository
    )
    {
        $this->projectRepository = $projectRepository;
        $this->partRepository = $partRepository;
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

    public function reOrderPart(Part $currentPart, int $afterPartId = null): ?array
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