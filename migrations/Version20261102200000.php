<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'analyse des arcs devient la dernière vérification complète du personnage
 * (arcs, fonctions dramatiques, écarts avec la fiche). L'origine, jamais
 * saisissable, disparaît.
 */
final class Version20261102200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'personnage.arc_analysis → personnage.verification, suppression de personnage.origin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personnage RENAME COLUMN arc_analysis TO verification');
        $this->addSql('ALTER TABLE personnage DROP origin');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personnage ADD origin VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE personnage RENAME COLUMN verification TO arc_analysis');
    }
}
