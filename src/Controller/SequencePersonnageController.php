<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\SequencePersonnageRepository;
use App\Repository\SequenceRepository;
use App\Repository\PersonnageRepository;
use App\Entity\SequencePersonnage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SequencePersonnageController extends AbstractController
{

    public function __construct(
        private SequencePersonnageRepository $sequencePersonnageRepository, 
        private SequenceRepository $sequenceRepository,
        private PersonnageRepository $personnageRepository,
        private EntityManagerInterface $entityManager
    ) {}

   
    #[Route('/api/sequence/personnage/remove/{sequenceId}/{personnageId}', name: 'api_sequence_personnage', methods: ['DELETE'])]
    public function removeSequencePersonnage( int $sequenceId, int $personnageId ):JsonResponse
    {

        $link = $this->sequencePersonnageRepository->findOneBy([
            'sequence' => $sequenceId,
            'personnage' => $personnageId
        ]);

        if (!$link) {
            return $this->json(['error' => 'Link not found'], 404);
        }
        $this->entityManager->remove($link);
        $this->entityManager->flush();


        return $this->json(['success' => true], 200, []);
    }

    #[Route('/api/sequence/personnage/add', name: 'api_sequence_personnage_add', methods: ['POST'])]
    public function addSequencePersonnage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['sequenceId']) || !isset($data['personnageId'])) {
            return $this->json(['error' => 'Missing sequenceId or personnageId'], 400);
        }

        $sequenceId = $data['sequenceId'];
        $personnageId = $data['personnageId'];

        // Vérifier si la relation existe déjà
        $existingLink = $this->sequencePersonnageRepository->findOneBy([
            'sequence' => $sequenceId,
            'personnage' => $personnageId
        ]);

        if ($existingLink) {
            return $this->json(['message' => 'Link already exists'], 200);
        }

        // Récupérer les entités
        $sequence = $this->sequenceRepository->find($sequenceId);
        $personnage = $this->personnageRepository->find($personnageId);

        if (!$sequence || !$personnage) {
            return $this->json(['error' => 'Sequence or Personnage not found'], 404);
        }

        // Créer la nouvelle relation
        $sequencePersonnage = new SequencePersonnage();
        $sequencePersonnage->setSequence($sequence);
        $sequencePersonnage->setPersonnage($personnage);

        $this->entityManager->persist($sequencePersonnage);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'id' => $sequencePersonnage->getId()], 201);
    }
}
