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

    #[ORM\ManyToOne(targetEntity: Personnage::class, inversedBy: 'actantialSchemas')]
    #[ORM\JoinColumn(nullable: false)]
    private Personnage $personnage;

    #[ORM\ManyToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sequence $fromSequence = null;

    #[ORM\ManyToOne(targetEntity: Sequence::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Sequence $toSequence = null;

    /**
     * Structure JSON des rôles actantiels, indexée par rôle. Un actant peut
     * être tenu par plusieurs entités ; personnage_id est renseigné quand
     * l'entité est un personnage du projet :
     * {
     *   "Sujet": {
     *     "role": "Sujet",
     *     "concerned": [{"name": "Harry Potter", "personnage_id": 1}],
     *     "comment": "Veut découvrir ce que cache le troisième étage"
     *   },
     *   "Adjuvant": {
     *     "role": "Adjuvant",
     *     "concerned": [
     *       {"name": "Hermione", "personnage_id": 2},
     *       {"name": "Ron", "personnage_id": 5}
     *     ],
     *     "comment": "Aide précieuse"
     *   },
     *   "Destinataire": {
     *     "role": "Destinataire",
     *     "concerned": [{"name": "Le monde magique", "personnage_id": null}],
     *     "comment": null
     *   },
     *   ...
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
     * @param array $concerned Liste de ['name' => string, 'personnage_id' => ?int]
     * @param string|null $comment Commentaire optionnel
     */
    public function setActantialRole(string $role, array $concerned, ?string $comment = null): self
    {
        $this->actantialRoles[$role] = [
            'role' => $role,
            'concerned' => $concerned,
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

    /**
     * Forme exposée au front
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'roles' => $this->actantialRoles,
            'comment' => $this->comment,
        ];
    }
}