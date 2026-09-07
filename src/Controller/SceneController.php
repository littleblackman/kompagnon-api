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

        try {
            $scene = $sceneService->createOrUpdate($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }

        // Configuration de la sérialisation
        $context = [
            'groups' => ['scene:read'],
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            },
            'ignored_attributes' => ['sequence'],
            'max_depth' => 1,
        ];

        return $this->json([
            'scene'     => $scene,
            'positions' => $sceneService->getPositions($scene->getSequence()),
        ], 200, [], $context);
    }

    #[Route('/api/scene/order', name: 'api_scene_order', methods: ['POST'])]
    public function updateSceneOrder(SceneService $sceneService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sceneService->updateOrder($data['scenes']);

        return $this->json(['success' => true], 200, []);
    }

    #[Route('/api/scene/move', name: 'api_scene_move', methods: ['POST'])]
    public function moveScene(SceneService $sceneService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['sceneId'], $data['targetSequenceId'])) {
            return $this->json(['error' => 'sceneId et targetSequenceId sont requis'], 400);
        }

        try {
            $moved = $sceneService->moveToSequence(
                (int) $data['sceneId'],
                (int) $data['targetSequenceId'],
                isset($data['afterSceneId']) ? (int) $data['afterSceneId'] : null
            );
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }

        // Les deux conteneurs ont bougé : le front doit resynchroniser les deux
        return $this->json([
            'success'         => true,
            'sourceSequenceId' => $moved['source']->getId(),
            'targetSequenceId' => $moved['target']->getId(),
            'sourcePositions' => $sceneService->getPositions($moved['source']),
            'targetPositions' => $sceneService->getPositions($moved['target']),
        ], 200);
    }

    #[Route('/api/scene/delete/{id}', name: 'api_scene_delete', methods: ['DELETE'])]
    public function deleteScene(SceneService $sceneService, int $id): JsonResponse
    {
        try {
            $sequence = $sceneService->delete($id);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }

        return $this->json([
            'success'   => true,
            'positions' => $sceneService->getPositions($sequence),
        ], 200, []);
    }
}
