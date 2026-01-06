<?php

namespace App\Controller;

use App\Entity\EventType;
use App\Repository\EventTypeRepository;
use App\Repository\NarrativePartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/event-type')]
class AdminEventTypeController extends AbstractController
{
    public function __construct(
        private EventTypeRepository $eventTypeRepository,
        private NarrativePartRepository $narrativePartRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * GET /api/admin/event-type
     * Liste tous les event types (admin)
     */
    #[Route('', name: 'admin_event_type_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $eventTypes = $this->eventTypeRepository->findAll();

        $data = array_map(function(EventType $eventType) {
            $events = $eventType->getEvents()->toArray();
            return [
                'id' => $eventType->getId(),
                'name' => $eventType->getName(),
                'code' => $eventType->getCode(),
                'description' => $eventType->getDescription(),
                'eventsCount' => count($events),
                'narrativePart' => [
                    'id' => $eventType->getNarrativePart()?->getId(),
                    'name' => $eventType->getNarrativePart()?->getName(),
                    'code' => $eventType->getNarrativePart()?->getCode(),
                ],
                'events' => array_map(function($event) {
                    return [
                        'id' => $event->getId(),
                        'name' => $event->getName(),
                        'description' => $event->getDescription(),
                        'isOptional' => $event->isOptional(),
                    ];
                }, $events)
            ];
        }, $eventTypes);

        return $this->json($data);
    }

    /**
     * GET /api/admin/event-type/{id}
     * Récupère un event type par ID
     */
    #[Route('/{id}', name: 'admin_event_type_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $eventType = $this->eventTypeRepository->find($id);

        if (!$eventType) {
            return $this->json(['error' => 'EventType not found'], 404);
        }

        return $this->json([
            'id' => $eventType->getId(),
            'name' => $eventType->getName(),
            'code' => $eventType->getCode(),
            'description' => $eventType->getDescription(),
            'narrative_part_id' => $eventType->getNarrativePart()?->getId(),
        ]);
    }

    /**
     * POST /api/admin/event-type
     * Crée un nouveau event type
     */
    #[Route('', name: 'admin_event_type_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['code'])) {
            return $this->json(['error' => 'Name and code are required'], 400);
        }

        $eventType = new EventType();
        $eventType->setName($data['name']);
        $eventType->setCode($data['code']);
        $eventType->setDescription($data['description'] ?? null);

        // Associer au narrative part si fourni
        if (isset($data['narrative_part_id'])) {
            $narrativePart = $this->narrativePartRepository->find($data['narrative_part_id']);
            if ($narrativePart) {
                $eventType->setNarrativePart($narrativePart);
                // Le narrativePartCode est automatiquement synchronisé dans setNarrativePart()
            }
        }

        $this->entityManager->persist($eventType);
        $this->entityManager->flush();

        return $this->json([
            'id' => $eventType->getId(),
            'name' => $eventType->getName(),
            'code' => $eventType->getCode(),
            'description' => $eventType->getDescription(),
            'narrative_part_id' => $eventType->getNarrativePart()?->getId(),
        ], 201);
    }

    /**
     * PUT /api/admin/event-type/{id}
     * Met à jour un event type
     */
    #[Route('/{id}', name: 'admin_event_type_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $eventType = $this->eventTypeRepository->find($id);

        if (!$eventType) {
            return $this->json(['error' => 'EventType not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $eventType->setName($data['name']);
        }
        if (isset($data['code'])) {
            $eventType->setCode($data['code']);
        }
        if (isset($data['description'])) {
            $eventType->setDescription($data['description']);
        }
        if (isset($data['narrative_part_id'])) {
            $narrativePart = $this->narrativePartRepository->find($data['narrative_part_id']);
            if ($narrativePart) {
                $eventType->setNarrativePart($narrativePart);
                // Le narrativePartCode est automatiquement synchronisé dans setNarrativePart()
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $eventType->getId(),
            'name' => $eventType->getName(),
            'code' => $eventType->getCode(),
            'description' => $eventType->getDescription(),
            'narrative_part_id' => $eventType->getNarrativePart()?->getId(),
        ]);
    }

    /**
     * DELETE /api/admin/event-type/{id}
     * Supprime un event type
     */
    #[Route('/{id}', name: 'admin_event_type_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $eventType = $this->eventTypeRepository->find($id);

        if (!$eventType) {
            return $this->json(['error' => 'EventType not found'], 404);
        }

        $this->entityManager->remove($eventType);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}
