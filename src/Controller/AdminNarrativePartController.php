<?php

namespace App\Controller;

use App\Entity\NarrativePart;
use App\Repository\NarrativePartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/narrative-part')]
class AdminNarrativePartController extends AbstractController
{
    public function __construct(
        private NarrativePartRepository $narrativePartRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * GET /api/admin/narrative-part
     * Liste tous les narrative parts (admin)
     */
    #[Route('', name: 'admin_narrative_part_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $narrativeParts = $this->narrativePartRepository->findAll();

        $data = array_map(function(NarrativePart $part) {
            $eventTypes = $part->getEventTypes()->toArray();
            return [
                'id' => $part->getId(),
                'name' => $part->getName(),
                'code' => $part->getCode(),
                'description' => $part->getDescription(),
                'eventTypesCount' => count($eventTypes),
            ];
        }, $narrativeParts);

        return $this->json($data);
    }

    /**
     * GET /api/admin/narrative-part/{id}
     * Récupère un narrative part par ID
     */
    #[Route('/{id}', name: 'admin_narrative_part_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $narrativePart = $this->narrativePartRepository->find($id);

        if (!$narrativePart) {
            return $this->json(['error' => 'NarrativePart not found'], 404);
        }

        return $this->json([
            'id' => $narrativePart->getId(),
            'name' => $narrativePart->getName(),
            'code' => $narrativePart->getCode(),
            'description' => $narrativePart->getDescription(),
        ]);
    }

    /**
     * POST /api/admin/narrative-part
     * Crée un nouveau narrative part
     */
    #[Route('', name: 'admin_narrative_part_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['code'])) {
            return $this->json(['error' => 'Name and code are required'], 400);
        }

        $narrativePart = new NarrativePart();
        $narrativePart->setName($data['name']);
        $narrativePart->setCode($data['code']);
        $narrativePart->setDescription($data['description'] ?? null);

        $this->entityManager->persist($narrativePart);
        $this->entityManager->flush();

        return $this->json([
            'id' => $narrativePart->getId(),
            'name' => $narrativePart->getName(),
            'code' => $narrativePart->getCode(),
            'description' => $narrativePart->getDescription(),
        ], 201);
    }

    /**
     * PUT /api/admin/narrative-part/{id}
     * Met à jour un narrative part
     */
    #[Route('/{id}', name: 'admin_narrative_part_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $narrativePart = $this->narrativePartRepository->find($id);

        if (!$narrativePart) {
            return $this->json(['error' => 'NarrativePart not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $narrativePart->setName($data['name']);
        }
        if (isset($data['code'])) {
            $narrativePart->setCode($data['code']);
        }
        if (isset($data['description'])) {
            $narrativePart->setDescription($data['description']);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $narrativePart->getId(),
            'name' => $narrativePart->getName(),
            'code' => $narrativePart->getCode(),
            'description' => $narrativePart->getDescription(),
        ]);
    }

    /**
     * DELETE /api/admin/narrative-part/{id}
     * Supprime un narrative part
     */
    #[Route('/{id}', name: 'admin_narrative_part_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $narrativePart = $this->narrativePartRepository->find($id);

        if (!$narrativePart) {
            return $this->json(['error' => 'NarrativePart not found'], 404);
        }

        $this->entityManager->remove($narrativePart);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}
