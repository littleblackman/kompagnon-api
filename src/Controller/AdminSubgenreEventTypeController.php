<?php

namespace App\Controller;

use App\Entity\SubgenreEventType;
use App\Repository\SubgenreRepository;
use App\Repository\EventTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/subgenre-event-type')]
class AdminSubgenreEventTypeController extends AbstractController
{
    public function __construct(
        private SubgenreRepository $subgenreRepository,
        private EventTypeRepository $eventTypeRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * POST /api/admin/subgenre-event-type/add
     * Ajoute un event type à un sous-genre
     */
    #[Route('/add', name: 'admin_subgenre_event_type_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['subgenre_id']) || empty($data['event_type_id'])) {
            return $this->json(['error' => 'subgenre_id and event_type_id are required'], 400);
        }

        $subgenre = $this->subgenreRepository->find($data['subgenre_id']);
        if (!$subgenre) {
            return $this->json(['error' => 'Subgenre not found'], 404);
        }

        $eventType = $this->eventTypeRepository->find($data['event_type_id']);
        if (!$eventType) {
            return $this->json(['error' => 'EventType not found'], 404);
        }

        // Vérifier si la relation existe déjà
        foreach ($subgenre->getSubgenreEventTypes() as $subgenreEventType) {
            if ($subgenreEventType->getEventType() === $eventType) {
                return $this->json(['error' => 'This event type is already assigned to this subgenre'], 400);
            }
        }

        // Créer la relation
        $subgenreEventType = new SubgenreEventType();
        $subgenreEventType->setSubgenre($subgenre);
        $subgenreEventType->setEventType($eventType);
        $subgenreEventType->setWeight($data['weight'] ?? 1); // Par défaut 1
        $subgenreEventType->setIsMandatory($data['is_mandatory'] ?? true); // Par défaut obligatoire

        $this->entityManager->persist($subgenreEventType);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'subgenre_id' => $subgenre->getId(),
            'event_type_id' => $eventType->getId(),
            'weight' => $subgenreEventType->getWeight(),
            'is_mandatory' => $subgenreEventType->isMandatory()
        ], 201);
    }

    /**
     * PUT /api/admin/subgenre-event-type/update
     * Met à jour le weight et is_mandatory d'une relation
     */
    #[Route('/update', name: 'admin_subgenre_event_type_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['subgenre_id']) || empty($data['event_type_id'])) {
            return $this->json(['error' => 'subgenre_id and event_type_id are required'], 400);
        }

        $subgenre = $this->subgenreRepository->find($data['subgenre_id']);
        if (!$subgenre) {
            return $this->json(['error' => 'Subgenre not found'], 404);
        }

        $eventType = $this->eventTypeRepository->find($data['event_type_id']);
        if (!$eventType) {
            return $this->json(['error' => 'EventType not found'], 404);
        }

        // Trouver la relation existante
        foreach ($subgenre->getSubgenreEventTypes() as $subgenreEventType) {
            if ($subgenreEventType->getEventType() === $eventType) {
                // Mettre à jour weight et is_mandatory
                if (isset($data['weight'])) {
                    $weight = (int)$data['weight'];
                    if ($weight < 1 || $weight > 5) {
                        return $this->json(['error' => 'Weight must be between 1 and 5'], 400);
                    }
                    $subgenreEventType->setWeight($weight);
                }
                if (isset($data['is_mandatory'])) {
                    $subgenreEventType->setIsMandatory((bool)$data['is_mandatory']);
                }

                $this->entityManager->flush();

                return $this->json([
                    'success' => true,
                    'weight' => $subgenreEventType->getWeight(),
                    'is_mandatory' => $subgenreEventType->isMandatory()
                ]);
            }
        }

        return $this->json(['error' => 'This event type is not assigned to this subgenre'], 404);
    }

    /**
     * DELETE /api/admin/subgenre-event-type/remove
     * Retire un event type d'un sous-genre
     */
    #[Route('/remove', name: 'admin_subgenre_event_type_remove', methods: ['DELETE'])]
    public function remove(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['subgenre_id']) || empty($data['event_type_id'])) {
            return $this->json(['error' => 'subgenre_id and event_type_id are required'], 400);
        }

        $subgenre = $this->subgenreRepository->find($data['subgenre_id']);
        if (!$subgenre) {
            return $this->json(['error' => 'Subgenre not found'], 404);
        }

        $eventType = $this->eventTypeRepository->find($data['event_type_id']);
        if (!$eventType) {
            return $this->json(['error' => 'EventType not found'], 404);
        }

        // Trouver et supprimer la relation
        foreach ($subgenre->getSubgenreEventTypes() as $subgenreEventType) {
            if ($subgenreEventType->getEventType() === $eventType) {
                $this->entityManager->remove($subgenreEventType);
                $this->entityManager->flush();
                return $this->json(['success' => true]);
            }
        }

        return $this->json(['error' => 'This event type is not assigned to this subgenre'], 404);
    }
}
