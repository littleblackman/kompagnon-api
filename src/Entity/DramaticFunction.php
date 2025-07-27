<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
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
}