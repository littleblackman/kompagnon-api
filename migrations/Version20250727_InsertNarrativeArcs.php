<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250727_InsertNarrativeArcs extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert initial narrative arcs data';
    }

    public function up(Schema $schema): void
    {
        $arcs = [
            [
                'name' => 'Arc de rédemption',
                'description' => "Un personnage fautif cherche à se racheter à travers un sacrifice ou une transformation.",
                'tendency' => 'positive',
                'steps' => [
                    "Faute ou échec initial",
                    "Déni ou fuite",
                    "Conséquences visibles",
                    "Effondrement ou isolement",
                    "Révélation et culpabilité",
                    "Acte de rédemption",
                    "Transformation morale ou émotionnelle"
                ]
            ],
            [
                'name' => 'Voyage du héros',
                'description' => "Le parcours initiatique classique où le héros traverse des épreuves et revient transformé.",
                'tendency' => 'positive',
                'steps' => [
                    "Monde ordinaire",
                    "Appel à l'aventure",
                    "Refus de l'appel",
                    "Rencontre du mentor",
                    "Franchissement du seuil",
                    "Épreuves, alliés et ennemis",
                    "Approche de la caverne",
                    "Ordalie",
                    "Récompense",
                    "Retour",
                    "Résurrection",
                    "Retour avec l'élixir"
                ]
            ],
            [
                'name' => 'Chute de grâce',
                'description' => "Un personnage puissant est déchu à cause de son orgueil ou de trahisons, avec ou sans rédemption.",
                'tendency' => 'negative',
                'steps' => [
                    "Puissance ou prestige",
                    "Faiblesse morale ou orgueil",
                    "Signes avant-coureurs ignorés",
                    "Crise ou trahison",
                    "Chute ou exil",
                    "Conséquences et punition",
                    "Réflexion ou acceptation"
                ]
            ],
            [
                'name' => 'Arc de désillusion',
                'description' => "Un personnage perd ses illusions et doit reconstruire son identité.",
                'tendency' => 'ambiguous',
                'steps' => [
                    "Idéalisme naïf",
                    "Premiers doutes",
                    "Contradictions apparentes",
                    "Prise de conscience brutale",
                    "Effondrement des croyances",
                    "Nouvelle vérité ou lucidité amère",
                    "Repositionnement identitaire"
                ]
            ],
            [
                'name' => 'Arc tragique',
                'description' => "Une force intérieure ou extérieure mène le personnage à sa perte inévitable.",
                'tendency' => 'negative',
                'steps' => [
                    "Noble intention ou force",
                    "Ascension ou réussite",
                    "Émergence du défaut fatal",
                    "Avertissements ignorés",
                    "Décision irréversible",
                    "Catastrophe",
                    "Mort ou destruction symbolique"
                ]
            ],
            [
                'name' => 'Arc de transformation',
                'description' => "Le personnage se transforme radicalement à travers des épreuves ou un cheminement intérieur.",
                'tendency' => 'positive',
                'steps' => [
                    "État stable ou bloqué",
                    "Perturbation ou appel",
                    "Résistance ou confusion",
                    "Exploration et essai",
                    "Point de rupture",
                    "Reconquête ou reconstruction",
                    "Nouvelle identité"
                ]
            ],
            [
                'name' => 'Arc initiatique',
                'description' => "Un personnage jeune ou naïf gagne en maturité après des expériences formatrices.",
                'tendency' => 'positive',
                'steps' => [
                    "Innocence ou ignorance",
                    "Confrontation à la réalité",
                    "Révolte ou fuite",
                    "Épreuve ou perte",
                    "Prise de conscience de soi",
                    "Acceptation de la responsabilité",
                    "Intégration adulte"
                ]
            ],
            [
                'name' => 'Arc de vengeance',
                'description' => "Un personnage poursuit une revanche, souvent au prix de son intégrité.",
                'tendency' => 'ambiguous',
                'steps' => [
                    "Perte ou injustice",
                    "Refus ou choc",
                    "Préparation ou enquête",
                    "Première vengeance",
                    "Escalade de la violence",
                    "Doute moral",
                    "Dénouement (destruction ou rédemption)"
                ]
            ],
            [
                'name' => 'Arc de corruption',
                'description' => "Un personnage abandonne ses valeurs initiales pour le pouvoir, le confort ou la survie.",
                'tendency' => 'negative',
                'steps' => [
                    "Bonnes intentions ou idéal",
                    "Exposition au pouvoir ou à la peur",
                    "Premier compromis",
                    "Érosion morale progressive",
                    "Ligne franchie",
                    "Corruption totale",
                    "Chute ou domination"
                ]
            ],
            [
                'name' => "Arc d'ascension",
                'description' => "Un personnage part de rien et grimpe vers le pouvoir ou la reconnaissance.",
                'tendency' => 'positive',
                'steps' => [
                    "Origine modeste",
                    "Première opportunité",
                    "Apprentissage ou talent révélé",
                    "Obstacle majeur",
                    "Percée décisive",
                    "Consolidation du pouvoir",
                    "Remise en question ou héritier"
                ]
            ],
            [
                'name' => "Arc d'amour et de perte",
                'description' => "Un personnage vit une grande histoire d'amour qui se termine dans la douleur, mais qui le transforme.",
                'tendency' => 'ambiguous',
                'steps' => [
                    "Rencontre ou ouverture",
                    "Connexion intense",
                    "Complication ou sacrifice",
                    "Rupture ou perte",
                    "Douleur ou isolement",
                    "Réminiscence ou souvenir",
                    "Apaisement ou nouvelle ouverture"
                ]
            ]
        ];

        foreach ($arcs as $arc) {
            $this->addSql(
                'INSERT INTO narrative_arc (name, description, tendency, steps) VALUES (:name, :description, :tendency, :steps)',
                [
                    'name' => $arc['name'],
                    'description' => $arc['description'],
                    'tendency' => $arc['tendency'],
                    'steps' => json_encode($arc['steps'], JSON_UNESCAPED_UNICODE)
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM narrative_arc WHERE name IN (
            "Arc de rédemption",
            "Voyage du héros",
            "Chute de grâce",
            "Arc de désillusion",
            "Arc tragique",
            "Arc de transformation",
            "Arc initiatique",
            "Arc de vengeance",
            "Arc de corruption",
            "Arc d\'ascension",
            "Arc d\'amour et de perte"
        )');
    }
}