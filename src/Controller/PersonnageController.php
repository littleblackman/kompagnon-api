<?php

namespace App\Controller;

use App\Service\PersonnageService;
use App\Repository\PersonnageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class PersonnageController extends AbstractController
{
    #[Route('/api/personnage/update', name: 'api_personnage_update', methods: ['PUT', 'POST'])]
    public function updatePersonnage(Request $request, PersonnageService $personnageService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['firstName']) && empty($data['lastName'])) {
            return $this->json(['error' => 'Le prénom ou le nom est obligatoire'], 400);
        }

        if (empty($data['project_id'])) {
            return $this->json(['error' => 'Le projet est obligatoire'], 400);
        }

        $personnage = $personnageService->createOrUpdate($data);
        
        if (!$personnage) {
            return $this->json(['error' => 'Erreur lors de la création/mise à jour du personnage'], 500);
        }

        return $this->json($personnage, 200, [], ['groups' => 'personnage:read']);
    }

    #[Route('/api/personnage/delete/{id}', name: 'api_personnage_delete', methods: ['DELETE'])]
    public function deletePersonnage(Request $request, PersonnageService $personnageService): JsonResponse
    {
        $id = $request->get('id');
        
        if (!$id) {
            return $this->json(['error' => 'ID du personnage manquant'], 400);
        }

        $success = $personnageService->deletePersonnage($id);
        
        if (!$success) {
            return $this->json(['error' => 'Le personnage n\'existe pas'], 404);
        }

        return $this->json(['message' => 'Le personnage a été supprimé'], 200);
    }

    #[Route('/api/personnages/project/{projectId}', name: 'api_personnages_by_project', methods: ['GET'])]
    public function getPersonnagesByProject(Request $request, PersonnageService $personnageService): JsonResponse
    {
        $projectId = $request->get('projectId');
        
        if (!$projectId) {
            return $this->json(['error' => 'ID du projet manquant'], 400);
        }

        $personnages = $personnageService->getPersonnagesByProject($projectId);

        return $this->json($personnages, 200, [], ['groups' => 'personnage:read']);
    }
}