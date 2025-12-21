<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['event:read', 'subgenre:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['event:read', 'event:write', 'subgenre:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['event:read', 'event:write', 'subgenre:read'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: EventType::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['event:read', 'event:write'])]
    private ?EventType $eventType = null;

    #[ORM\ManyToMany(targetEntity: Subgenre::class, inversedBy: 'events')]
    #[ORM\JoinTable(name: 'subgenre_event')]
    #[Groups(['event:write'])]
    private Collection $subgenres;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: NarrativeStructureEvent::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $narrativeStructureEvents;

    public function __construct()
    {
        $this->subgenres = new ArrayCollection();
        $this->narrativeStructureEvents = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getEventType(): ?EventType
    {
        return $this->eventType;
    }

    public function setEventType(?EventType $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    /**
     * @return Collection<int, Subgenre>
     */
    public function getSubgenres(): Collection
    {
        return $this->subgenres;
    }

    public function addSubgenre(Subgenre $subgenre): self
    {
        if (!$this->subgenres->contains($subgenre)) {
            $this->subgenres->add($subgenre);
        }
        return $this;
    }

    public function removeSubgenre(Subgenre $subgenre): self
    {
        $this->subgenres->removeElement($subgenre);
        return $this;
    }

    /**
     * @return Collection<int, NarrativeStructureEvent>
     */
    public function getNarrativeStructureEvents(): Collection
    {
        return $this->narrativeStructureEvents;
    }

    public function addNarrativeStructureEvent(NarrativeStructureEvent $narrativeStructureEvent): self
    {
        if (!$this->narrativeStructureEvents->contains($narrativeStructureEvent)) {
            $this->narrativeStructureEvents->add($narrativeStructureEvent);
            $narrativeStructureEvent->setEvent($this);
        }
        return $this;
    }

    public function removeNarrativeStructureEvent(NarrativeStructureEvent $narrativeStructureEvent): self
    {
        if ($this->narrativeStructureEvents->removeElement($narrativeStructureEvent)) {
            if ($narrativeStructureEvent->getEvent() === $this) {
                $narrativeStructureEvent->setEvent(null);
            }
        }
        return $this;
    }
}
