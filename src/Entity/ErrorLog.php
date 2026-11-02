<?php

namespace App\Entity;

use App\Repository\ErrorLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Erreur journalisée, côté API ou remontée par le front.
 *
 * Les occurrences identiques (même empreinte) et non résolues sont regroupées
 * sur une seule ligne : on incrémente `occurrences` au lieu d'empiler des
 * doublons. L'écriture passe par ErrorLogger (DBAL), pas par l'EntityManager,
 * qui peut être fermé justement à cause de l'erreur qu'on veut noter.
 */
#[ORM\Entity(repositoryClass: ErrorLogRepository::class)]
#[ORM\Table(name: 'error_log')]
#[ORM\Index(columns: ['fingerprint'], name: 'idx_error_log_fingerprint')]
#[ORM\Index(columns: ['last_seen_at'], name: 'idx_error_log_last_seen')]
class ErrorLog
{
    public const SOURCE_API = 'api';
    public const SOURCE_FRONT = 'front';

    public const LEVELS = ['critical', 'error', 'warning'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private string $source;

    #[ORM\Column(length: 10)]
    private string $level;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $exceptionClass = null;

    #[ORM\Column(nullable: true)]
    private ?int $statusCode = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $file = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $trace = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $userEmail = null;

    #[ORM\Column(length: 40)]
    private string $fingerprint;

    #[ORM\Column]
    private int $occurrences = 1;

    #[ORM\Column]
    private bool $resolved = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    public function getId(): ?int { return $this->id; }
    public function getSource(): string { return $this->source; }
    public function getLevel(): string { return $this->level; }
    public function getMessage(): string { return $this->message; }
    public function getExceptionClass(): ?string { return $this->exceptionClass; }
    public function getStatusCode(): ?int { return $this->statusCode; }
    public function getMethod(): ?string { return $this->method; }
    public function getUrl(): ?string { return $this->url; }
    public function getFile(): ?string { return $this->file; }
    public function getTrace(): ?string { return $this->trace; }
    public function getContext(): ?array { return $this->context; }
    public function getUserEmail(): ?string { return $this->userEmail; }
    public function getFingerprint(): string { return $this->fingerprint; }
    public function getOccurrences(): int { return $this->occurrences; }
    public function isResolved(): bool { return $this->resolved; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLastSeenAt(): \DateTimeImmutable { return $this->lastSeenAt; }

    public function setResolved(bool $resolved): static
    {
        $this->resolved = $resolved;
        return $this;
    }
}
