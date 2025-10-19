<?php

namespace App\Entity;

use App\Repository\NarrativeStructureEventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NarrativeStructureEventRepository::class)]
#[ORM\Table(name: 'narrative_structure_event')]
#[ORM\Index(name: 'idx_structure_position', columns: ['narrative_structure_id', 'position'])]
class NarrativeStructureEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['narrative_structure_event:read', 'narrative_structure:read', 'event:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['narrative_structure_event:read', 'narrative_structure_event:write', 'narrative_structure:read', 'event:read'])]
    private ?int $position = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['narrative_structure_event:read', 'narrative_structure_event:write', 'narrative_structure:read', 'event:read'])]
    private bool $isOptional = false;

    #[ORM\ManyToOne(targetEntity: NarrativeStructure::class, inversedBy: 'narrativeStructureEvents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['narrative_structure_event:read'])]
    private ?NarrativeStructure $narrativeStructure = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'narrativeStructureEvents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['narrative_structure_event:read', 'narrative_structure:read'])]
    private ?Event $event = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    public function setIsOptional(bool $isOptional): self
    {
        $this->isOptional = $isOptional;
        return $this;
    }

    public function getNarrativeStructure(): ?NarrativeStructure
    {
        return $this->narrativeStructure;
    }

    public function setNarrativeStructure(?NarrativeStructure $narrativeStructure): self
    {
        $this->narrativeStructure = $narrativeStructure;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;
        return $this;
    }
}
