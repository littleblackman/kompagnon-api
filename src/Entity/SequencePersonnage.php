<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[ORM\Entity]
class SequencePersonnage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['sequence:read', 'part:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Sequence::class, inversedBy: 'sequencePersonnages')]
    #[ORM\JoinColumn(nullable: false)]
    #[MaxDepth(1)]
    private Sequence $sequence;

    #[ORM\ManyToOne(targetEntity: Personnage::class, inversedBy: 'sequencePersonnages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['sequence:read', 'part:read'])]
    #[MaxDepth(1)]
    private Personnage $personnage;


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

    public function getPersonnage(): Personnage
    {
        return $this->personnage;
    }

    public function setPersonnage(Personnage $personnage): self
    {
        $this->personnage = $personnage;
        return $this;
    }
}
