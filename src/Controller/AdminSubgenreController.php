<?php

namespace App\Controller;

use App\Entity\Subgenre;
use App\Repository\SubgenreRepository;
use App\Repository\GenreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/subgenre')]
class AdminSubgenreController extends AbstractController
{
    public function __construct(
        private SubgenreRepository $subgenreRepository,
        private GenreRepository $genreRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * GET /api/admin/subgenre
     * Liste tous les subgenres (admin)
     */
    #[Route('', name: 'admin_subgenre_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $subgenres = $this->subgenreRepository->findAll();

        $data = array_map(function(Subgenre $subgenre) {
            return [
                'id' => $subgenre->getId(),
                'name' => $subgenre->getName(),
                'description' => $subgenre->getDescription(),
                'genre' => [
                    'id' => $subgenre->getGenre()?->getId(),
                    'name' => $subgenre->getGenre()?->getName(),
                ],
            ];
        }, $subgenres);

        return $this->json($data);
    }

    /**
     * GET /api/admin/subgenre/{id}
     * Récupère un subgenre par ID
     */
    #[Route('/{id}', name: 'admin_subgenre_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $subgenre = $this->subgenreRepository->find($id);

        if (!$subgenre) {
            return $this->json(['error' => 'Subgenre not found'], 404);
        }

        return $this->json([
            'id' => $subgenre->getId(),
            'name' => $subgenre->getName(),
            'description' => $subgenre->getDescription(),
            'genre_id' => $subgenre->getGenre()?->getId(),
        ]);
    }

    /**
     * POST /api/admin/subgenre
     * Crée un nouveau subgenre
     */
    #[Route('', name: 'admin_subgenre_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['genre_id'])) {
            return $this->json(['error' => 'Name and genre_id are required'], 400);
        }

        $genre = $this->genreRepository->find($data['genre_id']);
        if (!$genre) {
            return $this->json(['error' => 'Genre not found'], 404);
        }

        $subgenre = new Subgenre();
        $subgenre->setName($data['name']);
        $subgenre->setDescription($data['description'] ?? null);
        $subgenre->setGenre($genre);

        $this->entityManager->persist($subgenre);
        $this->entityManager->flush();

        return $this->json([
            'id' => $subgenre->getId(),
            'name' => $subgenre->getName(),
            'description' => $subgenre->getDescription(),
            'genre_id' => $subgenre->getGenre()->getId(),
        ], 201);
    }

    /**
     * PUT /api/admin/subgenre/{id}
     * Met à jour un subgenre
     */
    #[Route('/{id}', name: 'admin_subgenre_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $subgenre = $this->subgenreRepository->find($id);

        if (!$subgenre) {
            return $this->json(['error' => 'Subgenre not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $subgenre->setName($data['name']);
        }
        if (isset($data['description'])) {
            $subgenre->setDescription($data['description']);
        }
        if (isset($data['genre_id'])) {
            $genre = $this->genreRepository->find($data['genre_id']);
            if ($genre) {
                $subgenre->setGenre($genre);
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $subgenre->getId(),
            'name' => $subgenre->getName(),
            'description' => $subgenre->getDescription(),
            'genre_id' => $subgenre->getGenre()->getId(),
        ]);
    }

    /**
     * DELETE /api/admin/subgenre/{id}
     * Supprime un subgenre
     */
    #[Route('/{id}', name: 'admin_subgenre_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $subgenre = $this->subgenreRepository->find($id);

        if (!$subgenre) {
            return $this->json(['error' => 'Subgenre not found'], 404);
        }

        $this->entityManager->remove($subgenre);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}
