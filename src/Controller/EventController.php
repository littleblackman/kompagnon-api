<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Subgenre;
use App\Entity\NarrativeStructure;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/event')]
class EventController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * GET /api/event/subgenre/{subgenreId}
     * Retourne tous les events liés à un subgenre
     */
    #[Route('/subgenre/{subgenreId}', name: 'event_by_subgenre', methods: ['GET'])]
    public function getBySubgenre(int $subgenreId): JsonResponse
    {
        $subgenre = $this->entityManager->getRepository(Subgenre::class)->find($subgenreId);

        if (!$subgenre) {
            return $this->json(['error' => 'Subgenre not found'], 404);
        }

        $events = array_map(function(Event $event) {
            return [
                'id' => $event->getId(),
                'name' => $event->getName(),
                'description' => $event->getDescription(),
                'eventType' => $event->getEventType() ? [
                    'id' => $event->getEventType()->getId(),
                    'name' => $event->getEventType()->getName(),
                    'description' => $event->getEventType()->getDescription(),
                ] : null,
            ];
        }, $subgenre->getEvents()->toArray());

        return $this->json([
            'subgenre' => [
                'id' => $subgenre->getId(),
                'name' => $subgenre->getName(),
            ],
            'events' => $events,
        ]);
    }

    /**
     * GET /api/event/structure/{structureId}?subgenreId={subgenreId}
     * Retourne les events d'une structure narrative, filtrés par subgenre
     * et ordonnés selon la position dans la structure
     */
    #[Route('/structure/{structureId}', name: 'event_by_structure', methods: ['GET'])]
    public function getByStructure(int $structureId, Request $request): JsonResponse
    {
        $structure = $this->entityManager->getRepository(NarrativeStructure::class)->find($structureId);

        if (!$structure) {
            return $this->json(['error' => 'Narrative structure not found'], 404);
        }

        // Récupérer tous les events de la structure avec positions
        $structureEvents = $structure->getNarrativeStructureEvents()->toArray();

        // Trier par position
        usort($structureEvents, fn($a, $b) => $a->getPosition() <=> $b->getPosition());

        // Si subgenreId fourni, filtrer les events qui appartiennent au subgenre
        $subgenreId = $request->query->get('subgenreId');
        $subgenreEventIds = [];

        if ($subgenreId) {
            $subgenre = $this->entityManager->getRepository(Subgenre::class)->find($subgenreId);
            if ($subgenre) {
                $subgenreEventIds = array_map(
                    fn($event) => $event->getId(),
                    $subgenre->getEvents()->toArray()
                );
            }
        }

        $events = array_map(function($nse) use ($subgenreEventIds) {
            $event = $nse->getEvent();

            // Si on filtre par subgenre, ne garder que les events du subgenre
            if (!empty($subgenreEventIds) && !in_array($event->getId(), $subgenreEventIds)) {
                return null;
            }

            return [
                'id' => $event->getId(),
                'name' => $event->getName(),
                'description' => $event->getDescription(),
                'position' => $nse->getPosition(),
                'isOptional' => $nse->isOptional(),
                'eventType' => $event->getEventType() ? [
                    'id' => $event->getEventType()->getId(),
                    'name' => $event->getEventType()->getName(),
                ] : null,
            ];
        }, $structureEvents);

        // Retirer les null (events filtrés)
        $events = array_values(array_filter($events));

        return $this->json([
            'structure' => [
                'id' => $structure->getId(),
                'name' => $structure->getName(),
                'description' => $structure->getDescription(),
                'totalBeats' => $structure->getTotalBeats(),
            ],
            'events' => $events,
        ]);
    }
}
