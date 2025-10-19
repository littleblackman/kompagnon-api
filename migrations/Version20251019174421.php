<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019174421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subgenre_dramatic_function (id INT AUTO_INCREMENT NOT NULL, subgenre_id INT NOT NULL, dramatic_function_id INT NOT NULL, is_essential TINYINT(1) DEFAULT 0 NOT NULL, typical_count VARCHAR(20) DEFAULT NULL, INDEX IDX_35484C8849066E96 (subgenre_id), INDEX IDX_35484C88C3533413 (dramatic_function_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE subgenre_dramatic_function ADD CONSTRAINT FK_35484C8849066E96 FOREIGN KEY (subgenre_id) REFERENCES subgenre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subgenre_dramatic_function ADD CONSTRAINT FK_35484C88C3533413 FOREIGN KEY (dramatic_function_id) REFERENCES dramatic_function (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subgenre_dramatic_function DROP FOREIGN KEY FK_35484C8849066E96');
        $this->addSql('ALTER TABLE subgenre_dramatic_function DROP FOREIGN KEY FK_35484C88C3533413');
        $this->addSql('DROP TABLE subgenre_dramatic_function');
    }
}
