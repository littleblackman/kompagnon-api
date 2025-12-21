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

        // Structures narratives recommandées
        $narrativeStructures = array_map(function($sns) {
            $structure = $sns->getNarrativeStructure();

            // Récupérer les events de cette structure, triés par position
            $events = array_map(function($nse) {
                $event = $nse->getEvent();
                return [
                    'id' => $event->getId(),
                    'name' => $event->getName(),
                    'description' => $event->getDescription(),
                    'position' => $nse->getPosition(),
                    'isOptional' => $nse->isOptional(),
                ];
            }, $structure->getNarrativeStructureEvents()->toArray());

            // Trier les events par position
            usort($events, fn($a, $b) => $a['position'] <=> $b['position']);

            return [
                'id' => $structure->getId(),
                'name' => $structure->getName(),
                'description' => $structure->getDescription(),
                'totalBeats' => $structure->getTotalBeats(),
                'recommendedPercentage' => $sns->getRecommendedPercentage(),
                'isDefault' => $sns->isDefault(),
                'events' => $events,
            ];
        }, $subgenre->getSubgenreNarrativeStructures()->toArray());

        // Trier par pourcentage décroissant
        usort($narrativeStructures, fn($a, $b) => $b['recommendedPercentage'] <=> $a['recommendedPercentage']);

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

        return $this->json([
            'id' => $subgenre->getId(),
            'name' => $subgenre->getName(),
            'description' => $subgenre->getDescription(),
            'genre' => [
                'id' => $subgenre->getGenre()->getId(),
                'name' => $subgenre->getGenre()->getName(),
            ],
            'narrativeStructures' => $narrativeStructures,
            'dramaticFunctions' => $dramaticFunctions,
        ]);
    }
}
