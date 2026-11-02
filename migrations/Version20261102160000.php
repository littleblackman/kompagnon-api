<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Niveaux de langage : le référentiel et son rattachement aux personnages.
 */
final class Version20261102160000 extends AbstractMigration
{
    private const LEVELS = [
        ['Soutenu', 'Langue recherchée et maîtrisée, syntaxe complète, vocabulaire choisi.', ['Subjonctif imparfait', 'Inversion du sujet', 'Vocabulaire rare', 'Pas d\'élision']],
        ['Courant', 'Langue standard, correcte sans être recherchée.', ['Phrases complètes', 'Vocabulaire usuel', 'Négation complète']],
        ['Familier', 'Langue relâchée de l\'oral détendu, entre proches.', ['Négation sans « ne »', 'Élisions (« t\'es »)', '« On » pour « nous »', 'Mots familiers (bouquin, boulot)']],
        ['Populaire', 'Tournures hors de la norme grammaticale, marquées socialement.', ['Fautes d\'accord assumées', '« Si j\'aurais »', 'Redoublement du sujet (« mon père, il… »)']],
        ['Argotique', 'Argot, verlan, vocabulaire d\'un milieu ou d\'une génération.', ['Verlan', 'Argot de milieu', 'Néologismes', 'Apocopes (« ouf », « chelou »)']],
        ['Vulgaire', 'Grossièretés et jurons comme marque de la parole.', ['Jurons', 'Insultes', 'Termes crus']],
        ['Précieux', 'Langue affectée, maniérée, qui se regarde parler.', ['Périphrases', 'Superlatifs', 'Anglicismes ou latinismes de prestige']],
        ['Littéraire / poétique', 'Parole imagée, rythmée, proche de l\'écrit.', ['Métaphores', 'Rythme travaillé', 'Lexique abstrait']],
        ['Technique / jargon', 'Vocabulaire d\'un métier ou d\'une spécialité.', ['Termes spécialisés', 'Sigles', 'Précision lexicale']],
        ['Administratif / formel', 'Langue institutionnelle, impersonnelle, parfois langue de bois.', ['Tournures passives', 'Nominalisations', 'Formules toutes faites']],
        ['Archaïque', 'Tournures et mots d\'une autre époque.', ['Vocabulaire désuet', 'Vouvoiement systématique', 'Formules anciennes']],
        ['Régional / dialectal', 'Régionalismes, accent ou parler local transcrits.', ['Régionalismes', 'Accent transcrit', 'Mots de patois']],
        ['Enfantin', 'Parole d\'enfant ou adressée à un enfant.', ['Syntaxe simple', 'Répétitions', 'Mots inventés ou déformés']],
        ['Mixte / bilingue', 'Alternance entre deux langues dans la même parole.', ['Mots étrangers insérés', 'Changement de langue en cours de phrase', 'Calques']],
    ];

    public function getDescription(): string
    {
        return 'Niveaux de langage (référentiel + rattachement aux personnages) et analyse du langage du personnage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE language_level (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, markers JSON NOT NULL, position INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE personnage_language_level (id INT AUTO_INCREMENT NOT NULL, personnage_id INT NOT NULL, language_level_id INT NOT NULL, weight INT NOT NULL, comment LONGTEXT DEFAULT NULL, quote LONGTEXT DEFAULT NULL, INDEX IDX_A3B856E35E315342 (personnage_id), INDEX IDX_A3B856E33313139D (language_level_id), UNIQUE INDEX uniq_personnage_language_level (personnage_id, language_level_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE personnage_language_level ADD CONSTRAINT FK_A3B856E35E315342 FOREIGN KEY (personnage_id) REFERENCES personnage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personnage_language_level ADD CONSTRAINT FK_A3B856E33313139D FOREIGN KEY (language_level_id) REFERENCES language_level (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE personnage ADD language_analysis LONGTEXT DEFAULT NULL');

        foreach (self::LEVELS as $position => [$name, $description, $markers]) {
            $this->addSql(
                'INSERT INTO language_level (name, description, markers, position) VALUES (?, ?, ?, ?)',
                [$name, $description, json_encode($markers, JSON_UNESCAPED_UNICODE), $position + 1]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personnage DROP language_analysis');
        $this->addSql('DROP TABLE personnage_language_level');
        $this->addSql('DROP TABLE language_level');
    }
}
