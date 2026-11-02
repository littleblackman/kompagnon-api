<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sauvegarde personnage.origin dans le background, avant que
 * Version20261102200000 ne supprime la colonne : une origine saisie en prod
 * ne doit pas disparaître avec elle.
 *
 * Sans effet là où la colonne n'existe plus (base locale déjà migrée) ou
 * quand aucune origine n'est renseignée.
 */
final class Version20261102195000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recopie personnage.origin à la fin du background avant sa suppression';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('personnage') || !$schema->getTable('personnage')->hasColumn('origin')) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, background, origin FROM personnage WHERE origin IS NOT NULL AND TRIM(origin) <> ''"
        );

        foreach ($rows as $row) {
            // Le background est du HTML TinyMCE : l'origine y entre échappée, en paragraphe
            $paragraph = '<p><strong>Origine :</strong> ' . htmlspecialchars(trim($row['origin']), ENT_QUOTES, 'UTF-8') . '</p>';

            $this->addSql(
                'UPDATE personnage SET background = ? WHERE id = ?',
                [($row['background'] ?? '') . $paragraph, $row['id']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Rien à défaire : l'origine reste lisible dans le background
    }
}
