<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class SequenceCharacter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Sequence::class, inversedBy: 'sequenceCharacters')]
    #[ORM\JoinColumn(nullable: false)]
    private Sequence $sequence;

    #[ORM\ManyToOne(targetEntity: Character::class, inversedBy: 'sequenceCharacters')]
    #[ORM\JoinColumn(nullable: false)]
    private Character $character;


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

    public function getCharacter(): Character
    {
        return $this->character;
    }

    public function setCharacter(Character $character): self
    {
        $this->character = $character;
        return $this;
    }
}
