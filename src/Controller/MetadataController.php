<?php

namespace App\Controller;

use App\Entity\Status;
use App\Repository\CriteriaRepository;
use App\Repository\PersonnageRepository;
use App\Repository\StatusRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\ProjectService;
use Symfony\Contracts\Cache\CacheInterface;
class MetadataController extends AbstractController
{

    #[Route('/api/metadata', name: 'get_metadata', methods: ['GET'])]
    public function getMetadata(
        CriteriaRepository $criteriaRepository,
        PersonnageRepository $personnageRepository,
        StatusRepository $statusRepository,
        CacheInterface $cache
    ): JsonResponse
    {

        $criterias = $cache->get('criterias', function() use ($criteriaRepository) {
            return $criteriaRepository->findAll();
        });
        $status = $cache->get('status', function() use ($statusRepository, $criteriaRepository) {
            return $statusRepository->findAll();
        });

       return $this->json(
           [
               'criterias' => $criterias,
               'status' =>$status,
               'personnages' => $personnageRepository->findAll(),
           ],
           200,
           [],
           ['groups' => 'metadata_read']
       );
    }
}
