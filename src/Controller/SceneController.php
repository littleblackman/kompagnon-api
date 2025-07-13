<?php

namespace App\Controller;

use App\Service\SceneService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SceneController extends AbstractController
{
    #[Route('/api/scene/update', name: 'api_scene_update', methods: ['POST'])]
    public function createOrUpdateScene(SceneService $sceneService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $scene = $sceneService->createOrUpdate($data);

        // Configuration de la sérialisation
        $context = [
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            },
            'ignored_attributes' => ['sequence'],
            'max_depth' => 1,
        ];

        return $this->json(['scene' => $scene], 200, [], $context);
    }

    #[Route('/api/scene/delete/{id}', name: 'api_scene_delete', methods: ['DELETE'])]
    public function deleteScene(SceneService $sceneService, int $id): JsonResponse
    {
        $sceneService->delete($id);

        return $this->json(['success' => true], 200, []);
    }
}
