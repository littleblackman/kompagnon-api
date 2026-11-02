<?php

namespace App\Service;

use App\Entity\Criteria;
use App\Entity\DramaticFunction;
use App\Entity\Genre;
use App\Entity\LanguageLevel;
use App\Entity\NarrativeArc;
use App\Entity\NarrativeStructure;
use App\Entity\Part;
use App\Entity\Personnage;
use App\Entity\PersonnageActantialSchema;
use App\Entity\PersonnageDramaticFunction;
use App\Entity\PersonnageLanguageLevel;
use App\Entity\PersonnageNarrativeArc;
use App\Entity\Project;
use App\Entity\Scene;
use App\Entity\Sequence;
use App\Entity\SequenceCriteria;
use App\Entity\SequencePersonnage;
use App\Entity\Status;
use App\Entity\Subgenre;
use App\Entity\Type;
use App\Entity\User;
use App\Repository\PersonnageRepository;
use App\Utils\SlugUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Instantané d'un projet : export en JSON et réimport en nouveau projet.
 *
 * Le format ne contient aucun id de base. Les éléments du projet se citent
 * par une référence locale (« seq-3 », « perso-2 »), et les métadonnées
 * (statuts, critères, fonctions, arcs…) par leur nom, leurs ids variant
 * d'un environnement à l'autre. Les images des personnages ne sont pas
 * exportées.
 */
class ProjectTransferService
{
    public const FORMAT = 'kompagnon-project';
    public const FORMAT_VERSION = 1;

    /** Statut posé par l'API quand le nom exporté est introuvable */
    private const DEFAULT_STATUS_ID = 6;

    /** @var array<string, array<string, object|null>> */
    private array $metadataCache = [];

    /** @var string[] Ce qui n'a pas pu être rattaché pendant l'import */
    private array $warnings = [];

