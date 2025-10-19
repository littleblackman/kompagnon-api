<?php

namespace App\Entity;

use App\Repository\NarrativeStructureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NarrativeStructureRepository::class)]
#[ORM\Table(name: 'narrative_structure')]
class NarrativeStructure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['narrative_structure:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['narrative_structure:read', 'narrative_structure:write'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['narrative_structure:read', 'narrative_structure:write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['narrative_structure:read', 'narrative_structure:write'])]
    private ?int $totalBeats = null;

    #[ORM\OneToMany(mappedBy: 'narrativeStructure', targetEntity: NarrativeStructureEvent::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['narrative_structure:read'])]
    private Collection $narrativeStructureEvents;

    #[ORM\OneToMany(mappedBy: 'narrativeStructure', targetEntity: SubgenreNarrativeStructure::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['narrative_structure:read'])]
    private Collection $subgenreNarrativeStructures;

    public function __construct()
    {
        $this->narrativeStructureEvents = new ArrayCollection();
        $this->subgenreNarrativeStructures = new ArrayCollection();
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

    public function getTotalBeats(): ?int
    {
        return $this->totalBeats;
    }

    public function setTotalBeats(?int $totalBeats): self
    {
        $this->totalBeats = $totalBeats;
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
            $narrativeStructureEvent->setNarrativeStructure($this);
        }
        return $this;
    }

    public function removeNarrativeStructureEvent(NarrativeStructureEvent $narrativeStructureEvent): self
    {
        if ($this->narrativeStructureEvents->removeElement($narrativeStructureEvent)) {
            if ($narrativeStructureEvent->getNarrativeStructure() === $this) {
                $narrativeStructureEvent->setNarrativeStructure(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SubgenreNarrativeStructure>
     */
    public function getSubgenreNarrativeStructures(): Collection
    {
        return $this->subgenreNarrativeStructures;
    }

    public function addSubgenreNarrativeStructure(SubgenreNarrativeStructure $subgenreNarrativeStructure): self
    {
        if (!$this->subgenreNarrativeStructures->contains($subgenreNarrativeStructure)) {
            $this->subgenreNarrativeStructures->add($subgenreNarrativeStructure);
            $subgenreNarrativeStructure->setNarrativeStructure($this);
        }
        return $this;
    }

    public function removeSubgenreNarrativeStructure(SubgenreNarrativeStructure $subgenreNarrativeStructure): self
    {
        if ($this->subgenreNarrativeStructures->removeElement($subgenreNarrativeStructure)) {
            if ($subgenreNarrativeStructure->getNarrativeStructure() === $this) {
                $subgenreNarrativeStructure->setNarrativeStructure(null);
            }
        }
        return $this;
    }
}
