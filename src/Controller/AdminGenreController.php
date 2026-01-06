<?php

namespace App\Controller;

use App\Entity\Genre;
use App\Repository\GenreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/genre')]
class AdminGenreController extends AbstractController
{
    public function __construct(
        private GenreRepository $genreRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * GET /api/admin/genre
     * Liste tous les genres (admin)
     */
    #[Route('', name: 'admin_genre_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $genres = $this->genreRepository->findAll();

        $data = array_map(function(Genre $genre) {
            return [
                'id' => $genre->getId(),
                'name' => $genre->getName(),
                'description' => $genre->getDescription(),
                'subgenresCount' => $genre->getSubgenres()->count(),
            ];
        }, $genres);

        return $this->json($data);
    }

    /**
     * GET /api/admin/genre/{id}
     * Récupère un genre par ID
     */
    #[Route('/{id}', name: 'admin_genre_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $genre = $this->genreRepository->find($id);

        if (!$genre) {
            return $this->json(['error' => 'Genre not found'], 404);
        }

        return $this->json([
            'id' => $genre->getId(),
            'name' => $genre->getName(),
            'description' => $genre->getDescription(),
        ]);
    }

    /**
     * POST /api/admin/genre
     * Crée un nouveau genre
     */
    #[Route('', name: 'admin_genre_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->json(['error' => 'Name is required'], 400);
        }

        $genre = new Genre();
        $genre->setName($data['name']);
        $genre->setDescription($data['description'] ?? null);

        $this->entityManager->persist($genre);
        $this->entityManager->flush();

        return $this->json([
            'id' => $genre->getId(),
            'name' => $genre->getName(),
            'description' => $genre->getDescription(),
        ], 201);
    }

    /**
     * PUT /api/admin/genre/{id}
     * Met à jour un genre
     */
    #[Route('/{id}', name: 'admin_genre_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $genre = $this->genreRepository->find($id);

        if (!$genre) {
            return $this->json(['error' => 'Genre not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $genre->setName($data['name']);
        }
        if (isset($data['description'])) {
            $genre->setDescription($data['description']);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $genre->getId(),
            'name' => $genre->getName(),
            'description' => $genre->getDescription(),
        ]);
    }

    /**
     * DELETE /api/admin/genre/{id}
     * Supprime un genre
     */
    #[Route('/{id}', name: 'admin_genre_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $genre = $this->genreRepository->find($id);

        if (!$genre) {
            return $this->json(['error' => 'Genre not found'], 404);
        }

        // Vérifier si le genre a des subgenres
        if ($genre->getSubgenres()->count() > 0) {
            return $this->json(['error' => 'Cannot delete genre with subgenres'], 400);
        }

        $this->entityManager->remove($genre);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}
