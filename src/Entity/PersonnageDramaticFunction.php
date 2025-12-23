<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'personnage_dramatic_function')]
class PersonnageDramaticFunction
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    #[Groups(["personnage:read", "project:read"])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Personnage::class, inversedBy: 'personnageDramaticFunctions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Personnage $personnage;

    #[ORM\ManyToOne(targetEntity: DramaticFunction::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(["personnage:read", "project:read"])]
    private DramaticFunction $dramaticFunction;

    #[ORM\ManyToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sequence $fromSequence = null;

    #[ORM\ManyToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sequence $toSequence = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(["personnage:read", "project:read"])]
    private int $weight = 50;

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

    public function getDramaticFunction(): DramaticFunction
    {
        return $this->dramaticFunction;
    }

    public function setDramaticFunction(DramaticFunction $dramaticFunction): self
    {
        $this->dramaticFunction = $dramaticFunction;
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