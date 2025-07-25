<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\Timestampable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['part:read']],
    denormalizationContext: ['groups' => ['part:write']]
)]
class Part
{
    use Timestampable;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    #[Groups(['part:read', 'project:read'])] // on veut que Project affiche l’ID
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['part:read', 'part:write', 'project:read'])] // affiché depuis Project
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['part:read', 'part:write', 'project:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['part:read', 'part:write', 'project:read'])]
    private int $position;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'parts')]
    #[Groups(['part:read', 'part:write'])]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: Status::class)]
    #[Groups(['part:read', 'part:write'])]
    private Status $status;

    #[ORM\OneToMany(targetEntity: Sequence::class, mappedBy: 'part', cascade: ['persist', 'remove'])]
    #[Groups(['part:read'])]
    private Collection $sequences;

    public function __construct()
    {
        $this->sequences = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

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

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSequences(): Collection
    {
        return $this->sequences;
    }

    public function addSequence(Sequence $sequence): self
    {
        if (!$this->sequences->contains($sequence)) {
            $this->sequences[] = $sequence;
            $sequence->setPart($this);
        }

        return $this;
    }

    public function removeSequence(Sequence $sequence): self
    {
        if ($this->sequences->removeElement($sequence)) {
            if ($sequence->getPart() === $this) {
                $sequence->setPart(null);
            }
        }

        return $this;
    }
}
