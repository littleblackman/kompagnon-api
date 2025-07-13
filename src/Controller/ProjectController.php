<?php

namespace App\Controller;

use App\Service\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api')]
class ProjectController extends AbstractController
{
    private ProjectService $projectService;
    private SerializerInterface $serializer;

    public function __construct(ProjectService $projectService, SerializerInterface $serializer)
    {
        $this->projectService = $projectService;
        $this->serializer = $serializer;
    }

    #[Route('/project/{slug}', name: 'project_get', methods: ['GET'])]
    public function getProject(string $slug): JsonResponse
    {
        try {
            $project = $this->projectService->getProjectBySlug($slug);

            if (!$project) {
                return $this->json(['error' => 'Project not found'], 404);
            }

            return $this->json($project, 200, [], [
                'groups' => ['project:read', 'part:read', 'sequence:read', 'scene:read'],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/project/update', name: 'project_update', methods: ['POST'])]
    public function createOrUpdateProject(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $project = $this->projectService->createOrUpdate($data);

            return $this->json($project, 200, [], [
                'groups' => ['project:read'],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/project/delete/{id}', name: 'project_delete', methods: ['DELETE'])]
    public function deleteProject(int $id): JsonResponse
    {
        $this->projectService->delete($id);

        return new JsonResponse(['success' => true]);
    }
}
