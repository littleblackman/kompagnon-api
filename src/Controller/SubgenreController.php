<?php

namespace App\Controller;

use App\Entity\Subgenre;
use App\Entity\SubgenreEventType;
use App\Entity\NarrativeStructure;
use App\Entity\NarrativePart;
use App\Entity\EventType;
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

            // Récupérer les NarrativeArcs recommandés pour cette fonction dramatique
            $narrativeArcs = array_map(function($arc) {
                return [
                    'id' => $arc->getId(),
                    'name' => $arc->getName(),
                    'description' => $arc->getDescription(),
                    'tendency' => $arc->getTendency(),
                    'steps' => $arc->getSteps(),
                    'variants' => $arc->getVariants(),
                ];
            }, $df->getNarrativeArcs()->toArray());

            return [
                'id' => $df->getId(),
                'name' => $df->getName(),
                'description' => $df->getDescription(),
                'narrativeArcs' => $narrativeArcs,
                'characteristics' => $df->getCharacteristics(),
                'tendency' => $df->getTendency(),
                'isEssential' => $sdf->isEssential(),
                'typicalCount' => $sdf->getTypicalCount(),
            ];
        }, $subgenre->getSubgenreDramaticFunctions()->toArray());

        // EventTypes avec leurs Events (triés par position), groupés par NarrativePart
        $eventTypesByPart = [];

        foreach ($subgenre->getSubgenreEventTypes() as $set) {
            $eventType = $set->getEventType();
            $narrativePart = $eventType->getNarrativePart();

            // Récupérer les events directement depuis eventType
            $events = $eventType->getEvents();

            // Formater et trier les events seulement s'ils existent
            $eventsData = [];
            if ($events && count($events) > 0) {
                $eventsArray = $events->toArray();

                // Trier: d'abord is_optional = 0 (essentiels), puis is_optional = 1 (optionnels), par id
                usort($eventsArray, function($a, $b) {
                    if ($a->isOptional() === $b->isOptional()) {
                        return $a->getId() <=> $b->getId();
                    }
                    return $a->isOptional() <=> $b->isOptional();
                });

                $eventsData = array_map(function($event) {
                    return [
                        'id' => $event->getId(),
                        'name' => $event->getName(),
                        'description' => $event->getDescription(),
                        'isOptional' => $event->isOptional(),
                    ];
                }, $eventsArray);
            }

            $eventTypeData = [
                'id' => $eventType->getId(),
                'name' => $eventType->getName(),
                'code' => $eventType->getCode(),
                'description' => $eventType->getDescription(),
                'weight' => $set->getWeight(),
                'isMandatory' => $set->isMandatory(),
                'events' => array_values($eventsData),
                'narrativePart' => $narrativePart ? [
                    'id' => $narrativePart->getId(),
                    'name' => $narrativePart->getName(),
                    'code' => $narrativePart->getCode(),
                ] : null,
            ];

            // Grouper par NarrativePart code
            if ($narrativePart) {
                $partCode = $narrativePart->getCode();
                if (!isset($eventTypesByPart[$partCode])) {
                    $eventTypesByPart[$partCode] = [
                        'narrativePart' => [
                            'id' => $narrativePart->getId(),
                            'name' => $narrativePart->getName(),
                            'code' => $narrativePart->getCode(),
                            'description' => $narrativePart->getDescription(),
                        ],
                        'eventTypes' => [],
                    ];
                }
                $eventTypesByPart[$partCode]['eventTypes'][] = $eventTypeData;
            }
        }

        // Convertir en tableau indexé et trier par ordre des NarrativeParts
        $narrativePartOrder = ['SETUP', 'DISRUPTION', 'COMMIT', 'ESCALATION', 'TURN', 'CRISIS', 'CLIMAX', 'RESOLUTION'];
        $sortedEventTypesByPart = [];
        foreach ($narrativePartOrder as $partCode) {
            if (isset($eventTypesByPart[$partCode])) {
                $sortedEventTypesByPart[] = $eventTypesByPart[$partCode];
            }
        }

        // Garder aussi l'ancien format pour compatibilité
        $eventTypes = [];
        foreach ($sortedEventTypesByPart as $part) {
            foreach ($part['eventTypes'] as $et) {
                $eventTypes[] = $et;
            }
        }

        // Récupérer les NarrativePart codes utilisés par ce sous-genre
        $subgenreNarrativePartCodes = array_keys($eventTypesByPart);

        // Récupérer toutes les structures narratives
        $allStructures = $this->entityManager->getRepository(NarrativeStructure::class)->findAll();
        $narrativeStructures = [];

        foreach ($allStructures as $structure) {
            $structurePartCodes = $structure->getNarrativePartCodesArray();

            // Calculer le pourcentage de compatibilité
            if (empty($structurePartCodes)) {
                $compatibilityPercentage = 0;
            } else {
                $matchingCodes = array_intersect($structurePartCodes, $subgenreNarrativePartCodes);
                $compatibilityPercentage = round((count($matchingCodes) / count($structurePartCodes)) * 100);
            }

            // Récupérer tous les events pour cette structure, ordonnés selon narrativePartOrder
            $structureEvents = [];
            $eventPosition = 1;

            foreach ($structurePartCodes as $partCode) {
                // Récupérer le NarrativePart
                $narrativePart = $this->entityManager->getRepository(NarrativePart::class)->findOneBy(['code' => $partCode]);

                if ($narrativePart) {
                    // Récupérer les EventTypes de ce NarrativePart qui sont liés au sous-genre
                    $eventTypesForPart = $this->entityManager->getRepository(EventType::class)
                        ->createQueryBuilder('et')
                        ->innerJoin('App\Entity\SubgenreEventType', 'sget', 'WITH', 'sget.eventType = et')
                        ->where('sget.subgenre = :subgenre')
                        ->andWhere('et.narrativePart = :narrativePart')
                        ->setParameter('subgenre', $subgenre)
                        ->setParameter('narrativePart', $narrativePart)
                        ->getQuery()
                        ->getResult();

                    // Pour chaque EventType, récupérer ses Events
                    foreach ($eventTypesForPart as $eventType) {
                        $events = $eventType->getEvents()->toArray();

                        // Trier: essentiels d'abord, puis optionnels
                        usort($events, function($a, $b) {
                            if ($a->isOptional() === $b->isOptional()) {
                                return $a->getId() <=> $b->getId();
                            }
                            return $a->isOptional() <=> $b->isOptional();
                        });

                        foreach ($events as $event) {
                            $structureEvents[] = [
                                'id' => $event->getId(),
                                'name' => $event->getName(),
                                'description' => $event->getDescription(),
                                'isOptional' => $event->isOptional(),
                                'isChecked' => !$event->isOptional(), // Cocher par défaut si obligatoire
                                'position' => $eventPosition++,
                                'narrativePartCode' => $partCode,
                                'narrativePartName' => $narrativePart->getName(),
                            ];
                        }
                    }
                }
            }

            // Créer l'aperçu de la structure avec les sections et event types
            $structurePreview = [];
            if ($structure->getNarrativePartOrder()) {
                foreach ($structure->getNarrativePartOrder() as $sectionName => $partCodes) {
                    $eventTypeNames = [];
                    foreach ($partCodes as $partCode) {
                        $narrativePart = $this->entityManager->getRepository(NarrativePart::class)->findOneBy(['code' => $partCode]);
                        if ($narrativePart) {
                            // Récupérer les EventTypes pour ce NarrativePart
                            $eventTypesForPreview = $this->entityManager->getRepository(EventType::class)
                                ->createQueryBuilder('et')
                                ->innerJoin('App\Entity\SubgenreEventType', 'sget', 'WITH', 'sget.eventType = et')
                                ->where('sget.subgenre = :subgenre')
                                ->andWhere('et.narrativePart = :narrativePart')
                                ->setParameter('subgenre', $subgenre)
                                ->setParameter('narrativePart', $narrativePart)
                                ->getQuery()
                                ->getResult();

                            foreach ($eventTypesForPreview as $et) {
                                $eventTypeNames[] = $et->getName();
                            }
                        }
                    }
                    if (!empty($eventTypeNames)) {
                        $structurePreview[] = [
                            'section' => $sectionName,
                            'eventTypes' => $eventTypeNames,
                        ];
                    }
                }
            }

            $narrativeStructures[] = [
                'id' => $structure->getId(),
                'name' => $structure->getName(),
                'description' => $structure->getDescription(),
                'narrativePartOrder' => $structure->getNarrativePartOrder(),
                'recommendedPercentage' => $compatibilityPercentage,
                'totalBeats' => count($structureEvents),
                'events' => $structureEvents,
                'structurePreview' => $structurePreview,
            ];
        }

        // Trier les structures par pourcentage décroissant
        usort($narrativeStructures, function($a, $b) {
            return $b['recommendedPercentage'] <=> $a['recommendedPercentage'];
        });

        return $this->json([
            'id' => $subgenre->getId(),
            'name' => $subgenre->getName(),
            'description' => $subgenre->getDescription(),
            'genre' => [
                'id' => $subgenre->getGenre()->getId(),
                'name' => $subgenre->getGenre()->getName(),
            ],
            'eventTypes' => $eventTypes, // Format plat pour compatibilité
            'eventTypesByPart' => $sortedEventTypesByPart, // Format groupé par NarrativePart
            'dramaticFunctions' => $dramaticFunctions,
            'narrativeStructures' => $narrativeStructures,
        ]);
    }
}
