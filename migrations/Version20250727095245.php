<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250727095245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add tendency column to dramatic_function and narrative_arc tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dramatic_function ADD tendency VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE narrative_arc ADD tendency VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dramatic_function DROP tendency');
        $this->addSql('ALTER TABLE narrative_arc DROP tendency');
    }
}
