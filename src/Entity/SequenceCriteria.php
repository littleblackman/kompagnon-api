<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class SequenceCriteria
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Sequence::class, inversedBy: 'sequenceCriterias')]
    #[ORM\JoinColumn(nullable: false)]
    private Sequence $sequence;

    #[ORM\ManyToOne(targetEntity: Criteria::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Criteria $criteria;

    #[ORM\Column(type: 'integer')]
    private int $rating;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSequence(): Sequence
    {
        return $this->sequence;
    }

    public function setSequence(Sequence $sequence): self
    {
        $this->sequence = $sequence;
        return $this;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function setCriteria(Criteria $criteria): self
    {
        $this->criteria = $criteria;
        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): self
    {
        $this->rating = $rating;
        return $this;
    }
}
