<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019152349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, subgenre_id INT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, event_type VARCHAR(50) DEFAULT NULL, INDEX IDX_3BAE0AA749066E96 (subgenre_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE genre (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE narrative_structure (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, total_beats INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE narrative_structure_event (id INT AUTO_INCREMENT NOT NULL, narrative_structure_id INT NOT NULL, event_id INT NOT NULL, position INT NOT NULL, is_optional TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_25DD056B7313C24E (narrative_structure_id), INDEX IDX_25DD056B71F7E88B (event_id), INDEX idx_structure_position (narrative_structure_id, position), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE subgenre (id INT AUTO_INCREMENT NOT NULL, genre_id INT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_F9E3DC224296D31F (genre_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA749066E96 FOREIGN KEY (subgenre_id) REFERENCES subgenre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE narrative_structure_event ADD CONSTRAINT FK_25DD056B7313C24E FOREIGN KEY (narrative_structure_id) REFERENCES narrative_structure (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE narrative_structure_event ADD CONSTRAINT FK_25DD056B71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subgenre ADD CONSTRAINT FK_F9E3DC224296D31F FOREIGN KEY (genre_id) REFERENCES genre (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA749066E96');
        $this->addSql('ALTER TABLE narrative_structure_event DROP FOREIGN KEY FK_25DD056B7313C24E');
        $this->addSql('ALTER TABLE narrative_structure_event DROP FOREIGN KEY FK_25DD056B71F7E88B');
        $this->addSql('ALTER TABLE subgenre DROP FOREIGN KEY FK_F9E3DC224296D31F');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE genre');
        $this->addSql('DROP TABLE narrative_structure');
        $this->addSql('DROP TABLE narrative_structure_event');
        $this->addSql('DROP TABLE subgenre');
    }
}
