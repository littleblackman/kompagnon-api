<?php

namespace App\Controller;

use App\Entity\NarrativeStructure;
use App\Repository\NarrativeStructureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/narrative-structure')]
class AdminNarrativeStructureController extends AbstractController
{
    public function __construct(
        private NarrativeStructureRepository $narrativeStructureRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * GET /api/admin/narrative-structure
     * Liste toutes les structures narratives (admin)
     */
    #[Route('', name: 'admin_narrative_structure_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $structures = $this->narrativeStructureRepository->findAll();

        $data = array_map(function(NarrativeStructure $structure) {
            return [
                'id' => $structure->getId(),
                'name' => $structure->getName(),
                'description' => $structure->getDescription(),
                'totalBeats' => $structure->getTotalBeats(),
                'narrativePartOrder' => $structure->getNarrativePartOrder(),
            ];
        }, $structures);

        return $this->json($data);
    }

    /**
     * GET /api/admin/narrative-structure/{id}
     * Récupère une structure narrative par ID
     */
    #[Route('/{id}', name: 'admin_narrative_structure_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $structure = $this->narrativeStructureRepository->find($id);

        if (!$structure) {
            return $this->json(['error' => 'NarrativeStructure not found'], 404);
        }

        return $this->json([
            'id' => $structure->getId(),
            'name' => $structure->getName(),
            'description' => $structure->getDescription(),
            'narrativePartOrder' => $structure->getNarrativePartOrder(),
        ]);
    }

    /**
     * POST /api/admin/narrative-structure
     * Crée une nouvelle structure narrative
     */
    #[Route('', name: 'admin_narrative_structure_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->json(['error' => 'Name is required'], 400);
        }

        $structure = new NarrativeStructure();
        $structure->setName($data['name']);
        $structure->setDescription($data['description'] ?? null);

        if (isset($data['narrativePartOrder'])) {
            $structure->setNarrativePartOrder($data['narrativePartOrder']);
        }

        $this->entityManager->persist($structure);
        $this->entityManager->flush();

        return $this->json([
            'id' => $structure->getId(),
            'name' => $structure->getName(),
            'description' => $structure->getDescription(),
            'narrativePartOrder' => $structure->getNarrativePartOrder(),
            'totalBeats' => $structure->getTotalBeats(),
        ], 201);
    }

    /**
     * PUT /api/admin/narrative-structure/{id}
     * Met à jour une structure narrative
     */
    #[Route('/{id}', name: 'admin_narrative_structure_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $structure = $this->narrativeStructureRepository->find($id);

        if (!$structure) {
            return $this->json(['error' => 'NarrativeStructure not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $structure->setName($data['name']);
        }
        if (isset($data['description'])) {
            $structure->setDescription($data['description']);
        }
        if (isset($data['narrativePartOrder'])) {
            $structure->setNarrativePartOrder($data['narrativePartOrder']);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $structure->getId(),
            'name' => $structure->getName(),
            'description' => $structure->getDescription(),
            'narrativePartOrder' => $structure->getNarrativePartOrder(),
            'totalBeats' => $structure->getTotalBeats(),
        ]);
    }

    /**
     * DELETE /api/admin/narrative-structure/{id}
     * Supprime une structure narrative
     */
    #[Route('/{id}', name: 'admin_narrative_structure_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $structure = $this->narrativeStructureRepository->find($id);

        if (!$structure) {
            return $this->json(['error' => 'NarrativeStructure not found'], 404);
        }

        $this->entityManager->remove($structure);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}
