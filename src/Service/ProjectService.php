<?php

namespace App\Service;

use App\Entity\Project;
use App\Repository\PartRepository;
use App\Repository\TypeRepository;
use App\Repository\ProjectRepository;
use App\Repository\SequenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;


class ProjectService
{
    private ProjectRepository $projectRepository;
    private PartRepository $partRepository;
    private SequenceRepository $sequenceRepository;
    private TypeRepository $typeRepository;
    private EntityManagerInterface $entityManager;
    private SluggerInterface $slugger;
    private Security $security;

    public function __construct(
        ProjectRepository $projectRepository,
        PartRepository $partRepository,
        SequenceRepository $sequenceRepository,
        TypeRepository $typeRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        Security $security

    ) {
        $this->projectRepository = $projectRepository;
        $this->partRepository = $partRepository;
        $this->sequenceRepository = $sequenceRepository;
        $this->typeRepository = $typeRepository;
        $this->entityManager = $entityManager;
        $this->slugger = $slugger;
        $this->security = $security;
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
            $slug = $this->generateUniqueSlug($data['name'] ?? 'new-project');
            $project->setSlug($slug);
            $project->setUser($this->security->getUser());
        }

        $project->setName($data['name']);
        $project->setDescription($data['description']);
        
        // Handle type if provided
        if (isset($data['type_id'])) {
            $type = $this->typeRepository->find($data['type_id']);
            if ($type) {
                $project->setType($type);
            }
        } elseif (isset($data['type']) && is_array($data['type']) && isset($data['type']['id'])) {
            // Support legacy format
            $type = $this->typeRepository->find($data['type']['id']);
            if ($type) {
                $project->setType($type);
            }
        }
        
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

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = strtolower($this->slugger->slug($name));
        $slug = $baseSlug;
        $i = 2;

        while ($this->projectRepository->slugExists($slug))  {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        return $slug;
    }

}