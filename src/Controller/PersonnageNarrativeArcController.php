<?php

namespace App\Controller;

use App\Entity\PersonnageNarrativeArc;
use App\Repository\PersonnageRepository;
use App\Repository\NarrativeArcRepository;
use App\Repository\SequenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PersonnageNarrativeArcController extends AbstractController
{
    #[Route('/api/personnage/{personnageId}/narrative-arc/add', name: 'api_personnage_narrative_arc_add', methods: ['POST'])]
    public function addNarrativeArc(
        int $personnageId,
        Request $request,
        PersonnageRepository $personnageRepository,
        NarrativeArcRepository $narrativeArcRepository,
        SequenceRepository $sequenceRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $personnage = $personnageRepository->find($personnageId);
        if (!$personnage) {
            return $this->json(['error' => 'Personnage non trouvé'], 404);
        }

        if (empty($data['narrativeArcId'])) {
            return $this->json(['error' => 'Arc narratif requis'], 400);
        }

        $narrativeArc = $narrativeArcRepository->find($data['narrativeArcId']);
        if (!$narrativeArc) {
            return $this->json(['error' => 'Arc narratif non trouvé'], 404);
        }

        $pna = new PersonnageNarrativeArc();
        $pna->setPersonnage($personnage);
        $pna->setNarrativeArc($narrativeArc);
        $pna->setWeight($data['weight'] ?? 50);
        $pna->setSteps($data['steps'] ?? []);
        $pna->setComment($data['comment'] ?? null);

        if (!empty($data['fromSequenceId'])) {
            $fromSequence = $sequenceRepository->find($data['fromSequenceId']);
            if ($fromSequence) {
                $pna->setFromSequence($fromSequence);
            }
        }

        if (!empty($data['toSequenceId'])) {
            $toSequence = $sequenceRepository->find($data['toSequenceId']);
            if ($toSequence) {
                $pna->setToSequence($toSequence);
            }
        }

        $em->persist($pna);
        $em->flush();

        return $this->json(['message' => 'Arc narratif ajouté avec succès', 'id' => $pna->getId()], 201);
    }

    #[Route('/api/personnage/{personnageId}/narrative-arc/{arcId}/update', name: 'api_personnage_narrative_arc_update', methods: ['POST'])]
    public function updateNarrativeArc(
        int $personnageId,
        int $arcId,
        Request $request,
        SequenceRepository $sequenceRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $pna = $em->getRepository(PersonnageNarrativeArc::class)->find($arcId);
        if (!$pna || $pna->getPersonnage()->getId() !== $personnageId) {
            return $this->json(['error' => 'Arc narratif non trouvé'], 404);
        }

        $pna->setWeight($data['weight'] ?? $pna->getWeight());
        $pna->setSteps($data['steps'] ?? []);
        $pna->setComment($data['comment'] ?? null);

        // Update fromSequence
        if (isset($data['fromSequenceId'])) {
            if ($data['fromSequenceId']) {
                $fromSequence = $sequenceRepository->find($data['fromSequenceId']);
                if ($fromSequence) {
                    $pna->setFromSequence($fromSequence);
                }
            } else {
                $pna->setFromSequence(null);
            }
        }

        // Update toSequence
        if (isset($data['toSequenceId'])) {
            if ($data['toSequenceId']) {
                $toSequence = $sequenceRepository->find($data['toSequenceId']);
                if ($toSequence) {
                    $pna->setToSequence($toSequence);
                }
            } else {
                $pna->setToSequence(null);
            }
        }

        $em->flush();

        return $this->json(['message' => 'Arc narratif mis à jour avec succès'], 200);
    }

    #[Route('/api/personnage/{personnageId}/narrative-arc/{arcId}/delete', name: 'api_personnage_narrative_arc_delete', methods: ['DELETE'])]
    public function deleteNarrativeArc(
        int $personnageId,
        int $arcId,
        EntityManagerInterface $em
    ): JsonResponse {
        $pna = $em->getRepository(PersonnageNarrativeArc::class)->find($arcId);
        if (!$pna || $pna->getPersonnage()->getId() !== $personnageId) {
            return $this->json(['error' => 'Arc narratif non trouvé'], 404);
        }

        $em->remove($pna);
        $em->flush();

        return $this->json(['message' => 'Arc narratif supprimé avec succès'], 200);
    }
}
