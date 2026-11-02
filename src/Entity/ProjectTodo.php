<?php

namespace App\Entity;

use App\Repository\ProjectTodoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tâche de travail d'un projet, saisie par l'auteur ou ajoutée par une analyse.
 *
 * Une tâche venue d'une analyse porte une empreinte (`dedupeKey`, sha1 d'une
 * clé fournie par le front) : relancer l'analyse ne la recrée pas, qu'elle
 * soit encore ouverte, cochée ou écartée. C'est pour ça qu'on n'efface pas
 * une tâche d'analyse supprimée par l'auteur : on la marque `dismissed`.
 */
#[ORM\Entity(repositoryClass: ProjectTodoRepository::class)]
#[ORM\Table(name: 'project_todo')]
#[ORM\UniqueConstraint(name: 'uniq_project_todo_dedupe', columns: ['project_id', 'dedupe_key'])]
class ProjectTodo
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_IA = 'ia';

    public const CATEGORIES = ['personnage', 'structure', 'continuite', 'style', 'recherche', 'autre'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(type: Types::TEXT)]
    private string $label;

    #[ORM\Column(length: 20)]
    private string $category = 'autre';

    #[ORM\Column(length: 10)]
    private string $source = self::SOURCE_MANUAL;

    /** Outil à l'origine de la tâche (verification-personnage…), pour l'affichage */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $dedupeKey = null;

    #[ORM\Column]
    private bool $done = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    #[ORM\Column]
    private bool $dismissed = false;

    #[ORM\ManyToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Sequence $sequence = null;

    #[ORM\ManyToOne(targetEntity: Scene::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Scene $scene = null;

    #[ORM\ManyToOne(targetEntity: Personnage::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnage $personnage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): Project { return $this->project; }
    public function getLabel(): string { return $this->label; }
    public function getCategory(): string { return $this->category; }
    public function getSource(): string { return $this->source; }
    public function getOrigin(): ?string { return $this->origin; }
    public function getDedupeKey(): ?string { return $this->dedupeKey; }
    public function isDone(): bool { return $this->done; }
    public function getDoneAt(): ?\DateTimeImmutable { return $this->doneAt; }
    public function isDismissed(): bool { return $this->dismissed; }
    public function getSequence(): ?Sequence { return $this->sequence; }
    public function getScene(): ?Scene { return $this->scene; }
    public function getPersonnage(): ?Personnage { return $this->personnage; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function setCategory(string $category): static
    {
        $this->category = in_array($category, self::CATEGORIES, true) ? $category : 'autre';
        return $this;
    }

    public function setSource(string $source): static
    {
        $this->source = $source === self::SOURCE_IA ? self::SOURCE_IA : self::SOURCE_MANUAL;
        return $this;
    }

    public function setOrigin(?string $origin): static
    {
        $this->origin = $origin;
        return $this;
    }

    public function setDedupeKey(?string $key): static
    {
        $this->dedupeKey = $key === null || $key === '' ? null : sha1($key);
        return $this;
    }

    public function setDone(bool $done): static
    {
        if ($done !== $this->done) {
            $this->doneAt = $done ? new \DateTimeImmutable() : null;
        }
        $this->done = $done;
        return $this;
    }

    public function setDismissed(bool $dismissed): static
    {
        $this->dismissed = $dismissed;
        return $this;
    }

    public function setSequence(?Sequence $sequence): static
    {
        $this->sequence = $sequence;
        return $this;
    }

    /** Une scène rattache aussi sa séquence, pour le classement par séquence */
    public function setScene(?Scene $scene): static
    {
        $this->scene = $scene;
        if ($scene) {
            $this->sequence = $scene->getSequence();
        }
        return $this;
    }

    public function setPersonnage(?Personnage $personnage): static
    {
        $this->personnage = $personnage;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'category' => $this->category,
            'source' => $this->source,
            'origin' => $this->origin,
            'done' => $this->done,
            'doneAt' => $this->doneAt?->format(\DATE_ATOM),
            'sequenceId' => $this->sequence?->getId(),
            'sceneId' => $this->scene?->getId(),
            'personnage' => $this->personnage ? [
                'id' => $this->personnage->getId(),
                'name' => trim($this->personnage->getFirstName() . ' ' . ($this->personnage->getLastName() ?? '')),
            ] : null,
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
        ];
    }
}
