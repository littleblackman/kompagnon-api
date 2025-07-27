<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\Timestampable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['sequence:read', 'part:read']],
    denormalizationContext: ['groups' => ['sequence:write']]
)]
class Sequence
{
    use Timestampable;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    #[Groups(['sequence:read', 'part:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['sequence:read', 'sequence:write', 'part:read'])]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['sequence:read', 'sequence:write', 'part:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['sequence:read', 'sequence:write', 'part:read'])]
    private int $position;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['sequence:read', 'sequence:write', 'part:read'])]
    private ?string $information = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['sequence:read', 'sequence:write', 'part:read'])]
    private ?string $intention = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['sequence:read', 'sequence:write', 'part:read'])]
    private ?string $aesthetic_idea = null;

    #[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'sequences')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['sequence:read', 'sequence:write'])] // pas dans part:read, pour éviter la boucle
    private Part $part;

    #[ORM\ManyToOne(targetEntity: Status::class)]
    #[Groups(['sequence:read', 'sequence:write', 'part:read'])]
    private Status $status;

    #[ORM\OneToMany(targetEntity: Scene::class, mappedBy: 'sequence', cascade: ['persist', 'remove'])]
    #[Groups(['sequence:read'])]
    private Collection $scenes;

    #[ORM\OneToMany(targetEntity: SequenceCriteria::class, mappedBy: 'sequence', cascade: ['persist', 'remove'])]
    #[Groups(['sequence:read', 'part:read'])]
    private Collection $sequenceCriterias;

    #[ORM\OneToMany(targetEntity: SequencePersonnage::class, mappedBy: 'sequence', cascade: ['persist', 'remove'])]
    #[Groups(['sequence:read', 'part:read'])]
    #[MaxDepth(1)]
    private Collection $sequencePersonnages;

    public function __construct()
    {
        $this->scenes = new ArrayCollection();
        $this->sequenceCriterias = new ArrayCollection();
        $this->sequencePersonnages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getPart(): Part
    {
        return $this->part;
    }

    public function setPart(Part $part): void
    {
        $this->part = $part;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): void
    {
        $this->status = $status;
    }

    public function getIntention(): ?string
    {
        return $this->intention;
    }

    public function setIntention(?string $intention): void
    {
        $this->intention = $intention;
    }

    public function getAestheticIdea(): ?string
    {
        return $this->aesthetic_idea;
    }

    public function setAestheticIdea(?string $aesthetic_idea): void
    {
        $this->aesthetic_idea = $aesthetic_idea;
    }

    public function getScenes(): Collection
    {
        return $this->scenes;
    }

    public function getSequenceCriterias(): Collection
    {
        return $this->sequenceCriterias;
    }

    public function getSequencePersonnages(): Collection
    {
        return $this->sequencePersonnages;
    }

    public function getInformation(): ?string
    {
        return $this->information;
    }

    public function setInformation(?string $information): void
    {
        $this->information = $information;
    }
}
