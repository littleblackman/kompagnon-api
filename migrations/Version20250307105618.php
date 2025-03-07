<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250307105618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE personnage (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, background LONGTEXT DEFAULT NULL, age INT DEFAULT NULL, origin VARCHAR(255) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, images LONGTEXT DEFAULT NULL, level INT DEFAULT NULL, analysis LONGTEXT DEFAULT NULL, strength VARCHAR(255) DEFAULT NULL, weakness VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, INDEX IDX_6AEA486D166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sequence_personnage (id INT AUTO_INCREMENT NOT NULL, sequence_id INT NOT NULL, personnage_id INT NOT NULL, INDEX IDX_139DA9C898FB19AE (sequence_id), INDEX IDX_139DA9C85E315342 (personnage_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE personnage ADD CONSTRAINT FK_6AEA486D166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE sequence_personnage ADD CONSTRAINT FK_139DA9C898FB19AE FOREIGN KEY (sequence_id) REFERENCES sequence (id)');
        $this->addSql('ALTER TABLE sequence_personnage ADD CONSTRAINT FK_139DA9C85E315342 FOREIGN KEY (personnage_id) REFERENCES personnage (id)');
        $this->addSql('ALTER TABLE `character` DROP FOREIGN KEY FK_937AB034166D1F9C');
        $this->addSql('ALTER TABLE sequence_character DROP FOREIGN KEY FK_3823B13D1136BE75');
        $this->addSql('ALTER TABLE sequence_character DROP FOREIGN KEY FK_3823B13D98FB19AE');
        $this->addSql('DROP TABLE `character`');
        $this->addSql('DROP TABLE sequence_character');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `character` (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, first_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, last_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, background LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, age INT DEFAULT NULL, origin VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, avatar VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, images LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, level INT DEFAULT NULL, analysis LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, strength VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, weakness VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, updated_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_937AB034166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE sequence_character (id INT AUTO_INCREMENT NOT NULL, sequence_id INT NOT NULL, character_id INT NOT NULL, INDEX IDX_3823B13D98FB19AE (sequence_id), INDEX IDX_3823B13D1136BE75 (character_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE `character` ADD CONSTRAINT FK_937AB034166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE sequence_character ADD CONSTRAINT FK_3823B13D1136BE75 FOREIGN KEY (character_id) REFERENCES `character` (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE sequence_character ADD CONSTRAINT FK_3823B13D98FB19AE FOREIGN KEY (sequence_id) REFERENCES sequence (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE personnage DROP FOREIGN KEY FK_6AEA486D166D1F9C');
        $this->addSql('ALTER TABLE sequence_personnage DROP FOREIGN KEY FK_139DA9C898FB19AE');
        $this->addSql('ALTER TABLE sequence_personnage DROP FOREIGN KEY FK_139DA9C85E315342');
        $this->addSql('DROP TABLE personnage');
        $this->addSql('DROP TABLE sequence_personnage');
    }
}
