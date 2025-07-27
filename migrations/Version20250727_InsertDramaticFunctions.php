<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250727_InsertDramaticFunctions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert initial dramatic functions data';
    }

    public function up(Schema $schema): void
    {
        $functions = [
            [
                'name' => 'Héros',
                'description' => "Le personnage principal qui évolue à travers des épreuves et porte l'arc central du récit.",
                'tendency' => 'positive',
                'characteristics' => [
                    "Proactif",
                    "En quête de sens",
                    "Transformé par le récit",
                    "Relatif au point de vue principal"
                ]
            ],
            [
                'name' => 'Mentor',
                'description' => "Guide ou conseiller qui éclaire le héros et l'aide à franchir des étapes critiques.",
                'tendency' => 'positive',
                'characteristics' => [
                    "Sage ou expérimenté",
                    "Parfois sacrificiel",
                    "Porteur de valeurs",
                    "Sert de miroir ou d'alerte"
                ]
            ],
            [
                'name' => 'Gardien du seuil',
                'description' => "Personnage ou obstacle qui empêche l'évolution du héros avant qu'il ne soit prêt.",
                'tendency' => 'ambiguous',
                'characteristics' => [
                    "Obstructeur initial",
                    "Met à l'épreuve",
                    "Peut se transformer",
                    "Souvent perçu comme ennemi à tort"
                ]
            ],
            [
                'name' => 'Ombre',
                'description' => "Figure antagoniste ou reflet négatif du héros, représentant souvent ses peurs ou failles.",
                'tendency' => 'negative',
                'characteristics' => [
                    "Opposition frontale ou subtile",
                    "Porte les contradictions du héros",
                    "Peut fasciner ou séduire",
                    "Parfois tragiquement humain"
                ]
            ],
            [
                'name' => 'Faux allié',
                'description' => "Personnage qui se présente comme un soutien mais trahit ou déstabilise le héros.",
                'tendency' => 'negative',
                'characteristics' => [
                    "Ambigu ou manipulateur",
                    "Changeant ou imprévisible",
                    "Pousse le héros à se méfier",
                    "Peut agir par intérêt personnel"
                ]
            ],
            [
                'name' => 'Reflet',
                'description' => "Double dramatique du héros, incarnant une autre voie possible, opposée ou complémentaire.",
                'tendency' => 'ambiguous',
                'characteristics' => [
                    "Similaire au héros dans ses origines ou son désir",
                    "Choix opposés ou valeurs divergentes",
                    "Sert à révéler le cœur du conflit intérieur",
                    "Évolution contrastée"
                ]
            ],
            [
                'name' => 'Trickster',
                'description' => "Personnage instable, drôle ou provocateur, qui apporte le chaos ou l'éveil.",
                'tendency' => 'ambiguous',
                'characteristics' => [
                    "Déjoue les attentes",
                    "Peut être comique ou cynique",
                    "Fait avancer le récit par le trouble",
                    "Souvent imprévisible mais lucide"
                ]
            ],
            [
                'name' => 'Sauveur sacrificiel',
                'description' => "Personnage secondaire qui se sacrifie pour permettre au héros d'évoluer ou survivre.",
                'tendency' => 'positive',
                'characteristics' => [
                    "Dévoué ou silencieux",
                    "Souvent discret au début",
                    "Acte de don final",
                    "Catalyseur de transformation pour les autres"
                ]
            ],
            [
                'name' => 'Prophète',
                'description' => "Personnage porteur d'une vision, d'un avertissement ou d'une vérité ignorée.",
                'tendency' => 'ambiguous',
                'characteristics' => [
                    "Visionnaire ou délirant",
                    "Souvent marginalisé",
                    "Annonce une crise ou un basculement",
                    "Incompris jusqu'à la fin"
                ]
            ],
            [
                'name' => 'Bouffon',
                'description' => "Personnage comique ou grotesque qui révèle les vérités cachées par le rire.",
                'tendency' => 'ambiguous',
                'characteristics' => [
                    "Léger en surface, profond en sous-texte",
                    "Porte une critique sociale",
                    "Souvent ignoré mais lucide",
                    "Cache parfois une douleur"
                ]
            ],
            [
                'name' => 'Victime sacrificielle',
                'description' => "Personnage sans pouvoir, souvent victime du système, dont la souffrance déclenche une transformation.",
                'tendency' => 'positive',
                'characteristics' => [
                    "Apparemment passif",
                    "Souffre d'une injustice",
                    "Fait réagir le héros ou la communauté",
                    "Mène à une prise de conscience collective"
                ]
            ],
            [
                'name' => 'Catalyseur',
                'description' => "Personnage secondaire qui provoque un changement majeur sans évoluer lui-même.",
                'tendency' => 'ambiguous',
                'characteristics' => [
                    "Stable ou figé",
                    "Déclencheur d'actions",
                    "Peut être moralement neutre",
                    "Souvent oublié après son effet"
                ]
            ],
            [
                'name' => 'Tentateur',
                'description' => "Figure de séduction, de pouvoir ou de facilité qui détourne le héros de sa voie.",
                'tendency' => 'negative',
                'characteristics' => [
                    "Séducteur ou fascinant",
                    "Offre une solution rapide",
                    "Pousse à renier ses principes",
                    "Parfois sincère dans ses intentions"
                ]
            ],
            [
                'name' => 'Témoin',
                'description' => "Personnage qui ne décide pas, mais observe et porte la mémoire ou la narration.",
                'tendency' => 'ambiguous',
                'characteristics' => [
                    "Position extérieure",
                    "Conscience morale ou chroniqueur",
                    "Peut survivre au héros",
                    "Raconte l'histoire plus qu'il ne l'influe"
                ]
            ],
            [
                'name' => 'Survivant',
                'description' => "Personnage qui endure plus qu'il ne combat, et qui incarne la résilience.",
                'tendency' => 'positive',
                'characteristics' => [
                    "Silencieux ou fatigué",
                    "Témoin de la brutalité du monde",
                    "Refuse parfois d'agir",
                    "Montre la puissance de la persistance"
                ]
            ],
            [
                'name' => 'Enfant sacré',
                'description' => "Personnage fragile mais porteur d'une puissance symbolique ou d'un avenir.",
                'tendency' => 'positive',
                'characteristics' => [
                    "Innocent ou pur",
                    "Protégé ou poursuivi",
                    "Symbole d'espoir ou de renouveau",
                    "Souvent muet ou mystérieux"
                ]
            ]
        ];

        foreach ($functions as $function) {
            $this->addSql(
                'INSERT INTO dramatic_function (name, description, tendency, characteristics) VALUES (:name, :description, :tendency, :characteristics)',
                [
                    'name' => $function['name'],
                    'description' => $function['description'],
                    'tendency' => $function['tendency'],
                    'characteristics' => json_encode($function['characteristics'], JSON_UNESCAPED_UNICODE)
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM dramatic_function WHERE name IN (
            "Héros",
            "Mentor",
            "Gardien du seuil",
            "Ombre",
            "Faux allié",
            "Reflet",
            "Trickster",
            "Sauveur sacrificiel",
            "Prophète",
            "Bouffon",
            "Victime sacrificielle",
            "Catalyseur",
            "Tentateur",
            "Témoin",
            "Survivant",
            "Enfant sacré"
        )');
    }
}