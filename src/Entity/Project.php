<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Traits\Timestampable;
use App\State\ProjectCollectionProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new GetCollection(provider: ProjectCollectionProvider::class)
    ],
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
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $referenceNarrativeComponents = null;

    #[Groups(['project:read', 'project:write'])]
    #[ORM\ManyToOne(targetEntity: Type::class)]
    private Type $type;

    #[Groups(['project:read'])]
    #[ORM\OneToMany(targetEntity: Part::class, mappedBy: 'project', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $parts;

    #[Groups(['project:read', 'project:write'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[Groups(['project:read'])]
    #[ORM\OneToMany(targetEntity: Personnage::class, mappedBy: 'project', cascade: ['persist', 'remove'])]
    private Collection $personnages;

    public function __construct()
    {
        $this->parts = new ArrayCollection();
        $this->personnages = new ArrayCollection();
    }

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

    public function getReferenceNarrativeComponents(): ?array
    {
        return $this->referenceNarrativeComponents;
    }

    public function setReferenceNarrativeComponents(?array $referenceNarrativeComponents): void
    {
        $this->referenceNarrativeComponents = $referenceNarrativeComponents;
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

    public function getParts(): Collection
    {
        return $this->parts;
    }

    public function addPart(Part $part): self
    {
        if (!$this->parts->contains($part)) {
            $this->parts->add($part);
            $part->setProject($this);
        }

        return $this;
    }

    public function removePart(Part $part): self
    {
        if ($this->parts->removeElement($part)) {
            if ($part->getProject() === $this) {
                $part->setProject(null);
            }
        }

        return $this;
    }

    public function getPersonnages(): Collection
    {
        return $this->personnages;
    }

    public function addPersonnage(Personnage $personnage): self
    {
        if (!$this->personnages->contains($personnage)) {
            $this->personnages->add($personnage);
            $personnage->setProject($this);
        }

        return $this;
    }

    public function removePersonnage(Personnage $personnage): self
    {
        if ($this->personnages->removeElement($personnage)) {
            if ($personnage->getProject() === $this) {
                $personnage->setProject(null);
            }
        }

        return $this;
    }
}
