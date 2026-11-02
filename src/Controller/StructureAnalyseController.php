<?php

namespace App\Controller;

use App\Repository\ProjectRepository;
use App\Repository\SequenceRepository;
use App\Service\StructureAnalyseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Le texte suit-il la structure ? Le front lit l'état, enchaîne la lecture
 * des séquences à (re)lire, puis lance la synthèse.
 */
class StructureAnalyseController extends AbstractController
{
    #[Route('/api/project/{id}/analyse/structure', name: 'api_project_structure_state', methods: ['GET'])]
    public function state(int $id, ProjectRepository $projectRepository, StructureAnalyseService $service): JsonResponse
    {
        $project = $projectRepository->find($id);
        if (!$project) {
            return $this->json(['success' => false, 'message' => 'Projet non trouvé'], 404);
        }

        return $this->json($service->stateOf($project));
    }

    #[Route('/api/sequence/{id}/analyse/structure-reading', name: 'api_sequence_structure_reading', methods: ['POST'])]
    public function readSequence(int $id, Request $request, SequenceRepository $sequenceRepository, StructureAnalyseService $service): JsonResponse
    {
        $sequence = $sequenceRepository->find($id);
        if (!$sequence) {
            return $this->json(['success' => false, 'message' => 'Séquence non trouvée'], 404);
        }

        $result = $service->readSequence($sequence, $request->query->getBoolean('force'));

        return $this->json(['sequenceId' => $id] + $result, $result['success'] ? 200 : 422);
    }

    #[Route('/api/project/{id}/analyse/structure', name: 'api_project_structure_synthesis', methods: ['POST'])]
    public function synthesize(int $id, ProjectRepository $projectRepository, StructureAnalyseService $service): JsonResponse
    {
        $project = $projectRepository->find($id);
        if (!$project) {
            return $this->json(['success' => false, 'message' => 'Projet non trouvé'], 404);
        }

        $result = $service->synthesize($project);

        // 409 : des lectures manquent, le front les relance puis réessaie
        $status = $result['success'] ? 200 : (isset($result['staleSequenceIds']) ? 409 : 422);

        return $this->json($result, $status);
    }
}
