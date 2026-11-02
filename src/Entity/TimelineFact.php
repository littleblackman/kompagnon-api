<?php

namespace App\Entity;

use App\Entity\Traits\TimelineDated;
use App\Repository\TimelineFactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fait daté ou datable du récit, autre que la séquence elle-même : ce qu'un
 * personnage raconte de son passé, un événement historique, une note de fiche.
 *
 * Deux axes à ne pas confondre :
 * - la couche (`layer`) dit OÙ le fait se situe : présent du récit, passé
 *   des personnages, histoire du monde ;
 * - la nature (`nature`) dit D'OÙ on le tient : montré en scène, évoqué
 *   dans le texte, ou tiré d'une fiche personnage.
 */
#[ORM\Entity(repositoryClass: TimelineFactRepository::class)]
#[ORM\Table(name: 'timeline_fact')]
class TimelineFact
{
    use TimelineDated;

    public const LAYERS = ['present', 'passe', 'histoire'];

    public const NATURE_SCENE = 'scene';
    public const NATURE_EVOQUE = 'evoque';
    public const NATURE_FICHE = 'fiche';
    public const NATURES = [self::NATURE_SCENE, self::NATURE_EVOQUE, self::NATURE_FICHE];

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_REJECTED = 'rejected';
    public const STATUSES = [self::STATUS_PROPOSED, self::STATUS_VALIDATED, self::STATUS_REJECTED];

    /** Verdict de la passe de cohérence sur un fait de fiche : le texte le confirme-t-il ? */
    public const CHECKS = ['confirmed', 'unverifiable', 'contradicted'];

    public const ORIGIN_IA = 'ia';
    public const ORIGIN_MANUAL = 'manual';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    /** Séquence où le fait est lu ; nulle pour un fait de fiche */
    #[ORM\ManyToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Sequence $sequence = null;

    /** Fiche dont le fait est tiré */
    #[ORM\ManyToOne(targetEntity: Personnage::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Personnage $personnage = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $label;

    #[ORM\Column(type: Types::JSON)]
    private array $who = [];

    #[ORM\Column(length: 10)]
    private string $layer = 'passe';

    #[ORM\Column(length: 10)]
    private string $nature = self::NATURE_EVOQUE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $quote = null;

    #[ORM\Column(length: 10)]
    private string $status = self::STATUS_PROPOSED;

    #[ORM\Column(name: 'text_check', length: 15, nullable: true)]
    private ?string $check = null;

    #[ORM\Column(length: 10)]
    private string $origin = self::ORIGIN_IA;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): Project { return $this->project; }
    public function getSequence(): ?Sequence { return $this->sequence; }
    public function getPersonnage(): ?Personnage { return $this->personnage; }
    public function getLabel(): string { return $this->label; }
    public function getWho(): array { return $this->who; }
    public function getLayer(): string { return $this->layer; }
    public function getNature(): string { return $this->nature; }
    public function getQuote(): ?string { return $this->quote; }
    public function getStatus(): string { return $this->status; }
    public function getCheck(): ?string { return $this->check; }
    public function getOrigin(): string { return $this->origin; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setSequence(?Sequence $sequence): static
    {
        $this->sequence = $sequence;
        return $this;
    }

    public function setPersonnage(?Personnage $personnage): static
    {
        $this->personnage = $personnage;
        return $this;
    }

    public function setLabel(string $label): static
    {
        $this->label = trim($label);
        return $this;
    }

    public function setWho(array $who): static
    {
        $this->who = array_values(array_unique(array_filter(array_map(
            fn ($name) => is_string($name) ? trim($name) : '',
            $who
        ))));
        return $this;
    }

    public function setLayer(string $layer): static
    {
        $this->layer = in_array($layer, self::LAYERS, true) ? $layer : 'passe';
        return $this;
    }

    public function setNature(string $nature): static
    {
        $this->nature = in_array($nature, self::NATURES, true) ? $nature : self::NATURE_EVOQUE;
        return $this;
    }

    public function setQuote(?string $quote): static
    {
        $this->quote = $quote !== null && trim($quote) !== '' ? trim($quote) : null;
        return $this;
    }

    public function setStatus(string $status): static
    {
        $this->status = in_array($status, self::STATUSES, true) ? $status : self::STATUS_PROPOSED;
        return $this;
    }

    public function setCheck(?string $check): static
    {
        $this->check = in_array($check, self::CHECKS, true) ? $check : null;
        return $this;
    }

    public function setOrigin(string $origin): static
    {
        $this->origin = $origin === self::ORIGIN_MANUAL ? self::ORIGIN_MANUAL : self::ORIGIN_IA;
        return $this;
    }
}
