<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250727113606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE personnage_actantial_schema (id INT AUTO_INCREMENT NOT NULL, personnage_id INT NOT NULL, from_sequence_id INT DEFAULT NULL, to_sequence_id INT DEFAULT NULL, actantial_roles JSON NOT NULL, comment LONGTEXT DEFAULT NULL, INDEX IDX_1A3BDADC5E315342 (personnage_id), INDEX IDX_1A3BDADC6B428CB (from_sequence_id), INDEX IDX_1A3BDADCB66827CD (to_sequence_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE personnage_dramatic_function (id INT AUTO_INCREMENT NOT NULL, personnage_id INT NOT NULL, dramatic_function_id INT NOT NULL, from_sequence_id INT DEFAULT NULL, to_sequence_id INT DEFAULT NULL, weight INT NOT NULL, comment LONGTEXT DEFAULT NULL, INDEX IDX_618CE6895E315342 (personnage_id), INDEX IDX_618CE689C3533413 (dramatic_function_id), INDEX IDX_618CE6896B428CB (from_sequence_id), INDEX IDX_618CE689B66827CD (to_sequence_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE personnage_narrative_arc (id INT AUTO_INCREMENT NOT NULL, personnage_id INT NOT NULL, narrative_arc_id INT NOT NULL, from_sequence_id INT DEFAULT NULL, to_sequence_id INT DEFAULT NULL, weight INT NOT NULL, comment LONGTEXT DEFAULT NULL, INDEX IDX_9D08561C5E315342 (personnage_id), INDEX IDX_9D08561C31EC98B8 (narrative_arc_id), INDEX IDX_9D08561C6B428CB (from_sequence_id), INDEX IDX_9D08561CB66827CD (to_sequence_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE personnage_actantial_schema ADD CONSTRAINT FK_1A3BDADC5E315342 FOREIGN KEY (personnage_id) REFERENCES personnage (id)');
        $this->addSql('ALTER TABLE personnage_actantial_schema ADD CONSTRAINT FK_1A3BDADC6B428CB FOREIGN KEY (from_sequence_id) REFERENCES sequence (id)');
        $this->addSql('ALTER TABLE personnage_actantial_schema ADD CONSTRAINT FK_1A3BDADCB66827CD FOREIGN KEY (to_sequence_id) REFERENCES sequence (id)');
        $this->addSql('ALTER TABLE personnage_dramatic_function ADD CONSTRAINT FK_618CE6895E315342 FOREIGN KEY (personnage_id) REFERENCES personnage (id)');
        $this->addSql('ALTER TABLE personnage_dramatic_function ADD CONSTRAINT FK_618CE689C3533413 FOREIGN KEY (dramatic_function_id) REFERENCES dramatic_function (id)');
        $this->addSql('ALTER TABLE personnage_dramatic_function ADD CONSTRAINT FK_618CE6896B428CB FOREIGN KEY (from_sequence_id) REFERENCES sequence (id)');
        $this->addSql('ALTER TABLE personnage_dramatic_function ADD CONSTRAINT FK_618CE689B66827CD FOREIGN KEY (to_sequence_id) REFERENCES sequence (id)');
        $this->addSql('ALTER TABLE personnage_narrative_arc ADD CONSTRAINT FK_9D08561C5E315342 FOREIGN KEY (personnage_id) REFERENCES personnage (id)');
        $this->addSql('ALTER TABLE personnage_narrative_arc ADD CONSTRAINT FK_9D08561C31EC98B8 FOREIGN KEY (narrative_arc_id) REFERENCES narrative_arc (id)');
        $this->addSql('ALTER TABLE personnage_narrative_arc ADD CONSTRAINT FK_9D08561C6B428CB FOREIGN KEY (from_sequence_id) REFERENCES sequence (id)');
        $this->addSql('ALTER TABLE personnage_narrative_arc ADD CONSTRAINT FK_9D08561CB66827CD FOREIGN KEY (to_sequence_id) REFERENCES sequence (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE personnage_actantial_schema DROP FOREIGN KEY FK_1A3BDADC5E315342');
        $this->addSql('ALTER TABLE personnage_actantial_schema DROP FOREIGN KEY FK_1A3BDADC6B428CB');
        $this->addSql('ALTER TABLE personnage_actantial_schema DROP FOREIGN KEY FK_1A3BDADCB66827CD');
        $this->addSql('ALTER TABLE personnage_dramatic_function DROP FOREIGN KEY FK_618CE6895E315342');
        $this->addSql('ALTER TABLE personnage_dramatic_function DROP FOREIGN KEY FK_618CE689C3533413');
        $this->addSql('ALTER TABLE personnage_dramatic_function DROP FOREIGN KEY FK_618CE6896B428CB');
        $this->addSql('ALTER TABLE personnage_dramatic_function DROP FOREIGN KEY FK_618CE689B66827CD');
        $this->addSql('ALTER TABLE personnage_narrative_arc DROP FOREIGN KEY FK_9D08561C5E315342');
        $this->addSql('ALTER TABLE personnage_narrative_arc DROP FOREIGN KEY FK_9D08561C31EC98B8');
        $this->addSql('ALTER TABLE personnage_narrative_arc DROP FOREIGN KEY FK_9D08561C6B428CB');
        $this->addSql('ALTER TABLE personnage_narrative_arc DROP FOREIGN KEY FK_9D08561CB66827CD');
        $this->addSql('DROP TABLE personnage_actantial_schema');
        $this->addSql('DROP TABLE personnage_dramatic_function');
        $this->addSql('DROP TABLE personnage_narrative_arc');
    }
}
