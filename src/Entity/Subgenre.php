<?php

namespace App\Entity;

use App\Repository\SubgenreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SubgenreRepository::class)]
#[ORM\Table(name: 'subgenre')]
class Subgenre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['subgenre:read', 'genre:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['subgenre:read', 'subgenre:write', 'genre:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['subgenre:read', 'subgenre:write', 'genre:read'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Genre::class, inversedBy: 'subgenres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    // Pas de Groups ici pour éviter la référence circulaire Genre → Subgenre → Genre
    private ?Genre $genre = null;

    #[ORM\OneToMany(mappedBy: 'subgenre', targetEntity: SubgenreDramaticFunction::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    // Pas de Groups ici pour éviter surcharge dans metadata
    private Collection $subgenreDramaticFunctions;

    #[ORM\OneToMany(mappedBy: 'subgenre', targetEntity: SubgenreEventType::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['weight' => 'DESC'])]
    // Pas de Groups ici pour éviter surcharge dans metadata
    private Collection $subgenreEventTypes;

    public function __construct()
    {
        $this->subgenreDramaticFunctions = new ArrayCollection();
        $this->subgenreEventTypes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getGenre(): ?Genre
    {
        return $this->genre;
    }

    public function setGenre(?Genre $genre): self
    {
        $this->genre = $genre;
        return $this;
    }

    /**
     * @return Collection<int, SubgenreDramaticFunction>
     */
    public function getSubgenreDramaticFunctions(): Collection
    {
        return $this->subgenreDramaticFunctions;
    }

    public function addSubgenreDramaticFunction(SubgenreDramaticFunction $subgenreDramaticFunction): self
    {
        if (!$this->subgenreDramaticFunctions->contains($subgenreDramaticFunction)) {
            $this->subgenreDramaticFunctions->add($subgenreDramaticFunction);
            $subgenreDramaticFunction->setSubgenre($this);
        }
        return $this;
    }

    public function removeSubgenreDramaticFunction(SubgenreDramaticFunction $subgenreDramaticFunction): self
    {
        if ($this->subgenreDramaticFunctions->removeElement($subgenreDramaticFunction)) {
            if ($subgenreDramaticFunction->getSubgenre() === $this) {
                $subgenreDramaticFunction->setSubgenre(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SubgenreEventType>
     */
    public function getSubgenreEventTypes(): Collection
    {
        return $this->subgenreEventTypes;
    }

    public function addSubgenreEventType(SubgenreEventType $subgenreEventType): self
    {
        if (!$this->subgenreEventTypes->contains($subgenreEventType)) {
            $this->subgenreEventTypes->add($subgenreEventType);
            $subgenreEventType->setSubgenre($this);
        }
        return $this;
    }

    public function removeSubgenreEventType(SubgenreEventType $subgenreEventType): self
    {
        if ($this->subgenreEventTypes->removeElement($subgenreEventType)) {
            if ($subgenreEventType->getSubgenre() === $this) {
                $subgenreEventType->setSubgenre(null);
            }
        }
        return $this;
    }
}
