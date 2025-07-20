<?php

namespace App\Controller;

use App\Repository\CriteriaRepository;
use App\Repository\StatusRepository;
use App\Repository\TypeRepository;
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

       return $this->json(
           [
               'criterias' => $criterias,
               'status' =>$status,
               'types' => $types,
           ],
           200,
           [],
           ['groups' => 'metadata_read']
       );
    }
}
