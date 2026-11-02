<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Journal des erreurs API et front, consulté depuis l'admin.
 */
final class Version20261102150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table error_log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE error_log (id INT AUTO_INCREMENT NOT NULL, source VARCHAR(10) NOT NULL, level VARCHAR(10) NOT NULL, message LONGTEXT NOT NULL, exception_class VARCHAR(255) DEFAULT NULL, status_code INT DEFAULT NULL, method VARCHAR(10) DEFAULT NULL, url VARCHAR(2048) DEFAULT NULL, file VARCHAR(255) DEFAULT NULL, trace LONGTEXT DEFAULT NULL, context JSON DEFAULT NULL, user_email VARCHAR(180) DEFAULT NULL, fingerprint VARCHAR(40) NOT NULL, occurrences INT NOT NULL, resolved TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_error_log_fingerprint (fingerprint), INDEX idx_error_log_last_seen (last_seen_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE error_log');
    }
}
