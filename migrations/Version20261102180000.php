<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Analyse des arcs d'un personnage (verdicts par étape, arcs détectés),
 * rangée côté analyse : la fiche de l'auteur n'est jamais réécrite.
 */
final class Version20261102180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute personnage.arc_analysis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personnage ADD arc_analysis JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personnage DROP arc_analysis');
    }
}
