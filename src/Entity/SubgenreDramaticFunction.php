<?php

namespace App\Entity;

use App\Repository\SubgenreDramaticFunctionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SubgenreDramaticFunctionRepository::class)]
#[ORM\Table(name: 'subgenre_dramatic_function')]
class SubgenreDramaticFunction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['subgenre_dramatic_function:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Subgenre::class, inversedBy: 'subgenreDramaticFunctions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['subgenre_dramatic_function:read'])]
    private ?Subgenre $subgenre = null;

    #[ORM\ManyToOne(targetEntity: DramaticFunction::class, inversedBy: 'subgenreDramaticFunctions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['subgenre_dramatic_function:read', 'subgenre:read'])]
    private ?DramaticFunction $dramaticFunction = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['subgenre_dramatic_function:read', 'subgenre_dramatic_function:write'])]
    private bool $isEssential = false;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Groups(['subgenre_dramatic_function:read', 'subgenre_dramatic_function:write'])]
    private ?string $typicalCount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubgenre(): ?Subgenre
    {
        return $this->subgenre;
    }

    public function setSubgenre(?Subgenre $subgenre): self
    {
        $this->subgenre = $subgenre;
        return $this;
    }

    public function getDramaticFunction(): ?DramaticFunction
    {
        return $this->dramaticFunction;
    }

    public function setDramaticFunction(?DramaticFunction $dramaticFunction): self
    {
        $this->dramaticFunction = $dramaticFunction;
        return $this;
    }

    public function isEssential(): bool
    {
        return $this->isEssential;
    }

    public function setIsEssential(bool $isEssential): self
    {
        $this->isEssential = $isEssential;
        return $this;
    }

    public function getTypicalCount(): ?string
    {
        return $this->typicalCount;
    }

    public function setTypicalCount(?string $typicalCount): self
    {
        $this->typicalCount = $typicalCount;
        return $this;
    }
}
