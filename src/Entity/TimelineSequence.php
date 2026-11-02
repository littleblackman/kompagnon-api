<?php

namespace App\Entity;

use App\Entity\Traits\TimelineDated;
use App\Repository\TimelineSequenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Place d'une séquence dans le calendrier du récit : quand elle se passe,
 * où, avec qui. Une ligne du séquencier.
 *
 * Proposée par l'analyse, l'auteur la valide ou la corrige. Une fois validée,
 * une nouvelle analyse ne la réécrit plus : seuls ses faits sont relus.
 */
#[ORM\Entity(repositoryClass: TimelineSequenceRepository::class)]
#[ORM\Table(name: 'timeline_sequence')]
class TimelineSequence
{
    use TimelineDated;

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_VALIDATED = 'validated';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\OneToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private Sequence $sequence;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $place = null;

    /** Noms tels que le texte les donne, pas forcément des fiches personnages */
    #[ORM\Column(type: Types::JSON)]
    private array $who = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    /** Phrase du texte qui fonde la date */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $quote = null;

    #[ORM\Column(length: 10)]
    private string $status = self::STATUS_PROPOSED;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $extractedAt = null;

    public function __construct(Project $project, Sequence $sequence)
    {
        $this->project = $project;
        $this->sequence = $sequence;
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): Project { return $this->project; }
    public function getSequence(): Sequence { return $this->sequence; }
    public function getPlace(): ?string { return $this->place; }
    public function getWho(): array { return $this->who; }
    public function getSummary(): ?string { return $this->summary; }
    public function getQuote(): ?string { return $this->quote; }
    public function getStatus(): string { return $this->status; }
    public function isValidated(): bool { return $this->status === self::STATUS_VALIDATED; }
    public function getExtractedAt(): ?\DateTimeImmutable { return $this->extractedAt; }

    public function setPlace(?string $place): static
    {
        $this->place = $place !== null && trim($place) !== '' ? mb_substr(trim($place), 0, 255) : null;
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

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary !== null && trim($summary) !== '' ? trim($summary) : null;
        return $this;
    }

    public function setQuote(?string $quote): static
    {
        $this->quote = $quote !== null && trim($quote) !== '' ? trim($quote) : null;
        return $this;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status === self::STATUS_VALIDATED ? self::STATUS_VALIDATED : self::STATUS_PROPOSED;
        return $this;
    }

    public function markExtracted(): static
    {
        $this->extractedAt = new \DateTimeImmutable();
        return $this;
    }
}
