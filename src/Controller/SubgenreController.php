<?php

namespace App\Controller;

use App\Entity\Subgenre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/subgenre')]
class SubgenreController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * GET /api/subgenre/all
     * Retourne tous les subgenres avec leur genre parent
     */
    #[Route('/all', name: 'subgenre_all', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $subgenres = $this->entityManager->getRepository(Subgenre::class)->findAll();

        $data = array_map(function(Subgenre $subgenre) {
            return [
                'id' => $subgenre->getId(),
                'name' => $subgenre->getName(),
                'description' => $subgenre->getDescription(),
                'genre' => [
                    'id' => $subgenre->getGenre()->getId(),
                    'name' => $subgenre->getGenre()->getName(),
                    'description' => $subgenre->getGenre()->getDescription(),
                ],
            ];
        }, $subgenres);

        return $this->json($data);
    }

    /**
     * GET /api/subgenre/{id}
     * Retourne les détails d'un subgenre avec structures narratives et dramatic functions
     */
    #[Route('/{id}', name: 'subgenre_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $subgenre = $this->entityManager->getRepository(Subgenre::class)->find($id);

        if (!$subgenre) {
            return $this->json(['error' => 'Subgenre not found'], 404);
        }

        // Dramatic Functions suggérées
        $dramaticFunctions = array_map(function($sdf) {
            $df = $sdf->getDramaticFunction();
            return [
                'id' => $df->getId(),
                'name' => $df->getName(),
                'description' => $df->getDescription(),
                'characteristics' => $df->getCharacteristics(),
                'tendency' => $df->getTendency(),
                'isEssential' => $sdf->isEssential(),
                'typicalCount' => $sdf->getTypicalCount(),
            ];
        }, $subgenre->getSubgenreDramaticFunctions()->toArray());

        // EventTypes avec leurs Events (triés par position)
        $eventTypes = array_map(function($set) {
            $eventType = $set->getEventType();

            // Récupérer les events directement depuis eventType
            $events = $eventType->getEvents();

            // Formater les events seulement s'ils existent
            $eventsData = [];
            if ($events && count($events) > 0) {
                $eventsData = array_map(function($event) {
                    return [
                        'id' => $event->getId(),
                        'name' => $event->getName(),
                        'description' => $event->getDescription(),
                    ];
                }, $events->toArray());
            }

            return [
                'id' => $eventType->getId(),
                'name' => $eventType->getName(),
                'code' => $eventType->getCode(),
                'description' => $eventType->getDescription(),
                'weight' => $set->getWeight(),
                'isMandatory' => $set->isMandatory(),
                'events' => array_values($eventsData),
            ];
        }, $subgenre->getSubgenreEventTypes()->toArray());

        return $this->json([
            'id' => $subgenre->getId(),
            'name' => $subgenre->getName(),
            'description' => $subgenre->getDescription(),
            'genre' => [
                'id' => $subgenre->getGenre()->getId(),
                'name' => $subgenre->getGenre()->getName(),
            ],
            'eventTypes' => $eventTypes,
            'dramaticFunctions' => $dramaticFunctions,
        ]);
    }
}
