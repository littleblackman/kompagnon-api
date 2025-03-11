<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250311174020 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE part CHANGE `order` position INT NOT NULL');
        $this->addSql('ALTER TABLE scene CHANGE `order` position INT NOT NULL');
        $this->addSql('ALTER TABLE sequence CHANGE `order` position INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE part CHANGE position `order` INT NOT NULL');
        $this->addSql('ALTER TABLE sequence CHANGE position `order` INT NOT NULL');
        $this->addSql('ALTER TABLE scene CHANGE position `order` INT NOT NULL');
    }
}
