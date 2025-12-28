<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'subgenre_event_type')]
class SubgenreEventType
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Subgenre::class, inversedBy: 'subgenreEventTypes')]
    #[ORM\JoinColumn(name: 'subgenre_id', nullable: false, onDelete: 'CASCADE')]
    private ?Subgenre $subgenre = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: EventType::class)]
    #[ORM\JoinColumn(name: 'event_type_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['subgenre:read'])]
    private ?EventType $eventType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Groups(['subgenre:read'])]
    private ?string $eventTypeCode = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['subgenre:read'])]
    private ?int $weight = null;

    #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => 0])]
    #[Groups(['subgenre:read'])]
    private bool $isMandatory = false;

    public function getSubgenre(): ?Subgenre
    {
        return $this->subgenre;
    }

    public function setSubgenre(?Subgenre $subgenre): self
    {
        $this->subgenre = $subgenre;
        return $this;
    }

    public function getEventType(): ?EventType
    {
        return $this->eventType;
    }

    public function setEventType(?EventType $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getEventTypeCode(): ?string
    {
        return $this->eventTypeCode;
    }

    public function setEventTypeCode(?string $eventTypeCode): self
    {
        $this->eventTypeCode = $eventTypeCode;
        return $this;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(?int $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    public function isMandatory(): bool
    {
        return $this->isMandatory;
    }

    public function setIsMandatory(bool $isMandatory): self
    {
        $this->isMandatory = $isMandatory;
        return $this;
    }
}
