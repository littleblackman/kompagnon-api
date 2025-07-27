<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250727134209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add slug column as nullable first
        $this->addSql('ALTER TABLE personnage ADD slug VARCHAR(255) DEFAULT NULL');
        
        // Populate slugs for existing personnages
        $this->addSql("
            UPDATE personnage 
            SET slug = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                CONCAT(COALESCE(first_name, ''), '-', COALESCE(last_name, '')),
                'à', 'a'), 'é', 'e'), 'è', 'e'), 'ç', 'c'), 'ô', 'o'), ' ', '-'))
            WHERE slug IS NULL
        ");
        
        // Handle potential duplicates by adding numbers
        $this->addSql("
            UPDATE personnage p1 
            SET slug = CONCAT(slug, '-', p1.id)
            WHERE EXISTS (
                SELECT 1 FROM (SELECT slug FROM personnage) p2 
                WHERE p2.slug = p1.slug AND p1.id > (
                    SELECT MIN(id) FROM (SELECT id, slug FROM personnage) p3 WHERE p3.slug = p1.slug
                )
            )
        ");
        
        // Make slug NOT NULL and add unique constraint
        $this->addSql('ALTER TABLE personnage MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6AEA486D989D9B62 ON personnage (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_6AEA486D989D9B62 ON personnage');
        $this->addSql('ALTER TABLE personnage DROP slug');
    }
}
