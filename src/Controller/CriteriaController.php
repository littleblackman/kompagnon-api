<?php

namespace App\Controller;

use App\Service\SequenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CriteriaController extends AbstractController
{
    #[Route('/api/criteria/update', name: 'api_criteria_update', methods: ['POST'])]
    public function createOrUpdateCriteria(SequenceService $sequenceService, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $sequenceService->updateCriteria($data);
        
        return $this->json(['success' => true], 200);
    }
}
