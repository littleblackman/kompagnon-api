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
        $user = $this->security->getUser();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        return $this->projectRepository->findOneBy([
            'slug' => $slug,
            'user' => $user
        ]);
    }

    public function createOrUpdate(?array $data): ?Project
    {
        $em = $this->entityManager;
        $user = $this->security->getUser();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        if(isset($data['id']))  {
            $project = $this->projectRepository->findOneBy([
                'id' => $data['id'],
                'user' => $user
            ]);

            if (!$project) {
                throw new \Exception('Project not found or access denied');
            }
        } else {
            $project = new Project();
            $slug = $this->generateUniqueSlug($data['name'] ?? 'new-project');
            $project->setSlug($slug);
            $project->setUser($user);
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
        $user = $this->security->getUser();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $project = $this->projectRepository->findOneBy([
            'id' => $id,
            'user' => $user
        ]);

        if (!$project) {
            throw new \Exception('Project not found or access denied');
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