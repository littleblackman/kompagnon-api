<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ProjectRepository;
use Symfony\Bundle\SecurityBundle\Security;

class ProjectCollectionProvider implements ProviderInterface
{
    private ProjectRepository $projectRepository;
    private Security $security;

    public function __construct(ProjectRepository $projectRepository, Security $security)
    {
        $this->projectRepository = $projectRepository;
        $this->security = $security;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user) {
            return [];
        }

        // Retourner uniquement les projets de l'utilisateur connecté
        return $this->projectRepository->findBy(['user' => $user]);
    }
}
