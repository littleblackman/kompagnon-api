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
            // UPDATE - Mise à jour d'une scène existante
            $scene = $this->sceneRepository->find($data['id']);
        } else {
            // CREATE - Création d'une nouvelle scène
            $scene = new Scene();
            $sequence = $this->sequenceRepository->find($data['sequence_id']);
            $status = $this->statusRepository->find(6);
            $scene->setSequence($sequence);
            $scene->setStatus($status);
        }

        // PLUS DE LOGIQUE DE POSITION - Le frontend gère tout !
        // On sauvegarde simplement les données reçues
        $scene->setName($data['name']);
        
        // Description optionnelle
        if (isset($data['description'])) {
            $scene->setDescription($data['description']);
        }
        
        $scene->setContent($data['content']);
        
        // Position calculée côté frontend
        if (isset($data['position'])) {
            $scene->setPosition($data['position']);
        }

        $em->persist($scene);
        $em->flush();

        return $scene;
    }


    /**
     * Met à jour l'ordre des scènes (batch update des positions).
     */
    public function updateOrder(array $scenePositions): void
    {
        foreach ($scenePositions as $item) {
            if (isset($item['id']) && isset($item['position'])) {
                $scene = $this->sceneRepository->find($item['id']);
                if ($scene) {
                    $scene->setPosition($item['position']);
                    $this->entityManager->persist($scene);
                }
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Supprime une scène (sans réorganiser les positions - géré côté frontend).
     */
    public function delete(int $id): void
    {
        $scene = $this->sceneRepository->find($id);
        if (!$scene) {
            throw new \Exception('Scene non trouvée');
        }

        // Supprimer la scène - Le frontend gère la réorganisation des positions
        $this->entityManager->remove($scene);
        $this->entityManager->flush();
    }
}
