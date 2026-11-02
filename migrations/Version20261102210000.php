<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Analyse de structure : lecture de chaque séquence (mise en cache, avec
 * l'empreinte du texte lu) et synthèse rangée sur le projet.
 */
final class Version20261102210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute sequence.structure_reading et project.structure_verification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sequence ADD structure_reading JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD structure_verification JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sequence DROP structure_reading');
        $this->addSql('ALTER TABLE project DROP structure_verification');
    }
}
