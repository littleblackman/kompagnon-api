<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\PartRepository;

class PartController extends AbstractController
{
    #[Route('/api/part/update', name: 'api_part_update', methods: ['PUT'])]
    public function updatePart(Request $request,  PartRepository $partRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (empty($data['name'])) {
            return $this->json(['error' => 'Le nom est obligatoire'], 400);
        }
        $part = $partRepository->createOrUpdate($data);
        return $this->json($part, 200, [], ['groups' => 'part:read']);
    }
}
