<?php

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Character
{

    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $firstName;

    #[ORM\Column(type: 'string', length: 255)]
    private string $lastName;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $background = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $age = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $origin = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'characters')]
    #[ORM\JoinColumn(nullable: false)]
    private Project $project;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $images = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $level = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $analysis = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $strength = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $weakness = null;

    #[ORM\OneToMany(targetEntity: SequenceCharacter::class, mappedBy: 'character', cascade: ['persist', 'remove'])]
    private Collection $sequenceCharacters;

    public function __construct()
    {
        $this->sequenceCharacters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getBackground(): ?string
    {
        return $this->background;
    }

    public function setBackground(?string $background): self
    {
        $this->background = $background;
        return $this;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    public function getSequenceCharacters(): Collection
    {
        return $this->sequenceCharacters;
    }

    public function addSequenceCharacter(SequenceCharacter $sequenceCharacter): self
    {
        if (!$this->sequenceCharacters->contains($sequenceCharacter)) {
            $this->sequenceCharacters->add($sequenceCharacter);
            $sequenceCharacter->setCharacter($this);
        }
        return $this;
    }

    public function removeSequenceCharacter(SequenceCharacter $sequenceCharacter): self
    {
        if ($this->sequenceCharacters->removeElement($sequenceCharacter)) {
            if ($sequenceCharacter->getCharacter() === $this) {
                $sequenceCharacter->setCharacter(null);
            }
        }
        return $this;
    }


}
