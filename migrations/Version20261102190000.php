<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Chronologie : séquences datées, faits, incohérences et repères A0 / J0.
 */
final class Version20261102190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chronologie du récit (timeline_sequence, timeline_fact, timeline_issue) et project.timeline_settings';
    }

    public function up(Schema $schema): void
    {
        $date = 'year INT DEFAULT NULL, month INT DEFAULT NULL, day INT DEFAULT NULL, approximate TINYINT(1) NOT NULL, date_precision VARCHAR(20) NOT NULL, time VARCHAR(30) DEFAULT NULL, weekday VARCHAR(10) DEFAULT NULL, anchor LONGTEXT DEFAULT NULL';
        $table = 'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';

        $this->addSql("CREATE TABLE timeline_sequence (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, sequence_id INT NOT NULL, place VARCHAR(255) DEFAULT NULL, who JSON NOT NULL, summary LONGTEXT DEFAULT NULL, quote LONGTEXT DEFAULT NULL, status VARCHAR(10) NOT NULL, extracted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', $date, INDEX IDX_2B68C20166D1F9C (project_id), UNIQUE INDEX UNIQ_2B68C2098FB19AE (sequence_id), PRIMARY KEY(id)) $table");
        $this->addSql("CREATE TABLE timeline_fact (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, sequence_id INT DEFAULT NULL, personnage_id INT DEFAULT NULL, label LONGTEXT NOT NULL, who JSON NOT NULL, layer VARCHAR(10) NOT NULL, nature VARCHAR(10) NOT NULL, quote LONGTEXT DEFAULT NULL, status VARCHAR(10) NOT NULL, text_check VARCHAR(15) DEFAULT NULL, origin VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', $date, INDEX IDX_6EACAEE0166D1F9C (project_id), INDEX IDX_6EACAEE098FB19AE (sequence_id), INDEX IDX_6EACAEE05E315342 (personnage_id), PRIMARY KEY(id)) $table");
        $this->addSql("CREATE TABLE timeline_issue (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, severity VARCHAR(10) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, fix_proposal LONGTEXT DEFAULT NULL, status VARCHAR(10) NOT NULL, dedupe_key VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_32C3AE78166D1F9C (project_id), PRIMARY KEY(id)) $table");
        $this->addSql("CREATE TABLE timeline_issue_fact (timeline_issue_id INT NOT NULL, timeline_fact_id INT NOT NULL, INDEX IDX_280E92DEE4AFE87 (timeline_issue_id), INDEX IDX_280E92DE20476B5A (timeline_fact_id), PRIMARY KEY(timeline_issue_id, timeline_fact_id)) $table");
        $this->addSql("CREATE TABLE timeline_issue_sequence (timeline_issue_id INT NOT NULL, timeline_sequence_id INT NOT NULL, INDEX IDX_3CBD2588E4AFE87 (timeline_issue_id), INDEX IDX_3CBD2588EB38422C (timeline_sequence_id), PRIMARY KEY(timeline_issue_id, timeline_sequence_id)) $table");

        $this->addSql('ALTER TABLE timeline_sequence ADD CONSTRAINT FK_2B68C20166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timeline_sequence ADD CONSTRAINT FK_2B68C2098FB19AE FOREIGN KEY (sequence_id) REFERENCES sequence (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timeline_fact ADD CONSTRAINT FK_6EACAEE0166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timeline_fact ADD CONSTRAINT FK_6EACAEE098FB19AE FOREIGN KEY (sequence_id) REFERENCES sequence (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timeline_fact ADD CONSTRAINT FK_6EACAEE05E315342 FOREIGN KEY (personnage_id) REFERENCES personnage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timeline_issue ADD CONSTRAINT FK_32C3AE78166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timeline_issue_fact ADD CONSTRAINT FK_280E92DEE4AFE87 FOREIGN KEY (timeline_issue_id) REFERENCES timeline_issue (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timeline_issue_fact ADD CONSTRAINT FK_280E92DE20476B5A FOREIGN KEY (timeline_fact_id) REFERENCES timeline_fact (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timeline_issue_sequence ADD CONSTRAINT FK_3CBD2588E4AFE87 FOREIGN KEY (timeline_issue_id) REFERENCES timeline_issue (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timeline_issue_sequence ADD CONSTRAINT FK_3CBD2588EB38422C FOREIGN KEY (timeline_sequence_id) REFERENCES timeline_sequence (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE project ADD timeline_settings JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP timeline_settings');
        $this->addSql('DROP TABLE timeline_issue_sequence');
        $this->addSql('DROP TABLE timeline_issue_fact');
        $this->addSql('DROP TABLE timeline_issue');
        $this->addSql('DROP TABLE timeline_fact');
        $this->addSql('DROP TABLE timeline_sequence');
    }
}
