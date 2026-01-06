<?php

namespace App\Entity;

use App\Repository\EventTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: EventTypeRepository::class)]
#[ORM\Table(name: 'event_type')]
class EventType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['event_type:read', 'event:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['event_type:read', 'event_type:write', 'event:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Groups(['event_type:read', 'event_type:write'])]
    private ?string $code = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['event_type:read', 'event_type:write'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: NarrativePart::class, inversedBy: 'eventTypes')]
    #[ORM\JoinColumn(name: 'narrative_part_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['event_type:read', 'event:read'])]
    private ?NarrativePart $narrativePart = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Groups(['event_type:read'])]
    private ?string $narrativePartCode = null;

    #[ORM\OneToMany(mappedBy: 'eventType', targetEntity: Event::class)]
    private Collection $events;

    public function __construct()
    {
        $this->events = new ArrayCollection();
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
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setEventType($this);
        }
        return $this;
    }

    public function removeEvent(Event $event): self
    {
        if ($this->events->removeElement($event)) {
            if ($event->getEventType() === $this) {
                $event->setEventType(null);
            }
        }
        return $this;
    }

    public function getNarrativePart(): ?NarrativePart
    {
        return $this->narrativePart;
    }

    public function setNarrativePart(?NarrativePart $narrativePart): self
    {
        $this->narrativePart = $narrativePart;
        // Synchroniser le code
        if ($narrativePart) {
            $this->narrativePartCode = $narrativePart->getCode();
        } else {
            $this->narrativePartCode = null;
        }
        return $this;
    }

    public function getNarrativePartCode(): ?string
    {
        return $this->narrativePartCode;
    }

    public function setNarrativePartCode(?string $narrativePartCode): self
    {
        $this->narrativePartCode = $narrativePartCode;
        return $this;
    }
}
