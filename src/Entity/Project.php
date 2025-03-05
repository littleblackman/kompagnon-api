<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;



#[ApiResource(
    normalizationContext: ['groups' => ['project:read']],
    denormalizationContext: ['groups' => ['project:write']]
)]
#[ORM\Entity]
class Project
{
    use Timestampable;

    #[Groups(['project:read'])]
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Groups(['project:read', 'project:write'])]
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[Groups(['project:read', 'project:write'])]
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $slug;

    #[Groups(['project:read', 'project:write'])]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;
    #[Groups(['project:read', 'project:write'])]
    #[ORM\ManyToOne(targetEntity: Type::class)]
    private Type $type;

    #[Groups(['project:read', 'project:write'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function setType(Type $type): void
    {
        $this->type = $type;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }
}
