<?php

namespace App\Service;

use App\Entity\Scene;
use App\Entity\Sequence;
use App\Repository\SceneRepository;
use App\Repository\SequenceRepository;
use App\Repository\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;

class SceneService
{
    private SceneRepository $sceneRepository;
    private SequenceRepository $sequenceRepository;
    private EntityManagerInterface $entityManager;
    private StatusRepository $statusRepository;

    public function __construct(SceneRepository $sceneRepository, SequenceRepository $sequenceRepository, EntityManagerInterface $entityManager, StatusRepository $statusRepository)
    {
        $this->sceneRepository = $sceneRepository;
        $this->sequenceRepository = $sequenceRepository;
        $this->entityManager = $entityManager;
        $this->statusRepository = $statusRepository;
    }

    public function createOrUpdate(?array $data)
    {
        $em = $this->entityManager;

        if (isset($data['id'])) {
            $scene = $this->sceneRepository->find($data['id']);
        } else {
            $scene = new Scene();
            $sequence = $this->sequenceRepository->find($data['sequence_id']);
            $status = $this->statusRepository->find(6);
            $scene->setSequence($sequence);
            $scene->setStatus($status);
        }

        // Gestion de la position
        if (isset($data['afterSceneId'])) {
            $afterScene = $this->sceneRepository->find($data['afterSceneId']);
            if ($afterScene) {
                // Décaler d'abord toutes les scènes suivantes
                $this->shiftScenes($afterScene->getSequence(), $afterScene->getPosition() + 1);
                // Puis positionner la nouvelle scène
                $scene->setPosition($afterScene->getPosition() + 1);
            }
        } else {
            // Si pas de scène de référence, placer au début
            $this->shiftScenes($scene->getSequence(), 1);
            $scene->setPosition(1);
        }

        $scene->setName($data['name']);
        $scene->setDescription($data['description']);
        $scene->setContent($data['content']);
        $em->persist($scene);

        // Flush final pour s'assurer que tous les changements sont persistés
        $em->flush();

        return $scene;
    }

    /**
     * Décale toutes les scènes à partir d'une position donnée.
     */
    private function shiftScenes(Sequence $sequence, int $startPosition): void
    {
        $scenes = $this->sceneRepository->findBy(
            ['sequence' => $sequence],
            ['position' => 'DESC']
        );

        foreach ($scenes as $scene) {
            if ($scene->getPosition() >= $startPosition) {
                $scene->setPosition($scene->getPosition() + 1);
                $this->entityManager->persist($scene);
            }
        }
        // Flush pour s'assurer que les changements sont persistés
        $this->entityManager->flush();
    }

    /**
     * Supprime une scène et réorganise les positions.
     */
    public function delete(int $id): void
    {
        $scene = $this->sceneRepository->find($id);
        if (!$scene) {
            throw new \Exception('Scene non trouvée');
        }

        $sequence = $scene->getSequence();
        $position = $scene->getPosition();

        // Supprimer la scène
        $this->entityManager->remove($scene);
        $this->entityManager->flush();

        // Réorganiser les positions des scènes suivantes
        $scenes = $this->sceneRepository->findBy(
            ['sequence' => $sequence],
            ['position' => 'ASC']
        );

        foreach ($scenes as $scene) {
            if ($scene->getPosition() > $position) {
                $scene->setPosition($scene->getPosition() - 1);
                $this->entityManager->persist($scene);
            }
        }
        $this->entityManager->flush();
    }
}
