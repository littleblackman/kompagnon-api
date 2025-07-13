<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['scene:read']],
    denormalizationContext: ['groups' => ['scene:write']]
)]
class Scene
{
    use Timestampable;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    #[Groups(['scene:read', 'sequence:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['scene:read', 'scene:write', 'sequence:read'])]
    private string $name;

    #[ORM\Column(type: 'integer')]
    #[Groups(['scene:read', 'scene:write', 'sequence:read'])]
    private int $position;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['scene:read', 'scene:write', 'sequence:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['scene:read', 'scene:write', 'sequence:read'])]
    private string $content;

    #[ORM\ManyToOne(targetEntity: Sequence::class, inversedBy: 'scenes')]
    #[Groups(['scene:write'])]
    private Sequence $sequence;

    #[ORM\ManyToOne(targetEntity: Status::class)]
    #[Groups(['scene:read', 'scene:write'])]
    private Status $status;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getSequence(): Sequence
    {
        return $this->sequence;
    }

    public function setSequence(?Sequence $sequence): void
    {
        $this->sequence = $sequence;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): void
    {
        $this->status = $status;
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

    #[Groups(['scene:read', 'sequence:read'])]
    public function getSequenceId(): ?int
    {
        return $this->sequence?->getId();
    }
}
