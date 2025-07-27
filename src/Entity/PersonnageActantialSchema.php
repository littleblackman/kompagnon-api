<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'personnage_actantial_schema')]
class PersonnageActantialSchema
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Personnage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Personnage $personnage;

    #[ORM\ManyToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sequence $fromSequence = null;

    #[ORM\ManyToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sequence $toSequence = null;

    /**
     * Structure JSON des rôles actantiels :
     * {
     *   "Sujet": {
     *     "role": "Sujet",
     *     "concerned_name": "Harry Potter", 
     *     "concerned_personnage_id": 1,
     *     "comment": "Héros principal de l'histoire"
     *   },
     *   "Objet": {
     *     "role": "Objet",
     *     "concerned_name": "Pierre Philosophale",
     *     "concerned_personnage_id": null,
     *     "comment": "Objectif à atteindre"
     *   },
     *   "Adjuvant": {
     *     "role": "Adjuvant", 
     *     "concerned_name": "Hermione",
     *     "concerned_personnage_id": 2,
     *     "comment": "Aide précieuse"
     *   },
     *   "Opposant": {
     *     "role": "Opposant",
     *     "concerned_name": "Voldemort",
     *     "concerned_personnage_id": 3,
     *     "comment": null
     *   },
     *   "Destinateur": {
     *     "role": "Destinateur",
     *     "concerned_name": "Dumbledore",
     *     "concerned_personnage_id": 4,
     *     "comment": "Celui qui envoie en mission"
     *   },
     *   "Destinataire": {
     *     "role": "Destinataire",
     *     "concerned_name": "Le monde magique",
     *     "concerned_personnage_id": null,
     *     "comment": "Bénéficiaire de l'action"
     *   }
     * }
     */
    #[ORM\Column(type: 'json')]
    private array $actantialRoles = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPersonnage(): Personnage
    {
        return $this->personnage;
    }

    public function setPersonnage(Personnage $personnage): self
    {
        $this->personnage = $personnage;
        return $this;
    }

    public function getFromSequence(): ?Sequence
    {
        return $this->fromSequence;
    }

    public function setFromSequence(?Sequence $fromSequence): self
    {
        $this->fromSequence = $fromSequence;
        return $this;
    }

    public function getToSequence(): ?Sequence
    {
        return $this->toSequence;
    }

    public function setToSequence(?Sequence $toSequence): self
    {
        $this->toSequence = $toSequence;
        return $this;
    }

    public function getActantialRoles(): array
    {
        return $this->actantialRoles;
    }

    public function setActantialRoles(array $actantialRoles): self
    {
        $this->actantialRoles = $actantialRoles;
        return $this;
    }

    /**
     * Ajoute ou met à jour un rôle actantiel
     * 
     * @param string $role Le rôle (Sujet, Objet, Adjuvant, Opposant, Destinateur, Destinataire)
     * @param string $concernedName Le nom de la personne/lieu/chose concerné
     * @param int|null $concernedPersonnageId L'ID du personnage si c'est un personnage
     * @param string|null $comment Commentaire optionnel
     */
    public function setActantialRole(string $role, string $concernedName, ?int $concernedPersonnageId = null, ?string $comment = null): self
    {
        $this->actantialRoles[$role] = [
            'role' => $role,
            'concerned_name' => $concernedName,
            'concerned_personnage_id' => $concernedPersonnageId,
            'comment' => $comment
        ];
        
        return $this;
    }

    /**
     * Récupère un rôle actantiel spécifique
     */
    public function getActantialRole(string $role): ?array
    {
        return $this->actantialRoles[$role] ?? null;
    }

    /**
     * Supprime un rôle actantiel
     */
    public function removeActantialRole(string $role): self
    {
        unset($this->actantialRoles[$role]);
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }
}