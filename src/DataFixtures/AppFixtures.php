<?php

namespace App\DataFixtures;

use App\Entity\Status;
use App\Entity\Type;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create the type of the project
        $types = [
            ['name' => 'Roman', 'description' => 'Projet de fiction longue'],
            ['name' => 'Essai', 'description' => 'Texte argumentatif ou analytique'],
            ['name' => 'Scénario', 'description' => 'Projet de film ou de série'],
            ['name' => 'Poème', 'description' => 'Texte poétique'],
            ['name' => 'Nouvelle', 'description' => 'Projet de fiction courte'],
            ['name' => 'Article', 'description' => 'Texte informatif ou journalistique'],
            ['name' => 'Autre', 'description' => 'Projet de type inconnu'],
        ];

        foreach ($types as $t) {
            $type = new Type();
            $type->setName($t['name']);
            $type->setDescription($t['description']);
            $manager->persist($type);
        }

        // Create the status of the part
        $statuses = [
            ['name' => 'En cours', 'description' => 'Travail en cours'],
            ['name' => 'À valider', 'description' => 'En attente de validation'],
            ['name' => 'À travailler', 'description' => 'Nécessite des modifications'],
            ['name' => 'Validé', 'description' => 'Version finale validée'],
            ['name' => 'Masqué', 'description' => 'Non visible dans la publication'],
        ];

        foreach ($statuses as $s) {
            $status = new Status();
            $status->setName($s['name']);
            $status->setDescription($s['description']);
            $manager->persist($status);
        }

        $manager->flush();
    }
}
