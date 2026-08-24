<?php

namespace App\Controller;

use App\Service\PartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class PartController extends AbstractController
{
    #[Route('/api/part/update', name: 'api_part_update', methods: ['PUT', 'POST'])]
    public function updatePart(Request $request,  PartService $partService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (empty($data['name'])) {
            return $this->json(['error' => 'Le nom est obligatoire'], 400);
        }
        $result = $partService->createOrUpdate($data);

        // Sans groupes, la sérialisation part en Part -> Project -> parts -> Part…
        return $this->json($result, 200, [], ['groups' => ['part:read']]);
    }

    #[Route('/api/part/delete/{id}', name: 'api_part_delete', methods: ['DELETE'])]
    public function deletePart(int $id, PartService $partService): JsonResponse
    {
        try {
            $positions = $partService->delete($id);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }

        return $this->json([
            'message'   => 'La partie a été supprimée',
            'positions' => $positions,
        ], 200);
    }
}
