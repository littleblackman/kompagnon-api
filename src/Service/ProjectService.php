<?php

namespace App\Service;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\PartRepository;
use App\Repository\SequenceRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProjectService
{
    private ProjectRepository $projectRepository;
    private PartRepository $partRepository;
    private SequenceRepository $sequenceRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ProjectRepository $projectRepository,
        PartRepository $partRepository,
        SequenceRepository $sequenceRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->projectRepository = $projectRepository;
        $this->partRepository = $partRepository;
        $this->sequenceRepository = $sequenceRepository;
        $this->entityManager = $entityManager;
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

    public function getProjectBySlug(string $slug): ?Project
    {
        return $this->projectRepository->findOneBy(['slug' => $slug]);
    }

    public function createOrUpdate(?array $data): ?Project
    {
        $em = $this->entityManager;

        if(isset($data['id']))  {
            $project = $this->projectRepository->find($data['id']);
        } else {
            $project = new Project();
        }

        $project->setName($data['name']);
        $project->setDescription($data['description']);
        $project->setSlug($data['slug']);
        $em->persist($project);
        $em->flush();

        return $project;
    }

    public function delete(int $id): void
    {
        $project = $this->projectRepository->find($id);
        if (!$project) {
            throw new \Exception('Project not found');
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();
    }
}