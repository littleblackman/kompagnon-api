<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019183429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subgenre_narrative_structure (id INT AUTO_INCREMENT NOT NULL, subgenre_id INT NOT NULL, narrative_structure_id INT NOT NULL, recommended_percentage INT DEFAULT NULL, is_default TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_FBD9032149066E96 (subgenre_id), INDEX IDX_FBD903217313C24E (narrative_structure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE subgenre_narrative_structure ADD CONSTRAINT FK_FBD9032149066E96 FOREIGN KEY (subgenre_id) REFERENCES subgenre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subgenre_narrative_structure ADD CONSTRAINT FK_FBD903217313C24E FOREIGN KEY (narrative_structure_id) REFERENCES narrative_structure (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subgenre_narrative_structure DROP FOREIGN KEY FK_FBD9032149066E96');
        $this->addSql('ALTER TABLE subgenre_narrative_structure DROP FOREIGN KEY FK_FBD903217313C24E');
        $this->addSql('DROP TABLE subgenre_narrative_structure');
    }
}
