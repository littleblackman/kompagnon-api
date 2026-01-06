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

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['narrative_structure:read', 'narrative_structure:write'])]
    private ?array $narrativePartOrder = null;

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

    public function getNarrativePartOrder(): ?array
    {
        return $this->narrativePartOrder;
    }

    public function setNarrativePartOrder(?array $narrativePartOrder): self
    {
        $this->narrativePartOrder = $narrativePartOrder;
        return $this;
    }

    /**
     * Get all narrative part codes from the structure in order
     * Example JSON: {"Acte 1": ["SETUP", "DISRUPTION"], "Acte 2": ["ESCALATION", "TURN"]}
     * Returns: ["SETUP", "DISRUPTION", "ESCALATION", "TURN"]
     *
     * @return array
     */
    public function getNarrativePartCodesArray(): array
    {
        if (empty($this->narrativePartOrder)) {
            return [];
        }

        $codes = [];
        foreach ($this->narrativePartOrder as $actName => $partCodes) {
            if (is_array($partCodes)) {
                $codes = array_merge($codes, $partCodes);
            }
        }
        return $codes;
    }

    /**
     * Get the total number of beats (narrative parts) in this structure
     *
     * @return int
     */
    public function getTotalBeats(): int
    {
        return count($this->getNarrativePartCodesArray());
    }
}
