<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019155246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subgenre_event (event_id INT NOT NULL, subgenre_id INT NOT NULL, INDEX IDX_C92876D771F7E88B (event_id), INDEX IDX_C92876D749066E96 (subgenre_id), PRIMARY KEY(event_id, subgenre_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE subgenre_event ADD CONSTRAINT FK_C92876D771F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subgenre_event ADD CONSTRAINT FK_C92876D749066E96 FOREIGN KEY (subgenre_id) REFERENCES subgenre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA749066E96');
        $this->addSql('DROP INDEX IDX_3BAE0AA749066E96 ON event');
        $this->addSql('ALTER TABLE event ADD event_type_id INT DEFAULT NULL, DROP subgenre_id, DROP event_type');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7401B253C FOREIGN KEY (event_type_id) REFERENCES event_type (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7401B253C ON event (event_type_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7401B253C');
        $this->addSql('ALTER TABLE subgenre_event DROP FOREIGN KEY FK_C92876D771F7E88B');
        $this->addSql('ALTER TABLE subgenre_event DROP FOREIGN KEY FK_C92876D749066E96');
        $this->addSql('DROP TABLE subgenre_event');
        $this->addSql('DROP TABLE event_type');
        $this->addSql('DROP INDEX IDX_3BAE0AA7401B253C ON event');
        $this->addSql('ALTER TABLE event ADD subgenre_id INT NOT NULL, ADD event_type VARCHAR(50) DEFAULT NULL, DROP event_type_id');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA749066E96 FOREIGN KEY (subgenre_id) REFERENCES subgenre (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_3BAE0AA749066E96 ON event (subgenre_id)');
    }
}
