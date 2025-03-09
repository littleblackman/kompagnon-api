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

class MetadataController extends AbstractController
{

    #[Route('/api/metadata', name: 'get_metadata', methods: ['GET'])]
    public function getMetadata(CriteriaRepository $criteriaRepository, PersonnageRepository $personnageRepository, StatusRepository $statusRepository): JsonResponse
    {
       return $this->json(
           [
               'criterias' => $criteriaRepository->findAll(),
               'personnages' => $personnageRepository->findAll(),
               'status' => $statusRepository->findAll(),
           ],
           200,
           [],
           ['groups' => 'metadata_read']
       );
    }
}
