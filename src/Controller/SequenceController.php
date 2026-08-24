<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\SequenceService;

class SequenceController extends AbstractController
{
    #[Route('/api/sequence/update', name: 'api_sequence_update', methods: ['POST'])]
    public function createOrUpdateSequence(SequenceService $sequenceService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        try {
            $sequence = $sequenceService->createOrUpdate($data);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }

        // Configuration de la sérialisation
        $context = [
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            },
            'ignored_attributes' => ['sequencePersonnages', 'sequenceCriterias'],
            'max_depth' => 1
        ];

        return $this->json([
            'sequence'  => $sequence,
            'positions' => $sequenceService->getPositions($sequence->getPart()),
        ], 200, [], $context);
    }

    #[Route('/api/sequence/order', name: 'api_sequence_order', methods: ['POST'])]
    public function updateSequenceOrder(SequenceService $sequenceService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sequenceService->updateOrder($data['sequences']);

        return $this->json(['success' => true], 200, []);
    }

    #[Route('/api/sequence/delete/{id}', name: 'api_sequence_delete', methods: ['DELETE'])]
    public function deleteSequence(SequenceService $sequenceService, int $id): JsonResponse
    {
        try {
            $sequenceService->delete($id);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }

        return $this->json(['success' => true], 200, []);
    }
}
