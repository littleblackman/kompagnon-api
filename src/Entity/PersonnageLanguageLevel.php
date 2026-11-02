<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un niveau de langage tenu par un personnage. Un personnage peut en avoir
 * plusieurs : le poids dit lequel domine, le commentaire dans quelles
 * circonstances il bascule (« soutenu en public, argotique entre amis »).
 */
#[ORM\Entity]
#[ORM\Table(name: 'personnage_language_level')]
#[ORM\UniqueConstraint(name: 'uniq_personnage_language_level', columns: ['personnage_id', 'language_level_id'])]
class PersonnageLanguageLevel
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Personnage::class, inversedBy: 'personnageLanguageLevels')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Personnage $personnage;

    #[ORM\ManyToOne(targetEntity: LanguageLevel::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LanguageLevel $languageLevel;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 0, max: 100)]
    private int $weight = 50;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    /** Réplique du texte qui illustre ce niveau */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $quote = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPersonnage(): Personnage
    {
        return $this->personnage;
    }

    public function setPersonnage(Personnage $personnage): self
    {
        $this->personnage = $personnage;
        return $this;
    }

    public function getLanguageLevel(): LanguageLevel
    {
        return $this->languageLevel;
    }

    public function setLanguageLevel(LanguageLevel $languageLevel): self
    {
        $this->languageLevel = $languageLevel;
        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): self
    {
        $this->weight = max(0, min(100, $weight));
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getQuote(): ?string
    {
        return $this->quote;
    }

    public function setQuote(?string $quote): self
    {
        $this->quote = $quote;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'languageLevel' => $this->languageLevel->toArray(),
            'weight' => $this->weight,
            'comment' => $this->comment,
            'quote' => $this->quote,
        ];
    }
}
