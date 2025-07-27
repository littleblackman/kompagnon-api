<?php

namespace App\Controller;

use App\Service\PersonnageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

    #[Route('/api/personnage/{id}/upload-images', name: 'api_personnage_upload_images', methods: ['POST'])]
    public function uploadImages(int $id, Request $request, PersonnageService $personnageService): JsonResponse
    {
        try {
            $uploadedFiles = $request->files->get('images', []);
            
            if (empty($uploadedFiles)) {
                return $this->json(['error' => 'Aucun fichier fourni'], 400);
            }

            // Ensure it's an array even for single file
            if (!is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            // Validate files
            foreach ($uploadedFiles as $file) {
                if (!$file instanceof UploadedFile) {
                    return $this->json(['error' => 'Fichier invalide'], 400);
                }
                
                if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'])) {
                    return $this->json(['error' => 'Format d\'image non supporté. Utilisez JPG, PNG ou WebP.'], 400);
                }
                
                if ($file->getSize() > 10 * 1024 * 1024) { // 10MB max
                    return $this->json(['error' => 'Fichier trop volumineux (max 10MB)'], 400);
                }
            }

            $imageUrls = $personnageService->uploadImages($id, $uploadedFiles);
            
            return $this->json(['images' => $imageUrls], 200);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/personnage/{id}/reorder-images', name: 'api_personnage_reorder_images', methods: ['POST'])]
    public function reorderImages(int $id, Request $request, PersonnageService $personnageService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['images']) || !is_array($data['images'])) {
                return $this->json(['error' => 'Liste d\'images manquante'], 400);
            }

            $success = $personnageService->reorderImages($id, $data['images']);
            
            if (!$success) {
                return $this->json(['error' => 'Personnage non trouvé'], 404);
            }

            return $this->json(['success' => true], 200);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la réorganisation: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/personnage/{slug}/details', name: 'api_personnage_details', methods: ['GET'])]
    public function getPersonnageDetails(string $slug, PersonnageService $personnageService): JsonResponse
    {
        $personnageDetails = $personnageService->getPersonnageDetailsBySlug($slug);
        
        if (!$personnageDetails) {
            return $this->json(['error' => 'Personnage non trouvé'], 404);
        }

        return $this->json($personnageDetails, 200);
    }
}