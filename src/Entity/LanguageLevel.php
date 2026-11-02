<?php

namespace App\Entity;

use App\Repository\LanguageLevelRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Référentiel des niveaux de langage (soutenu, familier, argotique…) dont
 * on qualifie la parole d'un personnage.
 */
#[ORM\Entity(repositoryClass: LanguageLevelRepository::class)]
#[ORM\Table(name: 'language_level')]
class LanguageLevel
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    #[Groups(['language_level:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['language_level:read'])]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['language_level:read'])]
    private ?string $description = null;

    /** Marqueurs typiques, pour reconnaître le niveau dans un dialogue */
    #[ORM\Column(type: 'json')]
    #[Groups(['language_level:read'])]
    private array $markers = [];

    /** Ordre d'affichage : du plus soutenu au plus relâché, puis les styles transverses */
    #[ORM\Column(type: 'integer')]
    #[Groups(['language_level:read'])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getMarkers(): array
    {
        return $this->markers;
    }

    public function setMarkers(array $markers): self
    {
        $this->markers = $markers;
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'markers' => $this->markers,
        ];
    }
}
