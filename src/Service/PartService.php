<?php

namespace App\Service;

use App\Repository\ProjectRepository;
use App\Repository\PartRepository;
use App\Repository\SequenceRepository;
use App\Repository\SceneRepository;



class PartService
{

    private ProjectRepository $projectRepository;
    private PartRepository $partRepository;
    private SequenceRepository $sequenceRepository;
    private SceneRepository $sceneRepository;


    public function __construct( ProjectRepository $projectRepository,

    )
    {
        $this->projectRepository = $projectRepository;

    }

    public function getProjectWithDetails(string $slug): ?array
    {
        // retrieve the project by slug
        $project = $this->projectRepository->getProjectWithDetails( $slug);
        if (!$project) {
            return null;
        }

        return $project;
    }
}