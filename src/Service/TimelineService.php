<?php

namespace App\Service;

use App\Entity\Personnage;
use App\Entity\Project;
use App\Entity\Sequence;
use App\Entity\TimelineFact;
use App\Entity\TimelineIssue;
use App\Entity\TimelineSequence;
use App\Repository\TimelineFactRepository;
use App\Repository\TimelineIssueRepository;
use App\Repository\TimelineSequenceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Chronologie d'un projet : enregistre ce que l'analyse propose et construit
 * les quatre vues (frise, séquencier, tableau, incohérences).
 *
 * Le front n'a rien à calculer : zones, J+n, retours en arrière, jours de la
 * semaine et incohérences de calendrier arrivent tout faits.
 */
class TimelineService
{
    /** Écart, en années avant le présent du récit, au-delà duquel on parle d'origines */
    private const ORIGINS_YEARS = 20;

    private const ZONES = [
        'origines' => 'Origines',
        'avant' => 'Vies d\'avant',
        'juste_avant' => 'Juste avant',
        'present' => 'Le présent',
        'apres' => 'Après',
    ];

    private const LAYERS = [
        'present' => 'Présent du récit',
        'passe' => 'Passé des personnages',
        'histoire' => 'Histoire & monde',
    ];

    private const SEVERITY_ORDER = ['fort' => 0, 'moyen' => 1, 'faible' => 2, 'verifier' => 3];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TimelineSequenceRepository $sequenceRepository,
        private TimelineFactRepository $factRepository,
        private TimelineIssueRepository $issueRepository,
        private TimelineCalendar $calendar,
    ) {
    }

    // ── Ordre du livre ────────────────────────────────────────────────

    /**
     * Séquences dans l'ordre de lecture, avec leur numéro et leur repère I.2.
     *
     * @return array<int, array{sequence: Sequence, number: int, label: string, part: string}>
     */
    public function bookOrder(Project $project): array
    {
        $ordered = [];
        $number = 0;
        $partIndex = 0;

        foreach ($project->getParts() as $part) {
            $partIndex++;
            $sequences = $part->getSequences()->toArray();
            usort($sequences, fn (Sequence $a, Sequence $b) => $a->getPosition() <=> $b->getPosition());

            foreach ($sequences as $i => $sequence) {
                $ordered[$sequence->getId()] = [
                    'sequence' => $sequence,
                    'number' => ++$number,
                    'label' => $this->roman($partIndex) . '.' . ($i + 1),
                    'part' => $part->getName(),
                ];
            }
        }

        return $ordered;
    }

    /** Dernière séquence datée avant celle-ci dans l'ordre du livre : le repère du « lendemain » */
    public function previousDated(Sequence $sequence): ?TimelineSequence
    {
        $project = $sequence->getPart()->getProject();
        $byId = $this->indexBySequence($project);
        $previous = null;

        foreach ($this->bookOrder($project) as $id => $entry) {
            if ($id === $sequence->getId()) {
                return $previous;
            }
            if (isset($byId[$id]) && $byId[$id]->getYear() !== null) {
                $previous = $byId[$id];
            }
        }

        return $previous;
    }

    // ── Repères A0 / J0 ───────────────────────────────────────────────

    /**
     * J0 réglé par l'auteur, sinon la première date au jour près du présent.
     * A0 réglé, sinon l'année de J0.
     */
    public function references(Project $project, ?array $timelineSequences = null): array
    {
        $settings = $project->getTimelineSettings();
        $dayZero = null;
        $source = null;

        if ($settings['dayZero'] && ($parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $settings['dayZero']))) {
            $dayZero = $parsed;
            $source = 'manual';
        } else {
            $earliest = null;
            foreach ($timelineSequences ?? $this->sequenceRepository->findBy(['project' => $project]) as $ts) {
                $date = $this->calendar->toDate($ts->getYear(), $ts->getMonth(), $ts->getDay());
                if ($date && (!$earliest || $date < $earliest)) {
                    $earliest = $date;
                }
            }
            if ($earliest) {
                $dayZero = $earliest;
                $source = 'auto';
            }
        }

        $yearZero = is_numeric($settings['yearZero']) ? (int) $settings['yearZero'] : ($dayZero ? (int) $dayZero->format('Y') : null);

        return [
            'yearZero' => is_numeric($settings['yearZero']) ? (int) $settings['yearZero'] : null,
            'dayZero' => $settings['dayZero'],
            'dayZeroLabel' => $settings['dayZeroLabel'],
            'effectiveYearZero' => $yearZero,
            'effectiveDayZero' => $dayZero?->format('Y-m-d'),
            'dayZeroSource' => $source,
            'dayZeroDate' => $dayZero,
        ];
    }

    public function updateSettings(Project $project, array $data): void
    {
        $settings = $project->getTimelineSettings();

        if (array_key_exists('yearZero', $data)) {
            $settings['yearZero'] = is_numeric($data['yearZero']) ? (int) $data['yearZero'] : null;
        }
        if (array_key_exists('dayZero', $data)) {
            $valid = is_string($data['dayZero']) && \DateTimeImmutable::createFromFormat('!Y-m-d', $data['dayZero']);
            $settings['dayZero'] = $valid ? $data['dayZero'] : null;
        }
        if (array_key_exists('dayZeroLabel', $data)) {
            $label = is_string($data['dayZeroLabel']) ? trim($data['dayZeroLabel']) : '';
            $settings['dayZeroLabel'] = $label !== '' ? mb_substr($label, 0, 255) : null;
        }

        $project->setTimelineSettings($settings);
        $this->entityManager->flush();
    }

    // ── Enregistrement des propositions de l'analyse ─────────────────

    /**
     * Enregistre la lecture d'une séquence. Les faits encore « proposés » de
     * cette séquence sont remplacés ; validés et rejetés restent, et un fait
     * déjà tranché n'est pas reproposé sous le même libellé.
     */
    public function applySequenceExtraction(Sequence $sequence, array $extraction): void
    {
        $project = $sequence->getPart()->getProject();

        $ts = $this->sequenceRepository->findOneBy(['sequence' => $sequence]) ?? new TimelineSequence($project, $sequence);
        $this->entityManager->persist($ts);

        // Validée, la date est à l'auteur : on ne relit que les faits
        if (!$ts->isValidated() && isset($extraction['sequence']) && is_array($extraction['sequence'])) {
            $data = $extraction['sequence'];
            $ts->hydrateDate($data);
            $this->fixPrecision($ts);
            $ts->setPlace($data['place'] ?? null)
                ->setWho(is_array($data['who'] ?? null) ? $data['who'] : [])
                ->setSummary($data['summary'] ?? null)
                ->setQuote($data['quote'] ?? null);
        }
        $ts->markExtracted();

        $decided = [];
        foreach ($this->factRepository->findBy(['sequence' => $sequence]) as $fact) {
            if ($fact->getStatus() === TimelineFact::STATUS_PROPOSED && $fact->getOrigin() === TimelineFact::ORIGIN_IA) {
                $this->entityManager->remove($fact);
            } else {
                $decided[$this->labelKey($fact->getLabel())] = true;
            }
        }

        foreach ($extraction['facts'] ?? [] as $data) {
            $label = is_string($data['label'] ?? null) ? trim($data['label']) : '';
            if ($label === '' || isset($decided[$this->labelKey($label)])) {
                continue;
            }

            $fact = (new TimelineFact($project))
                ->setSequence($sequence)
                ->setLabel($label)
                ->setNature(TimelineFact::NATURE_EVOQUE)
                ->setLayer((string) ($data['layer'] ?? 'passe'))
                ->setWho(is_array($data['who'] ?? null) ? $data['who'] : [])
                ->setQuote($data['quote'] ?? null);
            $fact->hydrateDate($data);
            $this->fixPrecision($fact);
            $this->entityManager->persist($fact);
        }

        $this->entityManager->flush();
    }

    /** Faits tirés des fiches : même règle de remplacement, fiche par fiche */
    public function applyFicheExtraction(Project $project, array $facts): void
    {
        $byName = [];
        foreach ($project->getPersonnages() as $personnage) {
            $byName[$this->labelKey($this->personnageName($personnage))] = $personnage;
            $byName[$this->labelKey($personnage->getFirstName())] ??= $personnage;
        }

        $decided = [];
        foreach ($this->factRepository->findBy(['project' => $project, 'nature' => TimelineFact::NATURE_FICHE]) as $fact) {
            if ($fact->getStatus() === TimelineFact::STATUS_PROPOSED && $fact->getOrigin() === TimelineFact::ORIGIN_IA) {
                $this->entityManager->remove($fact);
            } else {
                $decided[$this->labelKey($fact->getLabel())] = true;
            }
        }

        foreach ($facts as $data) {
            $label = is_string($data['label'] ?? null) ? trim($data['label']) : '';
            $personnage = $byName[$this->labelKey((string) ($data['personnage'] ?? ''))] ?? null;
            if ($label === '' || !$personnage || isset($decided[$this->labelKey($label)])) {
                continue;
            }

            $fact = (new TimelineFact($project))
                ->setPersonnage($personnage)
                ->setLabel($label)
                ->setNature(TimelineFact::NATURE_FICHE)
                ->setLayer((string) ($data['layer'] ?? 'passe'))
                ->setWho(is_array($data['who'] ?? null) && $data['who'] ? $data['who'] : [$this->personnageName($personnage)])
                ->setQuote($data['quote'] ?? null);
            $fact->hydrateDate($data);
            $this->fixPrecision($fact);
            $this->entityManager->persist($fact);
        }

        $this->entityManager->flush();
    }

    /**
     * Remplace les incohérences ouvertes par celles de la nouvelle passe. Une
     * incohérence résolue ou écartée ne revient pas sous le même titre.
     *
     * @param array<string, TimelineFact|TimelineSequence> $refs identifiants F12 / S5 envoyés au modèle
     */
    public function applyCoherence(Project $project, array $issues, array $checks, array $refs): void
    {
        $known = [];
        foreach ($this->issueRepository->findBy(['project' => $project]) as $issue) {
            if ($issue->getStatus() === TimelineIssue::STATUS_OPEN) {
                $this->entityManager->remove($issue);
            } else {
                $known[$issue->getDedupeKey()] = true;
            }
        }

        foreach ($issues as $data) {
            $title = is_string($data['title'] ?? null) ? trim($data['title']) : '';
            if ($title === '' || isset($known[TimelineIssue::fingerprint($title)])) {
                continue;
            }
            $known[TimelineIssue::fingerprint($title)] = true;

            $issue = (new TimelineIssue($project, $title))
                ->setSeverity((string) ($data['severity'] ?? 'moyen'))
                ->setDescription($data['description'] ?? null)
                ->setFix($data['fix'] ?? null);

            foreach ($data['refs'] ?? [] as $ref) {
                $target = is_string($ref) ? ($refs[strtoupper(trim($ref))] ?? null) : null;
                if ($target instanceof TimelineFact) {
                    $issue->addFact($target);
                } elseif ($target instanceof TimelineSequence) {
                    $issue->addSequence($target);
                }
            }
            $this->entityManager->persist($issue);
        }

        foreach ($checks as $data) {
            $target = is_string($data['ref'] ?? null) ? ($refs[strtoupper(trim($data['ref']))] ?? null) : null;
            if ($target instanceof TimelineFact && $target->getNature() === TimelineFact::NATURE_FICHE) {
                $target->setCheck($data['check'] ?? null);
            }
        }

        $this->entityManager->flush();
    }

    // ── Saisie de l'auteur ────────────────────────────────────────────

    public function hydrateFact(TimelineFact $fact, array $data, ?Sequence $sequence): void
    {
        if (array_key_exists('label', $data) && is_string($data['label']) && trim($data['label']) !== '') {
            $fact->setLabel($data['label']);
        }
        if (array_key_exists('who', $data)) {
            $fact->setWho(is_array($data['who']) ? $data['who'] : preg_split('/\s*,\s*/', (string) $data['who']));
        }
        if (array_key_exists('layer', $data)) {
            $fact->setLayer((string) $data['layer']);
        }
        if (array_key_exists('nature', $data)) {
            $fact->setNature((string) $data['nature']);
        }
        if (array_key_exists('quote', $data)) {
            $fact->setQuote(is_string($data['quote']) ? $data['quote'] : null);
        }
        if (array_key_exists('status', $data)) {
            $fact->setStatus((string) $data['status']);
        }
        if (array_key_exists('sequenceId', $data)) {
            $fact->setSequence($sequence);
        }
        $fact->hydrateDate($data);
        $this->fixPrecision($fact);
    }

    public function hydrateTimelineSequence(TimelineSequence $ts, array $data): void
    {
        $ts->hydrateDate($data);
        $this->fixPrecision($ts);

        if (array_key_exists('place', $data)) {
            $ts->setPlace(is_string($data['place']) ? $data['place'] : null);
        }
        if (array_key_exists('who', $data)) {
            $ts->setWho(is_array($data['who']) ? $data['who'] : preg_split('/\s*,\s*/', (string) $data['who']));
        }
        if (array_key_exists('summary', $data)) {
            $ts->setSummary(is_string($data['summary']) ? $data['summary'] : null);
        }
        if (array_key_exists('status', $data)) {
            $ts->setStatus((string) $data['status']);
        }
    }

    /** Valide d'un coup ce qui est encore proposé, pour une séquence ou tout le projet */
    public function validateProposed(Project $project, ?Sequence $sequence): int
    {
        $criteria = ['project' => $project, 'status' => TimelineFact::STATUS_PROPOSED];
        if ($sequence) {
            $criteria['sequence'] = $sequence;
        }

        $count = 0;
        foreach ($this->factRepository->findBy($criteria) as $fact) {
            $fact->setStatus(TimelineFact::STATUS_VALIDATED);
            $count++;
        }
        foreach ($this->sequenceRepository->findBy($criteria) as $ts) {
            $ts->setStatus(TimelineSequence::STATUS_VALIDATED);
            $count++;
        }

        $this->entityManager->flush();

        return $count;
    }

    // ── Construction des vues ────────────────────────────────────────

    public function build(Project $project): array
    {
        $order = $this->bookOrder($project);
        $timelineSequences = $this->sequenceRepository->findBy(['project' => $project]);
        $byId = [];
        foreach ($timelineSequences as $ts) {
            $byId[$ts->getSequence()->getId()] = $ts;
        }

        $references = $this->references($project, $timelineSequences);
        $yearZero = $references['effectiveYearZero'];
        $dayZero = $references['dayZeroDate'];
        unset($references['dayZeroDate']);

        $facts = $this->factRepository->findBy(['project' => $project], ['id' => 'ASC']);
        $issues = $this->issueRepository->findBy(['project' => $project], ['id' => 'ASC']);

        // Codes stables I1, I2… dans l'ordre de création, écartées comprises
        $issueCodes = [];
        $factIssues = [];
        $sequenceIssues = [];
        foreach ($issues as $i => $issue) {
            $code = 'I' . ($i + 1);
            $issueCodes[$issue->getId()] = $code;
            if ($issue->getStatus() !== TimelineIssue::STATUS_OPEN) {
                continue;
            }
            foreach ($issue->getFacts() as $fact) {
                $factIssues[$fact->getId()][] = $code;
            }
            foreach ($issue->getSequences() as $ts) {
                $sequenceIssues[$ts->getId()][] = $code;
            }
        }

        // Le présent du récit, borné par les séquences datées
        $presentKeys = [];
        foreach ($timelineSequences as $ts) {
            if ($range = $this->calendar->range($ts->getYear(), $ts->getMonth(), $ts->getDay())) {
                $presentKeys[] = $range[0];
                $presentKeys[] = $range[1];
            }
        }
        $present = $presentKeys ? [min($presentKeys), max($presentKeys)] : null;

        $autoIssues = [];

        // ── Séquencier
        $rows = [];
        $maxKey = null;
        foreach ($order as $sequenceId => $entry) {
            $ts = $byId[$sequenceId] ?? null;
            $row = [
                'id' => $ts?->getId(),
                'sequenceId' => $sequenceId,
                'number' => $entry['number'],
                'label' => $entry['label'],
                'part' => $entry['part'],
                'name' => $entry['sequence']->getName(),
                'analysed' => $ts !== null,
                'stale' => $ts ? $this->isStale($entry['sequence'], $ts) : false,
                'issues' => $ts ? ($sequenceIssues[$ts->getId()] ?? []) : [],
            ];

            if ($ts) {
                $key = $this->calendar->sortKey($ts->getYear(), $ts->getMonth(), $ts->getDay());
                $flashback = $key !== null && $this->calendar->isFine($ts->getDatePrecision()) && $maxKey !== null && $key < $maxKey;
                if ($key !== null && $this->calendar->isFine($ts->getDatePrecision())) {
                    $maxKey = max($maxKey ?? $key, $key);
                }

                $row += $this->datedToArray($ts, $yearZero, $dayZero) + [
                    'place' => $ts->getPlace(),
                    'who' => $ts->getWho(),
                    'summary' => $ts->getSummary(),
                    'quote' => $ts->getQuote(),
                    'status' => $ts->getStatus(),
                    'extractedAt' => $ts->getExtractedAt()?->format(\DATE_ATOM),
                    'flashback' => $flashback,
                    'key' => $key,
                    'zone' => $this->zoneOf($ts->getYear(), $ts->getMonth(), $ts->getDay(), 'present', $present),
                ];

                foreach ($this->calendarIssues($ts, $row['date']) as $auto) {
                    $auto['sequences'] = [$sequenceId];
                    $auto['facts'] = [];
                    $autoIssues[] = $auto;
                }
            }

            $rows[] = $row;
        }

        // ── Faits
        $factRows = [];
        foreach ($facts as $fact) {
            $sequenceEntry = $fact->getSequence() ? ($order[$fact->getSequence()->getId()] ?? null) : null;
            $row = [
                'id' => $fact->getId(),
                'sequenceId' => $fact->getSequence()?->getId(),
                'sequenceLabel' => $sequenceEntry ? $sequenceEntry['label'] . ' · ' . $sequenceEntry['sequence']->getName() : null,
                'personnageId' => $fact->getPersonnage()?->getId(),
                'personnageName' => $fact->getPersonnage() ? $this->personnageName($fact->getPersonnage()) : null,
                'label' => $fact->getLabel(),
                'who' => $fact->getWho(),
                'layer' => $fact->getLayer(),
                'nature' => $fact->getNature(),
                'quote' => $fact->getQuote(),
                'status' => $fact->getStatus(),
                'check' => $fact->getCheck(),
                'origin' => $fact->getOrigin(),
                'issues' => $factIssues[$fact->getId()] ?? [],
                'key' => $this->calendar->sortKey($fact->getYear(), $fact->getMonth(), $fact->getDay()),
                'zone' => $this->zoneOf($fact->getYear(), $fact->getMonth(), $fact->getDay(), $fact->getLayer(), $present),
            ] + $this->datedToArray($fact, $yearZero, $dayZero);

            if ($fact->getStatus() !== TimelineFact::STATUS_REJECTED) {
                foreach ($this->calendarIssues($fact, $row['date']) as $auto) {
                    $auto['facts'] = [$fact->getId()];
                    $auto['sequences'] = [];
                    $autoIssues[] = $auto;
                }
            }

            $factRows[] = $row;
        }

        // Ordre chronologique ; les faits sans date ferment leur zone
        usort($factRows, fn ($a, $b) => [$a['key'] === null, $a['key'] ?? 0, $a['id']] <=> [$b['key'] === null, $b['key'] ?? 0, $b['id']]);

        // ── Zones de la frise : seulement celles qui ont du contenu
        $counts = array_fill_keys(array_keys(self::ZONES), 0);
        foreach ($rows as $row) {
            if (isset($row['zone'])) {
                $counts[$row['zone']]++;
            }
        }
        foreach ($factRows as $row) {
            if ($row['status'] !== TimelineFact::STATUS_REJECTED) {
                $counts[$row['zone']]++;
            }
        }
        $zones = [];
        foreach (self::ZONES as $key => $title) {
            if ($counts[$key] > 0) {
                $zones[] = ['key' => $key, 'title' => $title, 'range' => $this->zoneRange($key, $present), 'count' => $counts[$key]];
            }
        }

        // ── Incohérences : calendrier (calculées) puis analyse (enregistrées)
        $issueRows = [];
        foreach ($autoIssues as $i => $auto) {
            $issueRows[] = ['id' => null, 'code' => 'C' . ($i + 1), 'origin' => 'calendar', 'status' => 'open', 'description' => null] + $auto;
        }
        $sequenceIdOf = [];
        foreach ($timelineSequences as $ts) {
            $sequenceIdOf[$ts->getId()] = $ts->getSequence()->getId();
        }
        foreach ($issues as $issue) {
            if ($issue->getStatus() === TimelineIssue::STATUS_DISMISSED) {
                continue;
            }
            $issueRows[] = [
                'id' => $issue->getId(),
                'code' => $issueCodes[$issue->getId()],
                'origin' => 'ia',
                'severity' => $issue->getSeverity(),
                'title' => $issue->getTitle(),
                'description' => $issue->getDescription(),
                'fix' => $issue->getFix(),
                'status' => $issue->getStatus(),
                'facts' => array_map(fn (TimelineFact $f) => $f->getId(), $issue->getFacts()->toArray()),
                'sequences' => array_values(array_filter(array_map(
                    fn (TimelineSequence $ts) => $sequenceIdOf[$ts->getId()] ?? null,
                    $issue->getSequences()->toArray()
                ))),
            ];
        }
        usort($issueRows, fn ($a, $b) => [$a['status'] !== 'open', self::SEVERITY_ORDER[$a['severity']] ?? 9]
            <=> [$b['status'] !== 'open', self::SEVERITY_ORDER[$b['severity']] ?? 9]);

        $active = array_filter($factRows, fn ($f) => $f['status'] !== TimelineFact::STATUS_REJECTED);

        return [
            'settings' => $references,
            'zones' => $zones,
            'layers' => array_map(fn ($k, $v) => ['key' => $k, 'label' => $v], array_keys(self::LAYERS), self::LAYERS),
            'sequences' => $rows,
            'facts' => $factRows,
            'issues' => $issueRows,
            'stats' => [
                'sequences' => count($rows),
                'analysed' => count($timelineSequences),
                'stale' => count(array_filter($rows, fn ($r) => $r['stale'])),
                'facts' => count($active),
                'byNature' => [
                    'scene' => count($timelineSequences),
                    'evoque' => count(array_filter($active, fn ($f) => $f['nature'] === TimelineFact::NATURE_EVOQUE)),
                    'fiche' => count(array_filter($active, fn ($f) => $f['nature'] === TimelineFact::NATURE_FICHE)),
                ],
                'proposed' => count(array_filter($active, fn ($f) => $f['status'] === TimelineFact::STATUS_PROPOSED))
                    + count(array_filter($timelineSequences, fn ($ts) => !$ts->isValidated())),
                'openIssues' => count(array_filter($issueRows, fn ($i) => $i['status'] === 'open')),
            ],
        ];
    }

    // ── Calculs internes ─────────────────────────────────────────────

    private function datedToArray(TimelineFact|TimelineSequence $item, ?int $yearZero, ?\DateTimeImmutable $dayZero): array
    {
        $date = $item->dateToArray();
        $computedWeekday = $this->calendar->weekdayOf($item->getYear(), $item->getMonth(), $item->getDay());

        return [
            'date' => $date,
            'display' => $this->calendar->format($item->getYear(), $item->getMonth(), $item->getDay(), $item->isApproximate(), $yearZero) + [
                'precision' => TimelineCalendar::PRECISION_LABELS[$item->getDatePrecision()] ?? $item->getDatePrecision(),
                'weekday' => $computedWeekday,
            ],
            'dayOffset' => $this->calendar->dayOffset($item->getYear(), $item->getMonth(), $item->getDay(), $dayZero),
        ];
    }

    /** Ce que le calendrier seul suffit à prouver faux */
    private function calendarIssues(TimelineFact|TimelineSequence $item, array $date): array
    {
        $issues = [];
        $display = sprintf('%02d/%02d/%d', $date['day'] ?? 0, $date['month'] ?? 0, $date['year'] ?? 0);

        if ($this->calendar->isImpossible($date['year'], $date['month'], $date['day'])) {
            $issues[] = [
                'severity' => 'fort',
                'title' => 'Date impossible : ' . $display,
                'fix' => 'Vérifier le jour ou le mois dans le texte.',
            ];
        }

        $actual = $this->calendar->weekdayOf($date['year'], $date['month'], $date['day']);
        if ($date['weekday'] && $actual && $actual !== $date['weekday']) {
            $issues[] = [
                // Sur une date calée, c'est le calage qui est en cause, pas le texte
                'severity' => $date['approximate'] ? 'faible' : 'moyen',
                'title' => sprintf('« %s » tombe un %s : %s', $date['weekday'], $actual, $display),
                'fix' => $date['approximate']
                    ? 'La date est un calage : la décaler au ' . $date['weekday'] . ' le plus proche.'
                    : 'Changer le jour nommé dans le texte, ou la date.',
            ];
        }

        return $issues;
    }

    /**
     * Zone de la frise. Un fait du passé daté d'une année seule qui chevauche
     * le début du récit reste « juste avant » : c'est l'année d'avant le
     * présent, pas le présent.
     */
    private function zoneOf(?int $year, ?int $month, ?int $day, string $layer, ?array $present): string
    {
        $range = $this->calendar->range($year, $month, $day);

        if (!$range) {
            return match ($layer) {
                'present' => 'present',
                'histoire' => 'origines',
                default => 'avant',
            };
        }
        if (!$present) {
            return $layer === 'present' ? 'present' : 'avant';
        }

        [$start, $end] = $present;
        if ($range[0] > $end) {
            return 'apres';
        }
        if ($range[0] >= $start || ($layer === 'present' && $range[1] >= $start)) {
            return 'present';
        }

        $startYear = intdiv($start, 10000);
        return match (true) {
            $year < $startYear - self::ORIGINS_YEARS => 'origines',
            $year < $startYear - 1 => 'avant',
            default => 'juste_avant',
        };
    }

    private function zoneRange(string $zone, ?array $present): string
    {
        if (!$present) {
            return '';
        }
        $startYear = intdiv($present[0], 10000);
        $monthYear = fn (int $key) => sprintf('%02d/%d', intdiv($key % 10000, 100), intdiv($key, 10000));

        return match ($zone) {
            'origines' => 'avant ' . ($startYear - self::ORIGINS_YEARS),
            'avant' => ($startYear - self::ORIGINS_YEARS) . ' – ' . ($startYear - 2),
            'juste_avant' => ($startYear - 1) . ' – ' . $monthYear($present[0]),
            'present' => $monthYear($present[0]) . ' – ' . $monthYear($present[1]),
            'apres' => 'après ' . $monthYear($present[1]),
        };
    }

    /** Une scène réécrite depuis la dernière lecture rend la chronologie douteuse */
    private function isStale(Sequence $sequence, TimelineSequence $ts): bool
    {
        if (!$ts->getExtractedAt()) {
            return false;
        }
        foreach ($sequence->getScenes() as $scene) {
            $changed = $scene->getUpdatedAt() ?? $scene->getCreatedAt();
            if ($changed && $changed > $ts->getExtractedAt()) {
                return true;
            }
        }

        return false;
    }

    private function fixPrecision(TimelineFact|TimelineSequence $item): void
    {
        $item->setDatePrecision($this->calendar->coherentPrecision(
            $item->getDatePrecision(), $item->getYear(), $item->getMonth(), $item->getDay(), $item->getTime()
        ));
    }

    /** @return array<int, TimelineSequence> indexées par id de séquence */
    private function indexBySequence(Project $project): array
    {
        $byId = [];
        foreach ($this->sequenceRepository->findBy(['project' => $project]) as $ts) {
            $byId[$ts->getSequence()->getId()] = $ts;
        }

        return $byId;
    }

    private function labelKey(string $label): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($label)));
    }

    public function personnageName(Personnage $personnage): string
    {
        return trim($personnage->getFirstName() . ' ' . ($personnage->getLastName() ?? ''));
    }

    private function roman(int $n): string
    {
        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $out = '';
        foreach ($map as $symbol => $value) {
            while ($n >= $value) {
                $out .= $symbol;
                $n -= $value;
            }
        }

        return $out;
    }
}
