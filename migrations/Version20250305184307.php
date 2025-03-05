<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250305184307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `character` (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, background LONGTEXT DEFAULT NULL, age INT DEFAULT NULL, origin VARCHAR(255) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, images LONGTEXT DEFAULT NULL, level INT DEFAULT NULL, analysis LONGTEXT DEFAULT NULL, strength VARCHAR(255) DEFAULT NULL, weakness VARCHAR(255) DEFAULT NULL, INDEX IDX_937AB034166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE criteria (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, explanation LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sequence_character (id INT AUTO_INCREMENT NOT NULL, sequence_id INT NOT NULL, character_id INT NOT NULL, INDEX IDX_3823B13D98FB19AE (sequence_id), INDEX IDX_3823B13D1136BE75 (character_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sequence_criteria (id INT AUTO_INCREMENT NOT NULL, sequence_id INT NOT NULL, criteria_id INT NOT NULL, rating INT NOT NULL, INDEX IDX_3E23CBAE98FB19AE (sequence_id), INDEX IDX_3E23CBAE990BEA15 (criteria_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `character` ADD CONSTRAINT FK_937AB034166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE sequence_character ADD CONSTRAINT FK_3823B13D98FB19AE FOREIGN KEY (sequence_id) REFERENCES sequence (id)');
        $this->addSql('ALTER TABLE sequence_character ADD CONSTRAINT FK_3823B13D1136BE75 FOREIGN KEY (character_id) REFERENCES `character` (id)');
        $this->addSql('ALTER TABLE sequence_criteria ADD CONSTRAINT FK_3E23CBAE98FB19AE FOREIGN KEY (sequence_id) REFERENCES sequence (id)');
        $this->addSql('ALTER TABLE sequence_criteria ADD CONSTRAINT FK_3E23CBAE990BEA15 FOREIGN KEY (criteria_id) REFERENCES criteria (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE989D9B62 ON project (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `character` DROP FOREIGN KEY FK_937AB034166D1F9C');
        $this->addSql('ALTER TABLE sequence_character DROP FOREIGN KEY FK_3823B13D98FB19AE');
        $this->addSql('ALTER TABLE sequence_character DROP FOREIGN KEY FK_3823B13D1136BE75');
        $this->addSql('ALTER TABLE sequence_criteria DROP FOREIGN KEY FK_3E23CBAE98FB19AE');
        $this->addSql('ALTER TABLE sequence_criteria DROP FOREIGN KEY FK_3E23CBAE990BEA15');
        $this->addSql('DROP TABLE `character`');
        $this->addSql('DROP TABLE criteria');
        $this->addSql('DROP TABLE sequence_character');
        $this->addSql('DROP TABLE sequence_criteria');
        $this->addSql('DROP INDEX UNIQ_2FB3D0EE989D9B62 ON project');
    }
}
