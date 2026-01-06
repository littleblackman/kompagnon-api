<?php

namespace App\Controller;

use App\Service\ProjectAnalyseService;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProjectAnalyseController extends AbstractController
{
    #[Route('/api/project/{id}/analyse', name: 'api_project_analyse', methods: ['POST'])]
    public function analyzeProject(
        int $id,
        Request $request,
        ProjectRepository $projectRepository,
        ProjectAnalyseService $analyseService
    ): JsonResponse {
        $project = $projectRepository->find($id);

        if (!$project) {
            return $this->json(['error' => 'Projet non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $useFullContent = $data['useFullContent'] ?? false;

        $result = $analyseService->analyzeProject($project, $useFullContent);

        return $this->json($result, $result['success'] ? 200 : 500);
    }

    #[Route('/api/project/{id}/analyse/apply', name: 'api_project_analyse_apply', methods: ['POST'])]
    public function applyAnalysis(
        int $id,
        Request $request,
        ProjectRepository $projectRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $project = $projectRepository->find($id);

        if (!$project) {
            return $this->json(['error' => 'Projet non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // Récupérer les composantes narratives actuelles
        $referenceComponents = $project->getReferenceNarrativeComponents() ?? [];

        // Appliquer les suggestions dans le champ JSON
        if (isset($data['genreId'])) {
            $referenceComponents['genre_id'] = $data['genreId'];
        }

        if (isset($data['subgenreId'])) {
            $referenceComponents['subgenre_id'] = $data['subgenreId'];
        }

        if (isset($data['structureId'])) {
            $referenceComponents['narrative_structure_id'] = $data['structureId'];
        }

        // Mettre à jour le projet
        $project->setReferenceNarrativeComponents($referenceComponents);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Suggestions appliquées avec succès',
            'referenceNarrativeComponents' => $referenceComponents
        ], 200);
    }
}
