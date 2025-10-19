<?php

namespace App\Entity;

use App\Repository\GenreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: GenreRepository::class)]
#[ORM\Table(name: 'genre')]
class Genre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['genre:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['genre:read', 'genre:write'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['genre:read', 'genre:write'])]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'genre', targetEntity: Subgenre::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['genre:read'])]
    private Collection $subgenres;

    public function __construct()
    {
        $this->subgenres = new ArrayCollection();
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

    /**
     * @return Collection<int, Subgenre>
     */
    public function getSubgenres(): Collection
    {
        return $this->subgenres;
    }

    public function addSubgenre(Subgenre $subgenre): self
    {
        if (!$this->subgenres->contains($subgenre)) {
            $this->subgenres->add($subgenre);
            $subgenre->setGenre($this);
        }
        return $this;
    }

    public function removeSubgenre(Subgenre $subgenre): self
    {
        if ($this->subgenres->removeElement($subgenre)) {
            if ($subgenre->getGenre() === $this) {
                $subgenre->setGenre(null);
            }
        }
        return $this;
    }
}