    /** @var string[] Slugs de personnages déjà pris pendant cet import, pas encore en base */
    private array $reservedPersonnageSlugs = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PersonnageRepository $personnageRepository,
        private SluggerInterface $slugger,
        private Security $security
    ) {
    }

    public function export(Project $project): array
    {
        $sequenceRefs = [];
        $personnageRefs = [];

        $parts = [];
        $partIndex = 0;
        $sequenceIndex = 0;
        foreach ($this->sortByPosition($project->getParts()) as $part) {
            $sequences = [];
            foreach ($this->sortByPosition($part->getSequences()) as $sequence) {
                $ref = 'seq-' . (++$sequenceIndex);
                $sequenceRefs[$sequence->getId()] = $ref;

                $sequences[] = [
                    'ref' => $ref,
                    'name' => $sequence->getName(),
                    'description' => $sequence->getDescription(),
                    'position' => $sequence->getPosition(),
                    'status' => $sequence->getStatus()->getName(),
                    'intention' => $sequence->getIntention(),
                    'aestheticIdea' => $sequence->getAestheticIdea(),
                    'information' => $sequence->getInformation(),
                    'criterias' => array_map(fn (SequenceCriteria $sc) => [
                        'criteria' => $sc->getCriteria()->getName(),
                        'rating' => $sc->getRating(),
                    ], $sequence->getSequenceCriterias()->toArray()),
                    'scenes' => array_map(fn (Scene $scene) => [
                        'name' => $scene->getName(),
                        'description' => $scene->getDescription(),
                        'content' => $scene->getContent(),
                        'notes' => $scene->getNotes(),
                        'position' => $scene->getPosition(),
                        'status' => $scene->getStatus()->getName(),
                    ], $this->sortByPosition($sequence->getScenes())),
                ];
            }

            $parts[] = [
                'ref' => 'part-' . (++$partIndex),
                'name' => $part->getName(),
                'description' => $part->getDescription(),
                'position' => $part->getPosition(),
                'status' => $part->getStatus()->getName(),
                'sequences' => $sequences,
            ];
        }

        $personnageIndex = 0;
        foreach ($project->getPersonnages() as $personnage) {
            $personnageRefs[$personnage->getId()] = 'perso-' . (++$personnageIndex);
        }

        $seqRef = fn (?Sequence $s) => $s ? ($sequenceRefs[$s->getId()] ?? null) : null;

        $personnages = [];
        foreach ($project->getPersonnages() as $personnage) {
            $personnages[] = [
                'ref' => $personnageRefs[$personnage->getId()],
                'firstName' => $personnage->getFirstName(),
                'lastName' => $personnage->getLastName(),
                'background' => $personnage->getBackground(),
                'age' => $personnage->getAge(),
                'level' => $personnage->getLevel(),
                'analysis' => $personnage->getAnalysis(),
                'strength' => $personnage->getStrength(),
                'weakness' => $personnage->getWeakness(),
                'languageAnalysis' => $personnage->getLanguageAnalysis(),
                'sequences' => array_values(array_filter(array_map(
                    fn (SequencePersonnage $sp) => $seqRef($sp->getSequence()),
                    $personnage->getSequencePersonnages()->toArray()
                ))),
                'dramaticFunctions' => array_map(fn (PersonnageDramaticFunction $pdf) => [
                    'dramaticFunction' => $pdf->getDramaticFunction()->getName(),
                    'fromSequence' => $seqRef($pdf->getFromSequence()),
                    'toSequence' => $seqRef($pdf->getToSequence()),
                    'weight' => $pdf->getWeight(),
                    'comment' => $pdf->getComment(),
                ], $personnage->getPersonnageDramaticFunctions()->toArray()),
                'narrativeArcs' => array_map(fn (PersonnageNarrativeArc $pna) => [
                    'narrativeArc' => $pna->getNarrativeArc()->getName(),
                    'fromSequence' => $seqRef($pna->getFromSequence()),
                    'toSequence' => $seqRef($pna->getToSequence()),
                    'weight' => $pna->getWeight(),
                    'steps' => $pna->getSteps(),
                    'comment' => $pna->getComment(),
                ], $personnage->getPersonnageNarrativeArcs()->toArray()),
                'actantialSchemas' => array_map(fn (PersonnageActantialSchema $schema) => [
                    'fromSequence' => $seqRef($schema->getFromSequence()),
                    'toSequence' => $seqRef($schema->getToSequence()),
                    'actantialRoles' => $this->exportActantialRoles($schema->getActantialRoles(), $personnageRefs),
                    'comment' => $schema->getComment(),
                ], $personnage->getActantialSchemas()->toArray()),
                'languageLevels' => array_map(fn (PersonnageLanguageLevel $pll) => [
                    'languageLevel' => $pll->getLanguageLevel()->getName(),
                    'weight' => $pll->getWeight(),
                    'comment' => $pll->getComment(),
                    'quote' => $pll->getQuote(),
                ], $personnage->getPersonnageLanguageLevels()->toArray()),
            ];
        }

        return [
            'format' => self::FORMAT,
            'formatVersion' => self::FORMAT_VERSION,
            'exportedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'project' => [
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'type' => $project->getType()->getName(),
                'referenceNarrativeComponents' => $this->exportReferenceComponents($project->getReferenceNarrativeComponents()),
                'parts' => $parts,
                'personnages' => $personnages,
            ],
        ];
    }

    /**
     * Recrée le projet pour l'utilisateur connecté, toujours en nouveau
     * projet. Tout passe dans une seule transaction : en cas d'erreur, rien
     * n'est créé.
     *
     * @return array{project: Project, warnings: string[]}
     */
    public function import(array $data): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('User not authenticated');
        }

        if (($data['format'] ?? null) !== self::FORMAT || !isset($data['project']) || !is_array($data['project'])) {
            throw new \InvalidArgumentException("Ce fichier n'est pas un export de projet Kompagnon.");
        }
        if ((int) ($data['formatVersion'] ?? 0) > self::FORMAT_VERSION) {
            throw new \InvalidArgumentException("Ce fichier vient d'une version plus récente de Kompagnon.");
        }

        $this->warnings = [];
        $this->metadataCache = [];
        $this->reservedPersonnageSlugs = [];

        $project = $this->entityManager->wrapInTransaction(fn () => $this->createProject($data['project'], $user));

        return ['project' => $project, 'warnings' => array_values(array_unique($this->warnings))];
    }

    private function createProject(array $p, User $user): Project
    {
        $em = $this->entityManager;

        $project = new Project();
        $name = trim((string) ($p['name'] ?? '')) ?: 'Projet importé';
        $project->setName($name . ' (import du ' . (new \DateTimeImmutable())->format('d/m/Y') . ')');
        $project->setSlug($this->uniqueProjectSlug($name));
        $project->setDescription($p['description'] ?? null);
        $project->setUser($user);
        $project->setType($this->resolveType($p['type'] ?? null));
        $project->setReferenceNarrativeComponents($this->importReferenceComponents($p['referenceNarrativeComponents'] ?? null));
        $em->persist($project);

        /** @var array<string, Sequence> $sequences */
        $sequences = [];

        foreach ($p['parts'] ?? [] as $i => $partData) {
            $part = new Part();
            $part->setName((string) ($partData['name'] ?? 'Partie'));
            $part->setDescription($partData['description'] ?? null);
            $part->setPosition((int) ($partData['position'] ?? $i));
            $part->setStatus($this->resolveStatus($partData['status'] ?? null));
            $part->setProject($project);
            $em->persist($part);

            foreach ($partData['sequences'] ?? [] as $j => $seqData) {
                $sequence = new Sequence();
                $sequence->setName((string) ($seqData['name'] ?? 'Séquence'));
                $sequence->setDescription($seqData['description'] ?? null);
                $sequence->setPosition((int) ($seqData['position'] ?? $j));
                $sequence->setStatus($this->resolveStatus($seqData['status'] ?? null));
                $sequence->setIntention($seqData['intention'] ?? null);
                $sequence->setAestheticIdea($seqData['aestheticIdea'] ?? null);
                $sequence->setInformation($seqData['information'] ?? null);
                $sequence->setPart($part);
                $em->persist($sequence);

                if (!empty($seqData['ref'])) {
                    $sequences[$seqData['ref']] = $sequence;
                }

                foreach ($seqData['criterias'] ?? [] as $critData) {
                    $criteria = $this->findByName(Criteria::class, $critData['criteria'] ?? null, 'Critère');
                    if (!$criteria) {
                        continue;
                    }
                    $sc = new SequenceCriteria();
                    $sc->setSequence($sequence);
                    $sc->setCriteria($criteria);
                    $sc->setRating((int) ($critData['rating'] ?? 0));
                    $em->persist($sc);
                }

                foreach ($seqData['scenes'] ?? [] as $k => $sceneData) {
                    $scene = new Scene();
                    $scene->setName((string) ($sceneData['name'] ?? 'Scène'));
                    $scene->setDescription($sceneData['description'] ?? null);
                    $scene->setContent((string) ($sceneData['content'] ?? ''));
                    $scene->setNotes($sceneData['notes'] ?? null);
                    $scene->setPosition((int) ($sceneData['position'] ?? $k));
                    $scene->setStatus($this->resolveStatus($sceneData['status'] ?? null));
                    $scene->setSequence($sequence);
                    $em->persist($scene);
                }
            }
        }

        $seq = fn ($ref) => is_string($ref) ? ($sequences[$ref] ?? null) : null;

        /** @var array<string, Personnage> $personnages */
        $personnages = [];
        /** @var array<int, array{PersonnageActantialSchema, array}> $schemas */
        $schemas = [];

        foreach ($p['personnages'] ?? [] as $persoData) {
            $personnage = new Personnage();
            $personnage->setFirstName((string) ($persoData['firstName'] ?? 'Sans nom'));
            $personnage->setLastName((string) ($persoData['lastName'] ?? ''));
            $personnage->setBackground($persoData['background'] ?? null);
            $personnage->setAge(isset($persoData['age']) ? (int) $persoData['age'] : null);
            $personnage->setLevel(isset($persoData['level']) ? (int) $persoData['level'] : null);
            $personnage->setAnalysis($persoData['analysis'] ?? null);
            $personnage->setStrength($persoData['strength'] ?? null);
            $personnage->setWeakness($persoData['weakness'] ?? null);
            $personnage->setLanguageAnalysis($persoData['languageAnalysis'] ?? null);
            $personnage->setSlug($this->uniquePersonnageSlug($personnage));
            $personnage->setProject($project);
            $em->persist($personnage);

            if (!empty($persoData['ref'])) {
                $personnages[$persoData['ref']] = $personnage;
            }

            foreach (array_unique($persoData['sequences'] ?? []) as $ref) {
                if (!$sequence = $seq($ref)) {
                    continue;
                }
                $sp = new SequencePersonnage();
                $sp->setSequence($sequence);
                $sp->setPersonnage($personnage);
                $em->persist($sp);
            }

            foreach ($persoData['dramaticFunctions'] ?? [] as $fnData) {
                $function = $this->findByName(DramaticFunction::class, $fnData['dramaticFunction'] ?? null, 'Fonction dramatique');
                if (!$function) {
                    continue;
                }
                $pdf = new PersonnageDramaticFunction();
                $pdf->setDramaticFunction($function);
                $pdf->setFromSequence($seq($fnData['fromSequence'] ?? null));
                $pdf->setToSequence($seq($fnData['toSequence'] ?? null));
                $pdf->setWeight((int) ($fnData['weight'] ?? 0));
                $pdf->setComment($fnData['comment'] ?? null);
                $personnage->addPersonnageDramaticFunction($pdf);
                $em->persist($pdf);
            }

            foreach ($persoData['narrativeArcs'] ?? [] as $arcData) {
                $arc = $this->findByName(NarrativeArc::class, $arcData['narrativeArc'] ?? null, 'Arc narratif');
                if (!$arc) {
                    continue;
                }
                $pna = new PersonnageNarrativeArc();
                $pna->setNarrativeArc($arc);
                $pna->setFromSequence($seq($arcData['fromSequence'] ?? null));
                $pna->setToSequence($seq($arcData['toSequence'] ?? null));
                $pna->setWeight((int) ($arcData['weight'] ?? 0));
                $pna->setSteps($arcData['steps'] ?? null);
                $pna->setComment($arcData['comment'] ?? null);
                $personnage->addPersonnageNarrativeArc($pna);
                $em->persist($pna);
            }

            foreach ($persoData['actantialSchemas'] ?? [] as $schemaData) {
                $schema = new PersonnageActantialSchema();
                $schema->setFromSequence($seq($schemaData['fromSequence'] ?? null));
                $schema->setToSequence($seq($schemaData['toSequence'] ?? null));
                $schema->setComment($schemaData['comment'] ?? null);
                $personnage->addActantialSchema($schema);
                $em->persist($schema);
                // Les rôles citent des personnages : on les pose une fois les ids connus
                $schemas[] = [$schema, is_array($schemaData['actantialRoles'] ?? null) ? $schemaData['actantialRoles'] : []];
            }

            foreach ($persoData['languageLevels'] ?? [] as $levelData) {
                $level = $this->findByName(LanguageLevel::class, $levelData['languageLevel'] ?? null, 'Niveau de langage');
                if (!$level) {
                    continue;
                }
                $pll = new PersonnageLanguageLevel();
                $pll->setLanguageLevel($level);
                $pll->setWeight((int) ($levelData['weight'] ?? 0));
                $pll->setComment($levelData['comment'] ?? null);
                $pll->setQuote($levelData['quote'] ?? null);
                $personnage->addPersonnageLanguageLevel($pll);
                $em->persist($pll);
            }
        }

        $em->flush();

        foreach ($schemas as [$schema, $roles]) {
            $schema->setActantialRoles($this->importActantialRoles($roles, $personnages));
        }
        $em->flush();

        return $project;
    }

    /** personnage_id → personnage_ref, pour que le schéma survive au changement d'ids */
    private function exportActantialRoles(array $roles, array $personnageRefs): array
    {
        foreach ($roles as $key => $role) {
            foreach ($role['concerned'] ?? [] as $i => $concerned) {
                $id = $concerned['personnage_id'] ?? null;
                unset($roles[$key]['concerned'][$i]['personnage_id']);
                $roles[$key]['concerned'][$i]['personnage_ref'] = $id !== null ? ($personnageRefs[$id] ?? null) : null;
            }
        }

        return $roles;
    }

    private function importActantialRoles(array $roles, array $personnages): array
    {
        foreach ($roles as $key => $role) {
            foreach ($role['concerned'] ?? [] as $i => $concerned) {
                $ref = $concerned['personnage_ref'] ?? null;
                unset($roles[$key]['concerned'][$i]['personnage_ref']);
                $roles[$key]['concerned'][$i]['personnage_id'] = is_string($ref) && isset($personnages[$ref])
                    ? $personnages[$ref]->getId()
                    : null;
            }
        }

        return $roles;
    }

    /** Les ids de genre, sous-genre et structure deviennent des noms */
    private function exportReferenceComponents(?array $components): ?array
    {
        if (!$components) {
            return $components;
        }

        $map = [
            'genre_id' => ['genre', Genre::class],
            'subgenre_id' => ['subgenre', Subgenre::class],
            'narrative_structure_id' => ['narrative_structure', NarrativeStructure::class],
        ];

        foreach ($map as $idKey => [$nameKey, $class]) {
            if (!array_key_exists($idKey, $components)) {
                continue;
            }
            $entity = $components[$idKey] ? $this->entityManager->find($class, $components[$idKey]) : null;
            unset($components[$idKey]);
            $components[$nameKey] = $entity?->getName();
        }

        return $components;
    }

    private function importReferenceComponents(?array $components): ?array
    {
        if (!$components) {
            return $components;
        }

        $genre = $this->findByName(Genre::class, $components['genre'] ?? null, 'Genre');

        $subgenre = null;
        if (!empty($components['subgenre'])) {
            $criteria = ['name' => $components['subgenre']];
            if ($genre) {
                $criteria['genre'] = $genre;
            }
            $subgenre = $this->entityManager->getRepository(Subgenre::class)->findOneBy($criteria);
            if (!$subgenre) {
                $this->warnings[] = sprintf('Sous-genre « %s » introuvable.', $components['subgenre']);
            }
        }

        $structure = $this->findByName(NarrativeStructure::class, $components['narrative_structure'] ?? null, 'Structure narrative');

        foreach (['genre', 'subgenre', 'narrative_structure'] as $nameKey) {
            unset($components[$nameKey]);
        }
        $components['genre_id'] = $genre?->getId();
        $components['subgenre_id'] = $subgenre?->getId();
        $components['narrative_structure_id'] = $structure?->getId();

        return $components;
    }

    private function resolveStatus(?string $name): Status
    {
        $status = $this->findByName(Status::class, $name, 'Statut');

        return $status ?? $this->entityManager->find(Status::class, self::DEFAULT_STATUS_ID);
    }

    private function resolveType(?string $name): Type
    {
        $type = $this->findByName(Type::class, $name, 'Type de projet');
        $type ??= $this->entityManager->getRepository(Type::class)->findOneBy([], ['id' => 'ASC']);

        if (!$type) {
            throw new \RuntimeException('Aucun type de projet en base.');
        }

        return $type;
    }

    /**
     * Métadonnée par son nom, mise en cache. Un nom absent de la base est
     * signalé une fois dans les avertissements, et l'élément est ignoré.
     */
    private function findByName(string $class, ?string $name, string $label): ?object
    {
        if ($name === null || $name === '') {
            return null;
        }

        if (!array_key_exists($name, $this->metadataCache[$class] ?? [])) {
            $entity = $this->entityManager->getRepository($class)->findOneBy(['name' => $name]);
            $this->metadataCache[$class][$name] = $entity;
            if (!$entity) {
                $this->warnings[] = sprintf('%s « %s » introuvable : ignoré.', $label, $name);
            }
        }

        return $this->metadataCache[$class][$name];
    }

    /**
     * Slug unique sur toute la table : le filtre Doctrine limite les requêtes
     * Project à l'utilisateur, on passe donc par DBAL.
     */
    private function uniqueProjectSlug(string $name): string
    {
        $conn = $this->entityManager->getConnection();
        $baseSlug = strtolower($this->slugger->slug($name)) ?: 'projet';
        $slug = $baseSlug;
        $i = 2;

        while ((int) $conn->fetchOne('SELECT COUNT(id) FROM project WHERE slug = ?', [$slug]) > 0) {
            $slug = $baseSlug . '-' . $i;
            $i++;
        }

        return $slug;
    }

    /** Les personnages créés dans cet import ne sont pas encore en base : on garde leurs slugs en mémoire */
    private function uniquePersonnageSlug(Personnage $personnage): string
    {
        $baseSlug = SlugUtils::createPersonnageSlug($personnage->getFirstName(), $personnage->getLastName()) ?: 'personnage';
        $slug = $this->personnageRepository->findUniqueSlug($baseSlug);
        $counter = 1;

        while (in_array($slug, $this->reservedPersonnageSlugs, true)) {
            $slug = $this->personnageRepository->findUniqueSlug($baseSlug . '-' . $counter);
            $counter++;
        }

        $this->reservedPersonnageSlugs[] = $slug;

        return $slug;
    }

    /** @return array<int, object> */
    private function sortByPosition(iterable $items): array
    {
        $items = is_array($items) ? $items : iterator_to_array($items);
        usort($items, fn ($a, $b) => $a->getPosition() <=> $b->getPosition());

        return $items;
    }
}
