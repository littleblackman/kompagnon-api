<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection()
    ],
    normalizationContext: ['groups' => ['dramatic_function:read']]
)]
class DramaticFunction
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    #[Groups(['dramatic_function:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['dramatic_function:read'])]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['dramatic_function:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['dramatic_function:read'])]
    private array $characteristics = [];

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['dramatic_function:read'])]
    private string $tendency = 'ambiguous';

    #[ORM\OneToMany(mappedBy: 'dramaticFunction', targetEntity: SubgenreDramaticFunction::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $subgenreDramaticFunctions;

    #[ORM\ManyToMany(targetEntity: NarrativeArc::class, inversedBy: 'dramaticFunctions')]
    #[ORM\JoinTable(name: 'dramatic_function_narrative_arc')]
    private Collection $narrativeArcs;

    public function __construct()
    {
        $this->subgenreDramaticFunctions = new ArrayCollection();
        $this->narrativeArcs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
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

    public function getCharacteristics(): array
    {
        return $this->characteristics;
    }

    public function setCharacteristics(array $characteristics): self
    {
        $this->characteristics = $characteristics;
        return $this;
    }

    public function getTendency(): string
    {
        return $this->tendency;
    }

    public function setTendency(string $tendency): self
    {
        // Validation des valeurs possibles
        if (!in_array($tendency, ['positive', 'negative', 'ambiguous'])) {
            throw new \InvalidArgumentException('Tendency must be positive, negative or ambiguous');
        }
        $this->tendency = $tendency;
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
            $subgenreDramaticFunction->setDramaticFunction($this);
        }
        return $this;
    }

    public function removeSubgenreDramaticFunction(SubgenreDramaticFunction $subgenreDramaticFunction): self
    {
        if ($this->subgenreDramaticFunctions->removeElement($subgenreDramaticFunction)) {
            if ($subgenreDramaticFunction->getDramaticFunction() === $this) {
                $subgenreDramaticFunction->setDramaticFunction(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, NarrativeArc>
     */
    public function getNarrativeArcs(): Collection
    {
        return $this->narrativeArcs;
    }

    public function addNarrativeArc(NarrativeArc $narrativeArc): self
    {
        if (!$this->narrativeArcs->contains($narrativeArc)) {
            $this->narrativeArcs->add($narrativeArc);
        }
        return $this;
    }

    public function removeNarrativeArc(NarrativeArc $narrativeArc): self
    {
        $this->narrativeArcs->removeElement($narrativeArc);
        return $this;
    }
}