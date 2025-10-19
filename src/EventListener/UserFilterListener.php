<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use App\Entity\User;

class UserFilterListener
{
    private EntityManagerInterface $em;
    private Security $security;

    public function __construct(EntityManagerInterface $em, Security $security)
    {
        $this->em = $em;
        $this->security = $security;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Récupérer l'utilisateur connecté
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Activer le filtre et définir l'ID de l'utilisateur
        $filter = $this->em->getFilters()->enable('user_owned_filter');
        $filter->setParameter('user_id', $user->getId());
    }
}
