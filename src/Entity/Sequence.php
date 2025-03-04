<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity]
#[ApiResource]
class Sequence
{
    use Timestampable;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    private int $order;

    #[ORM\ManyToOne(targetEntity: Part::class)]
    private Part $part;

    #[ORM\ManyToOne(targetEntity: Status::class)]
    private Status $status;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $intention = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $aesthetic_idea = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
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

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): void
    {
        $this->order = $order;
    }

    public function getPart(): Part
    {
        return $this->part;
    }

    public function setPart(Part $part): void
    {
        $this->part = $part;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): void
    {
        $this->status = $status;
    }

    public function getIntention(): ?string
    {
        return $this->intention;
    }

    public function setIntention(?string $intention): void
    {
        $this->intention = $intention;
    }

    public function getAestheticIdea(): ?string
    {
        return $this->aesthetic_idea;
    }

    public function setAestheticIdea(?string $aesthetic_idea): void
    {
        $this->aesthetic_idea = $aesthetic_idea;
    }

}
