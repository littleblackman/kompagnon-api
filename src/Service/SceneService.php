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

        $isNew = !isset($data['id']);

        if (!$isNew) {
            // UPDATE - Mise à jour d'une scène existante
            $scene = $this->sceneRepository->find($data['id']);
            if (!$scene) {
                throw new \Exception('Scene non trouvée');
            }
        } else {
            // CREATE - Création d'une nouvelle scène
            $scene = new Scene();
            $sequence = $this->sequenceRepository->find($data['sequence_id'] ?? null);
            if (!$sequence) {
                throw new \Exception('Séquence non trouvée');
            }
            $status = $this->statusRepository->find(6);
            $scene->setSequence($sequence);
            $scene->setStatus($status);
        }

        // La position n'est calculée qu'à la création : une édition de contenu
        // (ou un autosave) ne doit jamais réordonner la séquence.
        // Le réordonnancement passe par /scene/order, le déplacement par /scene/move.
        if ($isNew) {
            $afterScene = isset($data['afterSceneId'])
                ? $this->sceneRepository->find($data['afterSceneId'])
                : null;

            // Pas de scène de référence : placer au début de la séquence
            $position = $afterScene ? $afterScene->getPosition() + 1 : 1;

            $this->shiftScenes($scene->getSequence(), $position);
            $scene->setPosition($position);
        }

        // Seuls les champs présents dans le payload sont écrits, pour qu'une
        // sauvegarde partielle (ex. la note seule) n'efface pas le reste.
        if (array_key_exists('name', $data))        $scene->setName($data['name']);
        if (array_key_exists('description', $data)) $scene->setDescription($data['description']);
        if (array_key_exists('content', $data))     $scene->setContent($data['content']);

        $em->persist($scene);
        $em->flush();

        return $scene;
    }

    /**
     * Décale d'un cran toutes les scènes d'une séquence à partir d'une position.
     */
    private function shiftScenes(Sequence $sequence, int $startPosition): void
    {
        $scenes = $this->sceneRepository->findBy(
            ['sequence' => $sequence],
            ['position' => 'DESC'] // on traite d'abord les positions les plus élevées
        );

        foreach ($scenes as $scene) {
            if ($scene->getPosition() >= $startPosition) {
                $scene->setPosition($scene->getPosition() + 1);
                $this->entityManager->persist($scene);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Déplace une scène vers une autre séquence (ou la repositionne dans la sienne).
     *
     * Endpoint dédié plutôt qu'une surcharge de createOrUpdate : toucher deux
     * conteneurs est une autre sémantique, et on évite de fragiliser le chemin
     * de sauvegarde le plus emprunté.
     *
     * @return array{source: Sequence, target: Sequence}
     */
    public function moveToSequence(int $sceneId, int $targetSequenceId, ?int $afterSceneId = null): array
    {
        $scene = $this->sceneRepository->find($sceneId);
        if (!$scene) {
            throw new \Exception('Scène non trouvée');
        }

        $target = $this->sequenceRepository->find($targetSequenceId);
        if (!$target) {
            throw new \Exception('Séquence cible non trouvée');
        }

        if ($afterSceneId === $sceneId) {
            throw new \Exception('Une scène ne peut pas être insérée après elle-même');
        }

        $source = $scene->getSequence();
        $sourcePosition = $scene->getPosition();

        // 1. Mettre la scène hors jeu (position 0) pour qu'elle n'interfère ni
        //    avec le compactage de la source ni avec le décalage de la cible,
        //    y compris quand les deux séquences sont la même.
        $scene->setPosition(0);
        $this->entityManager->persist($scene);
        $this->entityManager->flush();

        // 2. Combler le trou laissé dans la séquence d'origine
        $sourceScenes = $this->sceneRepository->findBy(['sequence' => $source], ['position' => 'ASC']);
        foreach ($sourceScenes as $other) {
            if ($other->getId() !== $sceneId && $other->getPosition() > $sourcePosition) {
                $other->setPosition($other->getPosition() - 1);
                $this->entityManager->persist($other);
            }
        }
        $this->entityManager->flush();

        // 3. Position dans la cible, calculée après compactage donc à jour
        $afterScene = $afterSceneId ? $this->sceneRepository->find($afterSceneId) : null;
        $position = ($afterScene && $afterScene->getSequence()->getId() === $target->getId())
            ? $afterScene->getPosition() + 1
            : 1;

        // 4. Décaler la cible puis poser la scène
        $this->shiftScenes($target, $position);
        $scene->setSequence($target);
        $scene->setPosition($position);
        $this->entityManager->persist($scene);
        $this->entityManager->flush();

        return ['source' => $source, 'target' => $target];
    }

    /**
     * Retourne les positions courantes des scènes d'une séquence, triées.
     * Permet au front de se resynchroniser sans recharger tout le projet.
     *
     * @return array<int, array{id: int, position: int}>
     */
    public function getPositions(Sequence $sequence): array
    {
        $scenes = $this->sceneRepository->findBy(['sequence' => $sequence], ['position' => 'ASC']);

        return array_map(
            fn (Scene $scene) => ['id' => $scene->getId(), 'position' => $scene->getPosition()],
            $scenes
        );
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
     * Supprime une scène et renumérote les scènes restantes de la séquence.
     */
    public function delete(int $id): Sequence
    {
        $scene = $this->sceneRepository->find($id);
        if (!$scene) {
            throw new \Exception('Scene non trouvée');
        }

        $sequence = $scene->getSequence();
        $position = $scene->getPosition();

        $this->entityManager->remove($scene);
        $this->entityManager->flush();

        // Combler le trou laissé par la scène supprimée
        $scenes = $this->sceneRepository->findBy(
            ['sequence' => $sequence],
            ['position' => 'ASC']
        );

        foreach ($scenes as $remaining) {
            if ($remaining->getPosition() > $position) {
                $remaining->setPosition($remaining->getPosition() - 1);
                $this->entityManager->persist($remaining);
            }
        }
        $this->entityManager->flush();

        return $sequence;
    }
}
