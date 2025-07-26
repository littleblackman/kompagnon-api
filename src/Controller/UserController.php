<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserController extends AbstractController
{
    #[Route('/api/user/profile', name: 'api_user_profile_update', methods: ['POST', 'PUT'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateProfile(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return $this->json(['error' => 'Données JSON invalides'], 400);
        }

        // Mise à jour des champs autorisés
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        if (isset($data['avatar'])) {
            $user->setAvatar($data['avatar']);
        }

        try {
            $entityManager->flush();
            
            return $this->json([
                'message' => 'Profil mis à jour avec succès',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'fullName' => $user->getFullName(),
                    'avatar' => $user->getAvatar(),
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour du profil'], 500);
        }
    }

    #[Route('/api/user/profile', name: 'api_user_profile_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getProfile(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'avatar' => $user->getAvatar(),
                'bio' => $user->getBio(),
                'speciality' => $user->getSpeciality(),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    #[Route('/api/user/upload-avatar', name: 'api_user_upload_avatar', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function uploadAvatar(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        try {
            $uploadedFile = $request->files->get('avatar');
            
            if (!$uploadedFile) {
                return $this->json(['error' => 'Aucun fichier fourni'], 400);
            }

            if (!$uploadedFile instanceof UploadedFile) {
                return $this->json(['error' => 'Fichier invalide'], 400);
            }
            
            if (!in_array($uploadedFile->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'])) {
                return $this->json(['error' => 'Format d\'image non supporté. Utilisez JPG, PNG ou WebP.'], 400);
            }
            
            if ($uploadedFile->getSize() > 5 * 1024 * 1024) { // 5MB max
                return $this->json(['error' => 'Fichier trop volumineux (max 5MB)'], 400);
            }

            // Create upload directory if it doesn't exist
            $uploadDir = 'uploads/avatars';
            $publicDir = __DIR__ . '/../../public/' . $uploadDir;
            
            if (!is_dir($publicDir)) {
                mkdir($publicDir, 0755, true);
            }

            // Delete old avatar if exists
            if ($user->getAvatar()) {
                $oldAvatarPath = __DIR__ . '/../../public/' . $user->getAvatar();
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }

            // Generate unique filename
            $filename = 'avatar-' . $user->getId() . '-' . uniqid() . '.' . $uploadedFile->guessExtension();
            $filePath = $publicDir . '/' . $filename;
            $relativePath = $uploadDir . '/' . $filename;

            // Move uploaded file
            $uploadedFile->move($publicDir, $filename);

            // Update user avatar
            $user->setAvatar($relativePath);
            $entityManager->flush();
            
            return $this->json([
                'message' => 'Avatar mis à jour avec succès',
                'avatar' => $relativePath,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'fullName' => $user->getFullName(),
                    'avatar' => $user->getAvatar(),
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 500);
        }
    }
}