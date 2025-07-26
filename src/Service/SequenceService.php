<?php

namespace App\Service;

use App\Entity\Sequence;
use App\Repository\PartRepository;
use App\Repository\SequenceRepository;
use App\Repository\StatusRepository;
use App\Repository\CriteriaRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Part;
use App\Entity\SequenceCriteria;


class SequenceService
{

    private SequenceRepository $sequenceRepository;
    private PartRepository $partRepository;
    private EntityManagerInterface $entityManager;
    private StatusRepository $statusRepository;
    private CriteriaRepository $criteriaRepository;

    public function __construct( SequenceRepository $sequenceRepository, PartRepository $partRepository, EntityManagerInterface $entityManager, StatusRepository $statusRepository, CriteriaRepository $criteriaRepository )
    {
        $this->sequenceRepository = $sequenceRepository;
        $this->partRepository = $partRepository;
        $this->entityManager = $entityManager;
        $this->statusRepository = $statusRepository;
        $this->criteriaRepository = $criteriaRepository;
    }

    /**
     * @param array|null $data
     * @return ?Sequence
     */
    public function createOrUpdate(?array $data): ?Sequence
    {
        $em = $this->entityManager;

        if(isset($data['id']))  {
            $sequence = $this->sequenceRepository->find($data['id']);
        } else {
            $sequence = new Sequence();
            $part = $this->partRepository->find($data['part_id']);
            $status = $this->statusRepository->find(6);
            $sequence->setPart($part);
            $sequence->setStatus($status);
        }

        // Gestion de la position
        if (isset($data['afterSequenceId'])) {
            $afterSequence = $this->sequenceRepository->find($data['afterSequenceId']);
            if ($afterSequence) {
                // Décaler d'abord toutes les séquences suivantes
                $this->shiftSequences($afterSequence->getPart(), $afterSequence->getPosition() + 1);
                // Puis positionner la nouvelle séquence
                $sequence->setPosition($afterSequence->getPosition() + 1);
            }
        } else {
            // Si pas de séquence de référence, placer au début
            $this->shiftSequences($sequence->getPart(), 1);
            $sequence->setPosition(1);
        }
        
        $sequence->setName($data['name']);
        $sequence->setDescription($data['description']);
        $sequence->setIntention($data['intention'] ?? null);
        $sequence->setAestheticIdea($data['aesthetic_idea'] ?? null);
        $sequence->setInformation($data['information'] ?? true);
        
        $em->persist($sequence);
        
        // Flush final pour s'assurer que tous les changements sont persistés
        $em->flush();

        return $sequence;
    }

    /**
     * Décale toutes les séquences à partir d'une position donnée
     */
    private function shiftSequences(Part $part, int $startPosition): void
    {
        $sequences = $this->sequenceRepository->findBy(
            ['part' => $part],
            ['position' => 'DESC'] // Important : on traite d'abord les positions les plus élevées
        );

        foreach ($sequences as $seq) {
            if ($seq->getPosition() >= $startPosition) {
                $seq->setPosition($seq->getPosition() + 1);
                $this->entityManager->persist($seq);
            }
        }
        // Flush pour s'assurer que les changements sont persistés
        $this->entityManager->flush();
    }

    /**
     * Supprime une séquence et réorganise les positions
     */
    public function delete(int $id): void
    {
        $sequence = $this->sequenceRepository->find($id);
        if (!$sequence) {
            throw new \Exception('Séquence non trouvée');
        }

        $part = $sequence->getPart();
        $position = $sequence->getPosition();

        // Supprimer la séquence
        $this->entityManager->remove($sequence);
        $this->entityManager->flush();

        // Réorganiser les positions des séquences suivantes
        $sequences = $this->sequenceRepository->findBy(
            ['part' => $part],
            ['position' => 'ASC']
        );

        foreach ($sequences as $seq) {
            if ($seq->getPosition() > $position) {
                $seq->setPosition($seq->getPosition() - 1);
                $this->entityManager->persist($seq);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Met à jour l'ordre des séquences (batch update des positions).
     */
    public function updateOrder(array $sequencePositions): void
    {
        foreach ($sequencePositions as $item) {
            if (isset($item['id']) && isset($item['position'])) {
                $sequence = $this->sequenceRepository->find($item['id']);
                if ($sequence) {
                    $sequence->setPosition($item['position']);
                    $this->entityManager->persist($sequence);
                }
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Met à jour ou crée un critère pour une séquence
     * @param array $data contient sequenceId, criteriaId, value
     */
    public function updateCriteria(array $data): void
    {
        $sequence = $this->sequenceRepository->find($data['sequenceId']);
        $criteria = $this->criteriaRepository->find($data['criteriaId']);
        
        if (!$sequence || !$criteria) {
            throw new \Exception('Séquence ou critère non trouvé');
        }

        // Chercher si l'association existe déjà
        $sequenceCriteria = $this->entityManager->getRepository(SequenceCriteria::class)
            ->findOneBy([
                'sequence' => $sequence,
                'criteria' => $criteria
            ]);

        if (!$sequenceCriteria) {
            // Créer nouvelle association
            $sequenceCriteria = new SequenceCriteria();
            $sequenceCriteria->setSequence($sequence);
            $sequenceCriteria->setCriteria($criteria);
        }

        // Mettre à jour la valeur
        $sequenceCriteria->setRating($data['value']);
        
        $this->entityManager->persist($sequenceCriteria);
        $this->entityManager->flush();
    }
}