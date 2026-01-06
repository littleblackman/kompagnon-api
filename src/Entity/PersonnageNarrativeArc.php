<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'personnage_narrative_arc')]
class PersonnageNarrativeArc
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Personnage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Personnage $personnage;

    #[ORM\ManyToOne(targetEntity: NarrativeArc::class)]
    #[ORM\JoinColumn(nullable: false)]
    private NarrativeArc $narrativeArc;

    #[ORM\ManyToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sequence $fromSequence = null;

    #[ORM\ManyToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sequence $toSequence = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 0, max: 100)]
    private int $weight = 50;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $steps = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPersonnage(): Personnage
    {
        return $this->personnage;
    }

    public function setPersonnage(Personnage $personnage): self
    {
        $this->personnage = $personnage;
        return $this;
    }

    public function getNarrativeArc(): NarrativeArc
    {
        return $this->narrativeArc;
    }

    public function setNarrativeArc(NarrativeArc $narrativeArc): self
    {
        $this->narrativeArc = $narrativeArc;
        return $this;
    }

    public function getFromSequence(): ?Sequence
    {
        return $this->fromSequence;
    }

    public function setFromSequence(?Sequence $fromSequence): self
    {
        $this->fromSequence = $fromSequence;
        return $this;
    }

    public function getToSequence(): ?Sequence
    {
        return $this->toSequence;
    }

    public function setToSequence(?Sequence $toSequence): self
    {
        $this->toSequence = $toSequence;
        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    public function getSteps(): ?array
    {
        return $this->steps;
    }

    public function setSteps(?array $steps): self
    {
        $this->steps = $steps;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }
}