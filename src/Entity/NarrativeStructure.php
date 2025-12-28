<?php

namespace App\Entity;

use App\Repository\NarrativeStructureRepository;
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

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['narrative_structure:read', 'narrative_structure:write'])]
    private ?string $eventTypeAssociated = null;

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

    public function getEventTypeAssociated(): ?string
    {
        return $this->eventTypeAssociated;
    }

    public function setEventTypeAssociated(?string $eventTypeAssociated): self
    {
        $this->eventTypeAssociated = $eventTypeAssociated;
        return $this;
    }

    /**
     * Get event type codes as array
     * Example: "PERFECT_DAILY_ROUTINE;DISTURBING_DISCOVERY;CROSSING_THE_THRESHOLD"
     * Returns: ["PERFECT_DAILY_ROUTINE", "DISTURBING_DISCOVERY", "CROSSING_THE_THRESHOLD"]
     *
     * @return array
     */
    public function getEventTypeCodesArray(): array
    {
        if (empty($this->eventTypeAssociated)) {
            return [];
        }
        return array_filter(array_map('trim', explode(';', $this->eventTypeAssociated)));
    }
}
