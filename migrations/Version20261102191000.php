<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Todo de projet : tâches de l'auteur et tâches ajoutées par les analyses.
 */
final class Version20261102191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table project_todo (tâches du projet, dédoublonnées par empreinte)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE project_todo (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, sequence_id INT DEFAULT NULL, scene_id INT DEFAULT NULL, personnage_id INT DEFAULT NULL, label LONGTEXT NOT NULL, category VARCHAR(20) NOT NULL, source VARCHAR(10) NOT NULL, origin VARCHAR(50) DEFAULT NULL, dedupe_key VARCHAR(40) DEFAULT NULL, done TINYINT(1) NOT NULL, done_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', dismissed TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_639F7EB8166D1F9C (project_id), INDEX IDX_639F7EB898FB19AE (sequence_id), INDEX IDX_639F7EB8166053B4 (scene_id), INDEX IDX_639F7EB85E315342 (personnage_id), UNIQUE INDEX uniq_project_todo_dedupe (project_id, dedupe_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE project_todo ADD CONSTRAINT FK_639F7EB8166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_todo ADD CONSTRAINT FK_639F7EB898FB19AE FOREIGN KEY (sequence_id) REFERENCES sequence (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_todo ADD CONSTRAINT FK_639F7EB8166053B4 FOREIGN KEY (scene_id) REFERENCES scene (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE project_todo ADD CONSTRAINT FK_639F7EB85E315342 FOREIGN KEY (personnage_id) REFERENCES personnage (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project_todo');
    }
}
