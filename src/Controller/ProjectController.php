<?php

namespace App\Controller;

use App\Service\ProjectService;
use App\Service\ProjectTransferService;
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
                'groups' => ['project:read', 'part:read', 'sequence:read', 'scene:read', 'personnage:read'],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Instantané complet du projet en JSON, sans les images des personnages.
     * Servi en pièce jointe pour que le navigateur le télécharge.
     */
    #[Route('/project/{slug}/export', name: 'project_export', methods: ['GET'])]
    public function exportProject(string $slug, ProjectTransferService $transferService): JsonResponse
    {
        try {
            $project = $this->projectService->getProjectBySlug($slug);

            if (!$project) {
                return $this->json(['error' => 'Project not found'], 404);
            }

            $response = new JsonResponse($transferService->export($project));
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $filename = sprintf('kompagnon-%s-%s.json', $project->getSlug(), date('Y-m-d-His'));
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

            return $response;
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Recrée un projet depuis un export, toujours en nouveau projet. Les
     * métadonnées introuvables (critère, arc…) sont ignorées et listées dans
     * `warnings`.
     */
    #[Route('/project/import', name: 'project_import', methods: ['POST'])]
    public function importProject(Request $request, ProjectTransferService $transferService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'JSON invalide'], 400);
        }

        try {
            $result = $transferService->import($data);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }

        return $this->json([
            'project' => $result['project'],
            'warnings' => $result['warnings'],
        ], 201, [], ['groups' => ['project:read']]);
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
