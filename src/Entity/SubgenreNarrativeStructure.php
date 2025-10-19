<?php

namespace App\Entity;

use App\Repository\SubgenreNarrativeStructureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SubgenreNarrativeStructureRepository::class)]
#[ORM\Table(name: 'subgenre_narrative_structure')]
class SubgenreNarrativeStructure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['subgenre_narrative_structure:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Subgenre::class, inversedBy: 'subgenreNarrativeStructures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['subgenre_narrative_structure:read'])]
    private ?Subgenre $subgenre = null;

    #[ORM\ManyToOne(targetEntity: NarrativeStructure::class, inversedBy: 'subgenreNarrativeStructures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['subgenre_narrative_structure:read', 'subgenre:read'])]
    private ?NarrativeStructure $narrativeStructure = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['subgenre_narrative_structure:read', 'subgenre_narrative_structure:write', 'subgenre:read'])]
    private ?int $recommendedPercentage = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['subgenre_narrative_structure:read', 'subgenre_narrative_structure:write', 'subgenre:read'])]
    private bool $isDefault = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubgenre(): ?Subgenre
    {
        return $this->subgenre;
    }

    public function setSubgenre(?Subgenre $subgenre): self
    {
        $this->subgenre = $subgenre;
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

    public function getRecommendedPercentage(): ?int
    {
        return $this->recommendedPercentage;
    }

    public function setRecommendedPercentage(?int $recommendedPercentage): self
    {
        $this->recommendedPercentage = $recommendedPercentage;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }
}
