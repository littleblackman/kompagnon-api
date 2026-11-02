<?php

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use App\Utils\SlugUtils;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[ORM\Entity]
class Personnage
{

    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(["personnage:read", "sequence:read", "part:read", "project:read"])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(["personnage:read", "sequence:read", "part:read", "project:read"])]
    private string $firstName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(["personnage:read", "sequence:read", "part:read", "project:read"])]
    // Nullable en base : un personnage peut n'avoir qu'un prénom
    private ?string $lastName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(["personnage:read", "sequence:read", "part:read", "project:read"])]
    private ?string $background = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(["personnage:read", "sequence:read", "part:read", "project:read"])]
    private ?int $age = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'personnages')]
    #[ORM\JoinColumn(nullable: false)]
    private Project $project;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(["personnage:read"])]
    private ?string $avatar = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Groups(["personnage:read", "sequence:read", "part:read", "project:read"])]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(["personnage:read", "personnage:write", "sequence:read", "part:read", "project:read"])]
    private ?string $images = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(["personnage:read", "sequence:read", "part:read", "project:read"])]
    private ?int $level = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(["personnage:read", "sequence:read", "part:read", "project:read"])]
    private ?string $analysis = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(["personnage:read"])]
    private ?string $strength = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(["personnage:read"])]
    private ?string $weakness = null;

    #[ORM\OneToMany(targetEntity: SequencePersonnage::class, mappedBy: 'personnage', cascade: ['persist', 'remove'])]
    #[MaxDepth(1)]
    private Collection $sequencePersonnages;

    #[ORM\OneToMany(targetEntity: PersonnageDramaticFunction::class, mappedBy: 'personnage', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(["personnage:read", "project:read"])]
    private Collection $personnageDramaticFunctions;

    #[ORM\OneToMany(targetEntity: PersonnageNarrativeArc::class, mappedBy: 'personnage', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(["personnage:read", "project:read"])]
    private Collection $personnageNarrativeArcs;

    // Pas de groupe de sérialisation : le schéma est servi par /details,
    // inutile de l'embarquer dans le payload du projet
    #[ORM\OneToMany(targetEntity: PersonnageActantialSchema::class, mappedBy: 'personnage', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $actantialSchemas;

    /** Comment parle le personnage : vocabulaire, syntaxe, tics, écarts selon l'interlocuteur */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(["personnage:read"])]
    private ?string $languageAnalysis = null;

    /**
     * Dernière vérification du personnage contre le texte : verdicts sur ses
     * arcs et ses fonctions dramatiques, ce qui est détecté hors fiche, écarts
     * et pistes. Côté analyse, jamais renvoyée en entrée d'une analyse.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $verification = null;

    // Servis par /details, comme le schéma actantiel
    #[ORM\OneToMany(targetEntity: PersonnageLanguageLevel::class, mappedBy: 'personnage', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['weight' => 'DESC'])]
    private Collection $personnageLanguageLevels;

    public function __construct()
    {
        $this->sequencePersonnages = new ArrayCollection();
        $this->personnageDramaticFunctions = new ArrayCollection();
        $this->personnageNarrativeArcs = new ArrayCollection();
        $this->actantialSchemas = new ArrayCollection();
        $this->personnageLanguageLevels = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getBackground(): ?string
    {
        return $this->background;
    }

    public function setBackground(?string $background): self
    {
        $this->background = $background;
        return $this;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    public function getSequencePersonnages(): Collection
    {
        return $this->sequencePersonnages;
    }

    public function addSequencePersonnage(SequencePersonnage $sequencePersonnage): self
    {
        if (!$this->sequencePersonnages->contains($sequencePersonnage)) {
            $this->sequencePersonnages->add($sequencePersonnage);
            $sequencePersonnage->setPersonnage($this);
        }
        return $this;
    }

    public function removeSequencePersonnage(SequencePersonnage $sequencePersonnage): self
    {
        if ($this->sequencePersonnages->removeElement($sequencePersonnage)) {
            if ($sequencePersonnage->getPersonnage() === $this) {
                $sequencePersonnage->setPersonnage(null);
            }
        }
        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): self
    {
        $this->age = $age;
        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(?int $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getAnalysis(): ?string
    {
        return $this->analysis;
    }

    public function setAnalysis(?string $analysis): self
    {
        $this->analysis = $analysis;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getImages(): ?string
    {
        return $this->images;
    }

    public function setImages(?string $images): self
    {
        $this->images = $images;
        return $this;
    }

    public function getStrength(): ?string
    {
        return $this->strength;
    }

    public function setStrength(?string $strength): self
    {
        $this->strength = $strength;
        return $this;
    }

    public function getWeakness(): ?string
    {
        return $this->weakness;
    }

    public function setWeakness(?string $weakness): self
    {
        $this->weakness = $weakness;
        return $this;
    }

    /**
     * Get avatar (first image from images array)
     */
    #[Groups(["personnage:read", "sequence:read", "part:read", "project:read"])]
    public function getAvatarUrl(): ?string
    {
        if (!$this->images) {
            return null;
        }
        
        $imagesArray = json_decode($this->images, true);
        if (is_array($imagesArray) && !empty($imagesArray)) {
            return $imagesArray[0];
        }
        
        return null;
    }

    /**
     * Get images as array
     */
    public function getImagesArray(): array
    {
        if (!$this->images) {
            return [];
        }
        
        $imagesArray = json_decode($this->images, true);
        return is_array($imagesArray) ? $imagesArray : [];
    }

    /**
     * Set images from array
     */
    public function setImagesArray(array $images): self
    {
        $this->images = json_encode($images);
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * Generate and set slug from first and last name
     */
    public function generateSlug(): self
    {
        $this->slug = SlugUtils::createPersonnageSlug($this->firstName, $this->lastName);
        return $this;
    }

    /**
     * @return Collection<int, PersonnageDramaticFunction>
     */
    public function getPersonnageDramaticFunctions(): Collection
    {
        return $this->personnageDramaticFunctions;
    }

    public function addPersonnageDramaticFunction(PersonnageDramaticFunction $personnageDramaticFunction): self
    {
        if (!$this->personnageDramaticFunctions->contains($personnageDramaticFunction)) {
            $this->personnageDramaticFunctions->add($personnageDramaticFunction);
            $personnageDramaticFunction->setPersonnage($this);
        }

        return $this;
    }

    public function removePersonnageDramaticFunction(PersonnageDramaticFunction $personnageDramaticFunction): self
    {
        $this->personnageDramaticFunctions->removeElement($personnageDramaticFunction);

        return $this;
    }

    /**
     * @return Collection<int, PersonnageNarrativeArc>
     */
    public function getPersonnageNarrativeArcs(): Collection
    {
        return $this->personnageNarrativeArcs;
    }

    public function addPersonnageNarrativeArc(PersonnageNarrativeArc $personnageNarrativeArc): self
    {
        if (!$this->personnageNarrativeArcs->contains($personnageNarrativeArc)) {
            $this->personnageNarrativeArcs->add($personnageNarrativeArc);
            $personnageNarrativeArc->setPersonnage($this);
        }

        return $this;
    }

    public function removePersonnageNarrativeArc(PersonnageNarrativeArc $personnageNarrativeArc): self
    {
        $this->personnageNarrativeArcs->removeElement($personnageNarrativeArc);

        return $this;
    }

    /**
     * @return Collection<int, PersonnageActantialSchema>
     */
    public function getActantialSchemas(): Collection
    {
        return $this->actantialSchemas;
    }

    /**
     * Schéma couvrant tout le récit (sans bornes de séquences)
     */
    public function getGlobalActantialSchema(): ?PersonnageActantialSchema
    {
        foreach ($this->actantialSchemas as $schema) {
            if ($schema->getFromSequence() === null && $schema->getToSequence() === null) {
                return $schema;
            }
        }

        return null;
    }

    public function getVerification(): ?array
    {
        return $this->verification;
    }

    public function setVerification(?array $verification): self
    {
        $this->verification = $verification;
        return $this;
    }

    public function getLanguageAnalysis(): ?string
    {
        return $this->languageAnalysis;
    }

    public function setLanguageAnalysis(?string $languageAnalysis): self
    {
        $this->languageAnalysis = $languageAnalysis;
        return $this;
    }

    /**
     * @return Collection<int, PersonnageLanguageLevel>
     */
    public function getPersonnageLanguageLevels(): Collection
    {
        return $this->personnageLanguageLevels;
    }

    public function addPersonnageLanguageLevel(PersonnageLanguageLevel $level): self
    {
        if (!$this->personnageLanguageLevels->contains($level)) {
            $this->personnageLanguageLevels->add($level);
            $level->setPersonnage($this);
        }

        return $this;
    }

    public function removePersonnageLanguageLevel(PersonnageLanguageLevel $level): self
    {
        $this->personnageLanguageLevels->removeElement($level);

        return $this;
    }

    public function addActantialSchema(PersonnageActantialSchema $schema): self
    {
        if (!$this->actantialSchemas->contains($schema)) {
            $this->actantialSchemas->add($schema);
            $schema->setPersonnage($this);
        }

        return $this;
    }
}
