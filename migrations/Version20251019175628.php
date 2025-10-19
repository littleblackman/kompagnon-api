<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019175628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dramatic_function_narrative_arc (dramatic_function_id INT NOT NULL, narrative_arc_id INT NOT NULL, INDEX IDX_D11343F3C3533413 (dramatic_function_id), INDEX IDX_D11343F331EC98B8 (narrative_arc_id), PRIMARY KEY(dramatic_function_id, narrative_arc_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE dramatic_function_narrative_arc ADD CONSTRAINT FK_D11343F3C3533413 FOREIGN KEY (dramatic_function_id) REFERENCES dramatic_function (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE dramatic_function_narrative_arc ADD CONSTRAINT FK_D11343F331EC98B8 FOREIGN KEY (narrative_arc_id) REFERENCES narrative_arc (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dramatic_function_narrative_arc DROP FOREIGN KEY FK_D11343F3C3533413');
        $this->addSql('ALTER TABLE dramatic_function_narrative_arc DROP FOREIGN KEY FK_D11343F331EC98B8');
        $this->addSql('DROP TABLE dramatic_function_narrative_arc');
    }
}
