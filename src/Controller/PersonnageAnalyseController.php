<?php

namespace App\Controller;

use App\Repository\PersonnageRepository;
use App\Service\PersonnageAnalyseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PersonnageAnalyseController extends AbstractController
{
    /**
     * Génère ou régénère le schéma actantiel global du personnage. Le schéma
     * enregistré est ensuite servi par /api/personnage/{slug}/details.
     */
    #[Route('/api/personnage/{id}/analyse/actantiel-schema', name: 'api_personnage_analyse_actantial_schema', methods: ['POST'])]
    public function generateActantialSchema(
        int $id,
        PersonnageRepository $personnageRepository,
        PersonnageAnalyseService $analyseService
    ): JsonResponse {
        $personnage = $personnageRepository->find($id);
        if (!$personnage) {
            return $this->json(['success' => false, 'message' => 'Personnage non trouvé'], 404);
        }

        $result = $analyseService->generateActantialSchema($personnage);

        // 422 : la requête est valide, c'est le personnage qui n'est pas analysable
        return $this->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Vérifie les arcs du personnage contre le texte et propose un background
     * et une analyse mis à jour. Rien n'est enregistré : l'auteur valide côté
     * front, puis la sauvegarde passe par /api/personnage/update.
     */
    #[Route('/api/personnage/{id}/analyse/verification', name: 'api_personnage_analyse_verification', methods: ['POST'])]
    public function verifyPersonnage(
        int $id,
        PersonnageRepository $personnageRepository,
        PersonnageAnalyseService $analyseService
    ): JsonResponse {
        $personnage = $personnageRepository->find($id);
        if (!$personnage) {
            return $this->json(['success' => false, 'message' => 'Personnage non trouvé'], 404);
        }

        $result = $analyseService->verifyPersonnage($personnage);

        return $this->json($result, $result['success'] ? 200 : 422);
    }
}
