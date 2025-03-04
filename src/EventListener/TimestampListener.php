<?php

namespace App\EventListener;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use App\Entity\Traits\Timestampable;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;

class TimestampListener
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (in_array(Timestampable::class, class_uses($entity))) {
            $entity->setCreatedAt(new DateTimeImmutable());

            if ($this->security->getUser()) {
                $entity->setCreatedBy($this->security->getUser()->getUserIdentifier());
            }
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (in_array(Timestampable::class, class_uses($entity))) {
            $entity->setUpdatedAt(new DateTimeImmutable());

            if ($this->security->getUser()) {
                $entity->setUpdatedBy($this->security->getUser()->getUserIdentifier());
            }
        }
    }
}
