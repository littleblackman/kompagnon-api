<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250727123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne variants aux arcs narratifs et injecte les variantes pour chaque arc (1 à 11)';
    }

    public function up(Schema $schema): void
    {

        $variants = [
            1 => [
                ['name' => 'Rédemption sacrificielle', 'description' => 'Le personnage se rachète en donnant sa vie ou en renonçant à ce qui comptait le plus.'],
                ['name' => 'Rédemption incomplète', 'description' => 'Le personnage cherche à se racheter, mais la société ou lui-même refuse son pardon.']
            ],
            2 => [
                ['name' => 'Héros réticent', 'description' => 'Le personnage refuse longuement son destin avant d’y céder.'],
                ['name' => 'Anti-héros', 'description' => 'Le parcours suit les étapes du héros, mais avec des motivations égoïstes ou sombres.']
            ],
            3 => [
                ['name' => 'Chute brutale', 'description' => 'Le basculement est rapide et irréversible dès le premier acte.'],
                ['name' => 'Chute en conscience', 'description' => 'Le personnage choisit sciemment sa propre perte.']
            ],
            4 => [
                ['name' => 'Désillusion politique', 'description' => 'Le personnage perd foi en un système ou une idéologie collective.'],
                ['name' => 'Désillusion intime', 'description' => 'La révélation concerne une figure aimée ou un mentor.']
            ],
            5 => [
                ['name' => 'Tragédie volontaire', 'description' => 'Le personnage choisit sa propre fin, au nom d’un idéal.'],
                ['name' => 'Tragédie du silence', 'description' => 'Le héros ne s’exprime jamais, et tout échoue par non-dit.']
            ],
            6 => [
                ['name' => 'Transformation lente', 'description' => 'La mutation se fait graduellement sur plusieurs niveaux (social, intime, psychologique).'],
                ['name' => 'Transformation explosive', 'description' => 'Un événement déclenche une métamorphose immédiate et radicale.']
            ],
            7 => [
                ['name' => 'Initiation spirituelle', 'description' => 'Le personnage découvre sa propre voix intérieure ou vocation mystique.'],
                ['name' => 'Initiation guerrière', 'description' => 'La maturité se fait par le combat et la survie.']
            ],
            8 => [
                ['name' => 'Vengeance froide', 'description' => 'Le personnage planifie sa revanche pendant des années avec sang-froid.'],
                ['name' => 'Vengeance cathartique', 'description' => 'L’acte final détruit à la fois la cible et le vengeur.']
            ],
            9 => [
                ['name' => 'Corruption par survie', 'description' => 'Le personnage cède face à la peur ou au besoin de protection.'],
                ['name' => 'Corruption douce', 'description' => 'Le glissement se fait presque à l’insu du personnage.']
            ],
            10 => [
                ['name' => 'Ascension révolutionnaire', 'description' => 'Le personnage prend le pouvoir en brisant les règles établies.'],
                ['name' => 'Ascension programmée', 'description' => 'Le personnage est désigné, préparé dès l’enfance à son rôle.']
            ],
            11 => [
                ['name' => 'Perte irréparable', 'description' => 'Le deuil est définitif et ne sera jamais guéri.'],
                ['name' => 'Perte libératrice', 'description' => 'La rupture permet de devenir soi-même, malgré la souffrance.']
            ],
        ];

        foreach ($variants as $id => $variantList) {
            $json = json_encode($variantList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->addSql("UPDATE narrative_arc SET variants = '$json' WHERE id = $id");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE narrative_arc DROP variants');
    }
}
