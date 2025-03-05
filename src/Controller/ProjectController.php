<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\ProjectService;

class ProjectController extends AbstractController
{
    #[Route('/api/project/{slug}', name: 'api_get_project_by_slug', methods: ['GET'])]
    public function getProjectBySlug(ProjectService $projectService, string $slug): JsonResponse
    {
        $project = $projectService->getProjectWithDetails($slug);
        if (!$project) {
            return $this->json(['error' => 'Projet non trouvé'], 404);
        }
        return $this->json($project, 200, [], ['groups' => 'project:read']);
    }
}
