<?php

namespace App\Controller;

use App\Repository\CriteriaRepository;
use App\Repository\StatusRepository;
use App\Repository\TypeRepository;
use App\Repository\NarrativeArcRepository;
use App\Repository\DramaticFunctionRepository;
use App\Repository\GenreRepository;
use App\Repository\SubgenreRepository;
use App\Repository\EventRepository;
use App\Repository\NarrativeStructureRepository;
use App\Provider\ActantialSchemaProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\CacheInterface;

class MetadataController extends AbstractController
{

    #[Route('/api/metadata', name: 'get_metadata', methods: ['GET'])]
    public function getMetadata(
        CriteriaRepository $criteriaRepository,
        StatusRepository $statusRepository,
        TypeRepository $typeRepository,
        NarrativeArcRepository $narrativeArcRepository,
        DramaticFunctionRepository $dramaticFunctionRepository,
        GenreRepository $genreRepository,
        SubgenreRepository $subgenreRepository,
        EventRepository $eventRepository,
        NarrativeStructureRepository $narrativeStructureRepository,
        ActantialSchemaProvider $actantialSchemaProvider,
        CacheInterface $cache
    ): JsonResponse
    {

        $criterias = $cache->get('criterias', function() use ($criteriaRepository) {
            return $criteriaRepository->findAll();
        });
        $status = $cache->get('status', function() use ($statusRepository) {
            return $statusRepository->findAll();
        });
        $types = $cache->get('types', function() use ($typeRepository) {
            return $typeRepository->findAll();
        });
        $narrativeArcs = $cache->get('narrative_arcs', function() use ($narrativeArcRepository) {
            return $narrativeArcRepository->findAll();
        });
        $dramaticFunctions = $cache->get('dramatic_functions', function() use ($dramaticFunctionRepository) {
            return $dramaticFunctionRepository->findAll();
        });

        // Nouveaux: Workflow data - SANS CACHE pour debug
        $allGenres = $genreRepository->findAll();
        // Forcer le chargement des subgenres pour chaque genre
        foreach ($allGenres as $genre) {
            $genre->getSubgenres()->count(); // Force lazy loading
        }
        $genres = $allGenres;
        $subgenres = $cache->get('subgenres', function() use ($subgenreRepository) {
            return $subgenreRepository->findAll();
        });
        $events = $cache->get('events', function() use ($eventRepository) {
            return $eventRepository->findAll();
        });

        // Créer un mapping subgenre_id => event_ids pour le frontend
        $subgenreEvents = [];
        foreach ($allGenres as $genre) {
            foreach ($genre->getSubgenres() as $subgenre) {
                $eventIds = [];
                foreach ($subgenre->getEvents() as $event) {
                    $eventIds[] = $event->getId();
                }
                $subgenreEvents[$subgenre->getId()] = $eventIds;
            }
        }

        // Charger les structures pour créer le mapping (sans les sérialiser complètement)
        $allNarrativeStructures = $narrativeStructureRepository->findAll();

        // DEBUG
        error_log('🔍 DEBUG: Nombre de structures: ' . count($allNarrativeStructures));

        // Créer un mapping structure_id => [{eventId, position, isOptional}]
        $structureEvents = [];
        $narrativeStructuresSimple = [];
        foreach ($allNarrativeStructures as $structure) {
            // Créer le mapping des events
            $structureEventsList = [];
            foreach ($structure->getNarrativeStructureEvents() as $nse) {
                $structureEventsList[] = [
                    'eventId' => $nse->getEvent()->getId(),
                    'position' => $nse->getPosition(),
                    'isOptional' => $nse->isOptional(),
                ];
            }
            // Trier par position
            usort($structureEventsList, fn($a, $b) => $a['position'] <=> $b['position']);
            $structureEvents[$structure->getId()] = $structureEventsList;

            // DEBUG pour les 2 premières structures
            if ($structure->getId() <= 2) {
                error_log(sprintf('🔍 Structure #%d "%s": %d events',
                    $structure->getId(),
                    $structure->getName(),
                    count($structureEventsList)
                ));
            }

            // Créer une version simplifiée de la structure (sans les relations)
            $narrativeStructuresSimple[] = [
                'id' => $structure->getId(),
                'name' => $structure->getName(),
                'description' => $structure->getDescription(),
                'totalBeats' => $structure->getTotalBeats(),
            ];
        }
        $narrativeStructures = $narrativeStructuresSimple;

        // DEBUG final
        error_log('🔍 DEBUG: structureEvents[1] count: ' . count($structureEvents[1] ?? []));

        // Récupérer le schéma actantiel via le provider
        $actantialSchema = iterator_to_array($actantialSchemaProvider->provide(new \ApiPlatform\Metadata\GetCollection(), [], []));

       return $this->json(
           [
               'criterias' => $criterias,
               'status' =>$status,
               'types' => $types,
               'narrativeArcs' => $narrativeArcs,
               'dramaticFunctions' => $dramaticFunctions,
               'actantialSchema' => $actantialSchema,
               'genres' => $genres,
               'subgenres' => $subgenres,
               'events' => $events,
               'narrativeStructures' => $narrativeStructures,
               'subgenreEvents' => $subgenreEvents, // Mapping subgenreId => [eventIds]
               'structureEvents' => $structureEvents, // Mapping structureId => [{eventId, position, isOptional}]
           ],
           200,
           [],
           ['groups' => ['metadata_read', 'narrative_arc:read', 'dramatic_function:read', 'genre:read', 'subgenre:read', 'event:read', 'narrative_structure:read']]
       );
    }
}
