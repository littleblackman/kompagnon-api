<?php

namespace App\Controller;

use App\Service\PartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\PartRepository;

class PartController extends AbstractController
{
    #[Route('/api/part/update', name: 'api_part_update', methods: ['PUT', 'POST'])]
    public function updatePart(Request $request,  PartService $partService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (empty($data['name'])) {
            return $this->json(['error' => 'Le nom est obligatoire'], 400);
        }
        $result = $partService->createOrUpdate($data);
        return $this->json($result);
    }

    #[Route('/api/part/delete/{id}', name: 'api_part_delete', methods: ['DELETE'])]
    public function deletePart(Request $request, PartRepository $partRepository): JsonResponse
    {
        $part = $partRepository->find($request->get('id'));
        if (!$part) {
            return $this->json(['error' => 'La partie n\'existe pas'], 404);
        }
        $em = $partRepository->getEntityManager();
        $em->remove($part);
        $em->flush();
        return $this->json(['message' => 'La partie a été supprimée'], 200);
    }
}
