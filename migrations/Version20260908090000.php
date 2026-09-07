<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Note de travail sur une scène.
 */
final class Version20260908090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ notes sur scene';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scene ADD notes LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scene DROP notes');
    }
}
