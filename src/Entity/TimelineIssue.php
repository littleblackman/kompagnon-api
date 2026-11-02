<?php

namespace App\Entity;

use App\Repository\TimelineIssueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Incohérence de calendrier relevée par la passe de cohérence.
 *
 * Le manuscrit prime sur les fiches : une incohérence décrit un écart et
 * propose une correction, elle ne modifie rien. Écartée par l'auteur, elle
 * reste en base pour ne pas revenir à l'analyse suivante.
 */
#[ORM\Entity(repositoryClass: TimelineIssueRepository::class)]
#[ORM\Table(name: 'timeline_issue')]
class TimelineIssue
{
    /** Du plus grave au moins grave : l'ordre sert au tri */
    public const SEVERITIES = ['fort', 'moyen', 'faible', 'verifier'];

    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUSES = [self::STATUS_OPEN, self::STATUS_RESOLVED, self::STATUS_DISMISSED];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(length: 10)]
    private string $severity = 'moyen';

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'fix_proposal', type: Types::TEXT, nullable: true)]
    private ?string $fix = null;

    #[ORM\Column(length: 10)]
    private string $status = self::STATUS_OPEN;

    /** Empreinte du titre normalisé, pour ne pas reproposer une incohérence écartée */
    #[ORM\Column(length: 40)]
    private string $dedupeKey;

    #[ORM\ManyToMany(targetEntity: TimelineFact::class)]
    #[ORM\JoinTable(name: 'timeline_issue_fact')]
    private Collection $facts;

    #[ORM\ManyToMany(targetEntity: TimelineSequence::class)]
    #[ORM\JoinTable(name: 'timeline_issue_sequence')]
    private Collection $sequences;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Project $project, string $title)
    {
        $this->project = $project;
        $this->title = mb_substr(trim($title), 0, 255);
        $this->dedupeKey = self::fingerprint($title);
        $this->facts = new ArrayCollection();
        $this->sequences = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fingerprint(string $title): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(
            iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title
        ));

        return sha1(trim($normalized));
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): Project { return $this->project; }
    public function getSeverity(): string { return $this->severity; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getFix(): ?string { return $this->fix; }
    public function getStatus(): string { return $this->status; }
    public function getDedupeKey(): string { return $this->dedupeKey; }
    /** @return Collection<int, TimelineFact> */
    public function getFacts(): Collection { return $this->facts; }
    /** @return Collection<int, TimelineSequence> */
    public function getSequences(): Collection { return $this->sequences; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setSeverity(string $severity): static
    {
        $this->severity = in_array($severity, self::SEVERITIES, true) ? $severity : 'moyen';
        return $this;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description !== null && trim($description) !== '' ? trim($description) : null;
        return $this;
    }

    public function setFix(?string $fix): static
    {
        $this->fix = $fix !== null && trim($fix) !== '' ? trim($fix) : null;
        return $this;
    }

    public function setStatus(string $status): static
    {
        $this->status = in_array($status, self::STATUSES, true) ? $status : self::STATUS_OPEN;
        return $this;
    }

    public function addFact(TimelineFact $fact): static
    {
        if (!$this->facts->contains($fact)) {
            $this->facts->add($fact);
        }
        return $this;
    }

    public function addSequence(TimelineSequence $sequence): static
    {
        if (!$this->sequences->contains($sequence)) {
            $this->sequences->add($sequence);
        }
        return $this;
    }
}
