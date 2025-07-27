<?php

namespace App\Controller;

use App\Repository\CriteriaRepository;
use App\Repository\StatusRepository;
use App\Repository\TypeRepository;
use App\Repository\NarrativeArcRepository;
use App\Repository\DramaticFunctionRepository;
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
           ],
           200,
           [],
           ['groups' => ['metadata_read', 'narrative_arc:read', 'dramatic_function:read']]
       );
    }
}
