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

    public function createOrUpdate(array $data): array
    {
        // retrieve the project by slug
        $part = $this->partRepository->createOrUpdate( $data);
        if (!$part) {
            return null;
        }

        // reorder the position
        if (!empty($data['afterPartId'])) {
            // return the part updated and array of positions
            $result = $this->reOrderPart($part, $data['afterPartId']);
            $part = $result['part'];
            $positions = array_flip($result['positions']);
        }

        return [
            'part' => $part,
            'positions' => $positions ?? []
        ];
    }

    public function reOrderPart(Part $currentPart, int $afterPartId): array
    {
        // retrieve all parts of the project
        $project = $this->projectRepository->find($currentPart->getProject());
        if(!$project) {
            return null;
        }

        // reorder position
        $datas = [];
        foreach($project->getParts() as $part) {

            if($part->getId() === $currentPart->getId()) continue;
            if($part->getId() === $afterPartId) {
                $datas[] = $part->getId();
                $datas[] = $currentPart->getId();
            } else {
                $datas[] = $part->getId();
            }
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