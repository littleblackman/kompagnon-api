<?php

namespace App\Entity;

use App\Repository\NarrativePartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NarrativePartRepository::class)]
#[ORM\Table(name: 'narrative_part')]
class NarrativePart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['narrative_part:read', 'event_type:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['narrative_part:read', 'narrative_part:write', 'event_type:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Groups(['narrative_part:read', 'narrative_part:write', 'event_type:read'])]
    private ?string $code = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['narrative_part:read', 'narrative_part:write'])]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'narrativePart', targetEntity: EventType::class)]
    private Collection $eventTypes;

    public function __construct()
    {
        $this->eventTypes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
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

    /**
     * @return Collection<int, EventType>
     */
    public function getEventTypes(): Collection
    {
        return $this->eventTypes;
    }

    public function addEventType(EventType $eventType): self
    {
        if (!$this->eventTypes->contains($eventType)) {
            $this->eventTypes->add($eventType);
            $eventType->setNarrativePart($this);
        }
        return $this;
    }

    public function removeEventType(EventType $eventType): self
    {
        if ($this->eventTypes->removeElement($eventType)) {
            if ($eventType->getNarrativePart() === $this) {
                $eventType->setNarrativePart(null);
            }
        }
        return $this;
    }
}
