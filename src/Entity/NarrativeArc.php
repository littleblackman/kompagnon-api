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
    normalizationContext: ['groups' => ['narrative_arc:read']]
)]
class NarrativeArc
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    #[Groups(['narrative_arc:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['narrative_arc:read'])]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['narrative_arc:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['narrative_arc:read'])]
    private array $steps = [];

    #[ORM\Column(type: 'json')]
    #[Groups(['narrative_arc:read'])]
    private array $variants = [];

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['narrative_arc:read'])]
    private string $tendency = 'ambiguous';

    #[ORM\ManyToMany(targetEntity: DramaticFunction::class, mappedBy: 'narrativeArcs')]
    private Collection $dramaticFunctions;

    public function __construct()
    {
        $this->dramaticFunctions = new ArrayCollection();
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

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function setSteps(array $steps): self
    {
        $this->steps = $steps;
        return $this;
    }

    public function getVariants(): array
    {
        return $this->variants;
    }

    public function setVariants(array $variants): self
    {
        $this->variants = $variants;
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
     * @return Collection<int, DramaticFunction>
     */
    public function getDramaticFunctions(): Collection
    {
        return $this->dramaticFunctions;
    }

    public function addDramaticFunction(DramaticFunction $dramaticFunction): self
    {
        if (!$this->dramaticFunctions->contains($dramaticFunction)) {
            $this->dramaticFunctions->add($dramaticFunction);
            $dramaticFunction->addNarrativeArc($this);
        }
        return $this;
    }

    public function removeDramaticFunction(DramaticFunction $dramaticFunction): self
    {
        if ($this->dramaticFunctions->removeElement($dramaticFunction)) {
            $dramaticFunction->removeNarrativeArc($this);
        }
        return $this;
    }
}