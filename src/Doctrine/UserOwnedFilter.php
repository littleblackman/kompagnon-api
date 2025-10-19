<?php

namespace App\Doctrine;

use App\Entity\Project;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class UserOwnedFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        // Appliquer le filtre uniquement sur l'entité Project
        if ($targetEntity->getReflectionClass()->getName() !== Project::class) {
            return '';
        }

        // Récupérer l'ID de l'utilisateur connecté depuis le paramètre
        $userId = $this->getParameter('user_id');

        if (!$userId) {
            return '';
        }

        // Ajouter la condition WHERE pour filtrer par user_id
        return sprintf('%s.user_id = %s', $targetTableAlias, $userId);
    }
}
