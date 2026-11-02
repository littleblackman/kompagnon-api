<?php

namespace App\EventSubscriber;

use App\Entity\Audience;
use App\Entity\Criteria;
use App\Entity\DramaticFunction;
use App\Entity\Event;
use App\Entity\EventType;
use App\Entity\Genre;
use App\Entity\LanguageLevel;
use App\Entity\NarrativeArc;
use App\Entity\NarrativeForm;
use App\Entity\NarrativePart;
use App\Entity\NarrativeStructure;
use App\Entity\Status;
use App\Entity\Subgenre;
use App\Entity\SubgenreDramaticFunction;
use App\Entity\SubgenreEventType;
use App\Entity\Type;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * /api/metadata met les référentiels en cache sans expiration : sans cette
 * invalidation, ce que l'admin crée ou modifie (un sous-genre, un beat…)
 * restait invisible dans l'application, et un projet rattaché à un
 * sous-genre récent perdait son cadre à l'affichage.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class MetadataCacheInvalidator
{
    /** Clés posées par MetadataController */
    private const KEYS = [
        'criterias', 'status', 'types', 'narrative_arcs', 'dramatic_functions', 'language_levels',
        'subgenres', 'events', 'narrative_parts', 'audiences', 'narrative_forms',
    ];

    private const METADATA_ENTITIES = [
        Audience::class, Criteria::class, DramaticFunction::class, Event::class, EventType::class,
        Genre::class, LanguageLevel::class, NarrativeArc::class, NarrativeForm::class, NarrativePart::class,
        NarrativeStructure::class, Status::class, Subgenre::class, SubgenreDramaticFunction::class,
        SubgenreEventType::class, Type::class,
    ];

    private bool $dirty = false;

    public function __construct(private CacheInterface $cache)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        $changed = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates(),
            $uow->getScheduledEntityDeletions()
        );

        foreach ($changed as $entity) {
            foreach (self::METADATA_ENTITIES as $class) {
                if ($entity instanceof $class) {
                    $this->dirty = true;
                    return;
                }
            }
        }
    }

    // Après l'écriture seulement : un flush qui échoue ne vide rien
    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->dirty) {
            return;
        }
        $this->dirty = false;

        foreach (self::KEYS as $key) {
            $this->cache->delete($key);
        }
    }
}
