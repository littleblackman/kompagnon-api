<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\SequenceService;
use App\Service\SequenceAnalyseService;
use App\Repository\SequenceRepository;

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

    /**
     * Alignement du texte écrit avec l'intention, l'idée esthétique et
     * l'information déclarées. Verdict à trois niveaux par champ.
     */
    #[Route('/api/sequence/{id}/analyse/alignment', name: 'api_sequence_analyse_alignment', methods: ['POST'])]
    public function analyseAlignment(
        int $id,
        SequenceRepository $sequenceRepository,
        SequenceAnalyseService $analyseService
    ): JsonResponse {
        $sequence = $sequenceRepository->find($id);
        if (!$sequence) {
            return $this->json(['success' => false, 'message' => 'Séquence non trouvée'], 404);
        }

        $result = $analyseService->analyseAlignment($sequence);

        // 422 : la requête est valide, c'est la séquence qui n'est pas analysable
        return $this->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Notes d'intensité proposées par le modèle, sur la même échelle 0-10
     * que la saisie manuelle. L'auteur reste libre de les reprendre.
     */
    #[Route('/api/sequence/{id}/analyse/criterias', name: 'api_sequence_analyse_criterias', methods: ['POST'])]
    public function analyseCriterias(
        int $id,
        SequenceRepository $sequenceRepository,
        SequenceAnalyseService $analyseService
    ): JsonResponse {
        $sequence = $sequenceRepository->find($id);
        if (!$sequence) {
            return $this->json(['success' => false, 'message' => 'Séquence non trouvée'], 404);
        }

        $result = $analyseService->suggestCriterias($sequence);

        return $this->json($result, $result['success'] ? 200 : 422);
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
