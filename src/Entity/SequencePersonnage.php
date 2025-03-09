<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class SequencePersonnage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Sequence::class, inversedBy: 'sequencePersonnages')]
    #[ORM\JoinColumn(nullable: false)]
    private Sequence $sequence;

    #[ORM\ManyToOne(targetEntity: Personnage::class, inversedBy: 'sequencePersonnages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["metadata_read"])]
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

    public function getPersonage(): Personnage
    {
        return $this->personnage;
    }

    public function setPersonnage(Personnage $personnage): self
    {
        $this->personnage = $personnage;
        return $this;
    }
}
