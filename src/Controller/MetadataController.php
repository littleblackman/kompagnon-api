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
        $narrativeStructures = $cache->get('narrative_structures', function() use ($narrativeStructureRepository) {
            return $narrativeStructureRepository->findAll();
        });

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
           ],
           200,
           [],
           ['groups' => ['metadata_read', 'narrative_arc:read', 'dramatic_function:read', 'genre:read', 'subgenre:read', 'event:read', 'narrative_structure:read']]
       );
    }
}
