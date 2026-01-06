<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\EventTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/event')]
class AdminEventController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private EventTypeRepository $eventTypeRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * GET /api/admin/event
     * Liste tous les events (admin)
     */
    #[Route('', name: 'admin_event_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $events = $this->eventRepository->findAll();

        $data = array_map(function(Event $event) {
            return [
                'id' => $event->getId(),
                'name' => $event->getName(),
                'description' => $event->getDescription(),
                'isOptional' => $event->isOptional(),
                'eventType' => [
                    'id' => $event->getEventType()?->getId(),
                    'name' => $event->getEventType()?->getName(),
                ],
            ];
        }, $events);

        return $this->json($data);
    }

    /**
     * GET /api/admin/event/{id}
     * Récupère un event par ID
     */
    #[Route('/{id}', name: 'admin_event_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if (!$event) {
            return $this->json(['error' => 'Event not found'], 404);
        }

        return $this->json([
            'id' => $event->getId(),
            'name' => $event->getName(),
            'description' => $event->getDescription(),
            'isOptional' => $event->isOptional(),
            'event_type_id' => $event->getEventType()?->getId(),
        ]);
    }

    /**
     * POST /api/admin/event
     * Crée un nouveau event
     */
    #[Route('', name: 'admin_event_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['event_type_id'])) {
            return $this->json(['error' => 'Name and event_type_id are required'], 400);
        }

        $eventType = $this->eventTypeRepository->find($data['event_type_id']);
        if (!$eventType) {
            return $this->json(['error' => 'EventType not found'], 404);
        }

        $event = new Event();
        $event->setName($data['name']);
        $event->setDescription($data['description'] ?? null);
        $event->setIsOptional($data['isOptional'] ?? false);
        $event->setEventType($eventType);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->json([
            'id' => $event->getId(),
            'name' => $event->getName(),
            'description' => $event->getDescription(),
            'isOptional' => $event->isOptional(),
            'event_type_id' => $event->getEventType()->getId(),
        ], 201);
    }

    /**
     * PUT /api/admin/event/{id}
     * Met à jour un event
     */
    #[Route('/{id}', name: 'admin_event_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if (!$event) {
            return $this->json(['error' => 'Event not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $event->setName($data['name']);
        }
        if (isset($data['description'])) {
            $event->setDescription($data['description']);
        }
        if (isset($data['isOptional'])) {
            $event->setIsOptional($data['isOptional']);
        }
        if (isset($data['event_type_id'])) {
            $eventType = $this->eventTypeRepository->find($data['event_type_id']);
            if ($eventType) {
                $event->setEventType($eventType);
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $event->getId(),
            'name' => $event->getName(),
            'description' => $event->getDescription(),
            'isOptional' => $event->isOptional(),
            'event_type_id' => $event->getEventType()->getId(),
        ]);
    }

    /**
     * DELETE /api/admin/event/{id}
     * Supprime un event
     */
    #[Route('/{id}', name: 'admin_event_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if (!$event) {
            return $this->json(['error' => 'Event not found'], 404);
        }

        $this->entityManager->remove($event);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}
