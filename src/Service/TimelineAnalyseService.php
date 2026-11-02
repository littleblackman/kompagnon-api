<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Sequence;
use App\Entity\TimelineFact;
use App\Entity\TimelineSequence;
use App\Repository\TimelineFactRepository;
use App\Repository\TimelineSequenceRepository;

/**
 * Lecture de la chronologie par le modèle, en trois passes distinctes :
 *
 * 1. séquence par séquence, dater la scène et relever les faits évoqués.
 *    Un manuscrit entier ne tient pas dans un appel, et relancer une seule
 *    séquence réécrite doit rester possible ;
 * 2. les fiches personnages, pour les faits que l'auteur a notés hors texte ;
 * 3. la cohérence, sur la liste compacte de tout ce qui a été relevé.
 *
 * Le modèle lit et propose ; le calendrier (jours de la semaine, écarts en
 * jours) est calculé par TimelineCalendar, jamais demandé au modèle.
 */
class TimelineAnalyseService
{
    private const PRECISIONS = '"heure", "jour", "semaine", "mois", "saison", "annee", "decennie", "ordre", "aucune"';

    public function __construct(
        private IAConnectionService $iaConnection,
        private TimelineService $timelineService,
        private TimelineCalendar $calendar,
        private TimelineSequenceRepository $sequenceRepository,
        private TimelineFactRepository $factRepository,
    ) {
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function extractSequence(Sequence $sequence): array
    {
        $content = $this->extractContent($sequence);
        if ($content === '') {
            return ['success' => false, 'message' => 'Cette séquence ne contient aucun texte à analyser.'];
        }

        $project = $sequence->getPart()->getProject();
        $order = $this->timelineService->bookOrder($project);
        $entry = $order[$sequence->getId()] ?? null;

        $systemPrompt = <<<PROMPT
Tu es la scripte d'un auteur : tu tiens la chronologie de son manuscrit. On te
donne le texte d'UNE séquence et les repères de calendrier déjà établis.

Deux tâches :
1. Dater la séquence elle-même : quand se passe ce qui est montré en scène.
2. Relever les faits datés ou datables que le texte évoque sans les montrer :
   souvenirs, passé des personnages, âges, durées (« depuis vingt ans »),
   événements historiques, événements annoncés pour plus tard.

Règles de datation :
- Calcule les dates absolues à partir des repères. « Il a cinquante ans », dit
  en décembre 2025, donne une naissance vers 1975. « Le lendemain » se compte
  depuis la séquence précédente.
- Une date obtenue par calcul ou par supposition porte "approximate": true, et
  "anchor" explique le calcul en une ligne (« dit en déc. 2025, moins 50 ans »).
- N'invente aucune date. Si le texte ne permet pas de dater, laisse year, month
  et day à null, avec la précision "ordre" (on sait seulement avant / après) ou
  "aucune".
- precision vaut l'une de : {self::PRECISIONS}.
- Une saison se note par son mois central (hiver = 1, printemps = 4, été = 7,
  automne = 10) avec la précision "saison".
- weekday : seulement si le texte nomme le jour (« le lundi »), en minuscules ;
  sinon null. Ne calcule jamais toi-même un jour de la semaine.
- time : l'heure telle que le texte la donne (« 21:03 », « vers 19 h »), sinon null.

Règles pour les faits :
- layer : "present" (même période que le récit mais hors de cette séquence),
  "passe" (passé des personnages), "histoire" (Histoire réelle, mythologie du monde).
- Un fait par information datée, en une phrase factuelle.
- Ne reprends pas comme fait ce qui se passe dans la séquence elle-même.
- quote : la phrase du texte, mot pour mot, qui fonde la date (200 caractères au plus).
- who : les noms tels que le texte les donne.
- Écris en français. Réponds uniquement par un objet JSON, sans texte autour.

Format exact :
{
  "sequence": {"year": 2025, "month": 12, "day": 20, "approximate": true, "precision": "jour",
               "time": "5 h 30", "weekday": null, "place": "…", "who": ["…"],
               "summary": "Ce qui se passe, en une phrase.", "anchor": "…", "quote": "…"},
  "facts": [{"label": "…", "who": ["…"], "layer": "passe", "year": 1975, "month": null, "day": null,
             "approximate": true, "precision": "annee", "weekday": null, "time": null,
             "anchor": "…", "quote": "…"}]
}
PROMPT;

        $userMessage = $this->referencesText($project)
            . $this->previousText($sequence)
            . $this->personnagesText($project)
            . "\nSÉQUENCE " . ($entry ? $entry['label'] . ' (n° ' . $entry['number'] . ', partie « ' . $entry['part'] . ' »)' : '')
            . ' : ' . $sequence->getName() . "\n\nTEXTE :\n" . $content;

        $parsed = $this->ask($systemPrompt, $userMessage, IAConnectionService::PROFILE_FAST, 4000);
        if (!isset($parsed['sequence']) && !isset($parsed['facts'])) {
            return ['success' => false, 'message' => 'Réponse du modèle inexploitable.'];
        }

        $this->timelineService->applySequenceExtraction($sequence, [
            'sequence' => is_array($parsed['sequence'] ?? null) ? $parsed['sequence'] : null,
            'facts' => array_filter(is_array($parsed['facts'] ?? null) ? $parsed['facts'] : [], 'is_array'),
        ]);

        return ['success' => true];
    }

    /**
     * Faits datés que l'auteur a notés dans les fiches. Seuls les champs de
     * l'auteur sont lus (background, âge, origine) : les champs d'analyse ne
     * repartent jamais en entrée.
     *
     * @return array{success: bool, message?: string}
     */
    public function extractFiches(Project $project): array
    {
        $fiches = '';
        foreach ($project->getPersonnages() as $personnage) {
            $background = trim(strip_tags($personnage->getBackground() ?? ''));
            if ($background === '' && $personnage->getAge() === null) {
                continue;
            }

            $fiches .= '### ' . $this->timelineService->personnageName($personnage) . "\n";
            if ($personnage->getAge() !== null) {
                $fiches .= 'Âge noté sur la fiche : ' . $personnage->getAge() . " ans\n";
            }
            if ($background !== '') {
                $fiches .= mb_substr($background, 0, 4000) . "\n";
            }
            $fiches .= "\n";
        }

        if ($fiches === '') {
            return ['success' => false, 'message' => 'Aucune fiche personnage ne contient de quoi dater.'];
        }

        $systemPrompt = <<<PROMPT
Tu es la scripte d'un auteur. On te donne ses fiches personnages, ses notes de
travail hors manuscrit, et les repères de calendrier du récit.

Ta seule tâche : relever les faits datés ou datables de ces fiches (naissances,
événements du passé, âges, durées).

Règles :
- Un âge noté s'entend au début du récit (J0) : il donne une année de naissance
  approximative.
- Une date calculée porte "approximate": true et "anchor" explique le calcul.
- N'invente aucune date ; un fait sans date possible garde year à null et la
  précision "aucune".
- precision vaut l'une de : {self::PRECISIONS}.
- layer : "passe" (vie du personnage) ou "histoire" (Histoire, monde).
- personnage : le nom exact de la fiche, tel qu'il apparaît après ###.
- quote : le passage de la fiche qui fonde le fait (200 caractères au plus).
- Écris en français. Réponds uniquement par un objet JSON, sans texte autour.

Format exact :
{"facts": [{"personnage": "…", "label": "…", "who": ["…"], "layer": "passe", "year": 2008,
            "month": null, "day": null, "approximate": true, "precision": "annee",
            "anchor": "…", "quote": "…"}]}
PROMPT;

        $userMessage = $this->referencesText($project) . "\nFICHES :\n" . $fiches;

        $parsed = $this->ask($systemPrompt, $userMessage, IAConnectionService::PROFILE_FAST, 4000);
        if (!isset($parsed['facts']) || !is_array($parsed['facts'])) {
            return ['success' => false, 'message' => 'Réponse du modèle inexploitable.'];
        }

        $this->timelineService->applyFicheExtraction($project, array_filter($parsed['facts'], 'is_array'));

        return ['success' => true];
    }

    /**
     * Relit l'ensemble de la chronologie relevée et signale ce qui ne tient
     * pas. Le manuscrit prime sur les fiches.
     *
     * @return array{success: bool, message?: string}
     */
    public function checkCoherence(Project $project): array
    {
        $order = $this->timelineService->bookOrder($project);
        $refs = [];
        $lines = '';

        foreach ($this->sequenceRepository->findBy(['project' => $project]) as $ts) {
            $entry = $order[$ts->getSequence()->getId()] ?? null;
            $ref = 'S' . ($entry['number'] ?? $ts->getId());
            $refs[$ref] = $ts;
            $lines .= $this->line($ref, [
                'séquence ' . ($entry['label'] ?? '?') . ' « ' . $ts->getSequence()->getName() . ' »',
                $this->dateText($ts),
                $ts->getPlace(),
                $ts->getWho() ? 'avec ' . implode(', ', $ts->getWho()) : null,
                $ts->getSummary(),
                $ts->getQuote() ? '« ' . mb_substr($ts->getQuote(), 0, 160) . ' »' : null,
            ]);
        }

        $facts = array_filter(
            $this->factRepository->findBy(['project' => $project], ['id' => 'ASC']),
            fn (TimelineFact $f) => $f->getStatus() !== TimelineFact::STATUS_REJECTED
        );
        foreach ($facts as $fact) {
            $ref = 'F' . $fact->getId();
            $refs[$ref] = $fact;
            $entry = $fact->getSequence() ? ($order[$fact->getSequence()->getId()] ?? null) : null;
            $lines .= $this->line($ref, [
                $fact->getNature() === TimelineFact::NATURE_FICHE
                    ? 'FICHE de ' . ($fact->getPersonnage() ? $this->timelineService->personnageName($fact->getPersonnage()) : '?')
                    : 'évoqué en ' . ($entry['label'] ?? '?'),
                $fact->getLayer(),
                $this->dateText($fact),
                $fact->getLabel(),
                $fact->getWho() ? 'qui : ' . implode(', ', $fact->getWho()) : null,
                $fact->getAnchor() ? 'ancrage : ' . $fact->getAnchor() : null,
                $fact->getQuote() ? '« ' . mb_substr($fact->getQuote(), 0, 160) . ' »' : null,
            ]);
        }

        if (count($refs) < 2) {
            return ['success' => false, 'message' => 'Analysez d\'abord des séquences : il n\'y a pas encore de chronologie à vérifier.'];
        }

        $systemPrompt = <<<'PROMPT'
Tu es la scripte d'un auteur. On te donne la chronologie relevée dans son
manuscrit : les séquences datées (S…) et les faits (F…), certains tirés de ses
fiches personnages. Le manuscrit prime sur les fiches.

Deux tâches :
1. Relever les incohérences de calendrier : âges qui ne collent pas d'une
   séquence à l'autre, durées (« il y a trois semaines », « depuis six ans »)
   qui ne tombent pas juste entre deux dates, ordre impossible, personnage à
   deux endroits au même moment, fiche contredite par le texte.
2. Pour chaque fait de FICHE, dire si le manuscrit le confirme.

Règles :
- Ne signale pas les jours de la semaine : ils sont vérifiés par ailleurs.
- Ne signale pas un simple flou ou une date non précisée : seulement ce qui
  ne peut pas être vrai en même temps.
- severity : "fort" casse le calendrier ; "moyen" se voit à la lecture attentive ;
  "faible" se corrige d'un mot ; "verifier" est un doute que l'auteur doit trancher.
- title : l'incohérence en une ligne, sans identifiant.
- description : les deux affirmations qui s'opposent, avec leurs dates.
- fix : la correction la plus petite possible, de préférence dans le texte
  (« remplacer "dix-neuf ans" par "dix-huit ans" dans la séquence II.3 »).
- refs : les identifiants concernés (S…, F…).
- check vaut "confirmed" (le texte le dit aussi), "unverifiable" (le texte n'en
  dit rien) ou "contradicted" (le texte dit autre chose).
- Écris en français. Réponds uniquement par un objet JSON, sans texte autour.

Format exact :
{"issues": [{"severity": "moyen", "title": "…", "description": "…", "fix": "…", "refs": ["S4", "F12"]}],
 "checks": [{"ref": "F3", "check": "contradicted"}]}
PROMPT;

        $userMessage = $this->referencesText($project) . "\nCHRONOLOGIE RELEVÉE :\n" . $lines;

        $parsed = $this->ask($systemPrompt, $userMessage, IAConnectionService::PROFILE_DEEP, 6000);
        if (!isset($parsed['issues']) && !isset($parsed['checks'])) {
            return ['success' => false, 'message' => 'Réponse du modèle inexploitable.'];
        }

        $this->timelineService->applyCoherence(
            $project,
            array_filter(is_array($parsed['issues'] ?? null) ? $parsed['issues'] : [], 'is_array'),
            array_filter(is_array($parsed['checks'] ?? null) ? $parsed['checks'] : [], 'is_array'),
            $refs
        );

        return ['success' => true];
    }

    // ── Contexte envoyé au modèle ────────────────────────────────────

    private function referencesText(Project $project): string
    {
        $references = $this->timelineService->references($project);
        $text = 'REPÈRES : ';
        $parts = [];

        if ($references['effectiveYearZero'] !== null) {
            $parts[] = 'année de référence A0 = ' . $references['effectiveYearZero'];
        }
        if ($references['effectiveDayZero']) {
            $date = $references['dayZeroDate'];
            $parts[] = 'J0 = ' . $date->format('d/m/Y')
                . ' (' . TimelineCalendar::WEEKDAYS[(int) $date->format('N') - 1] . ')'
                . ($references['dayZeroLabel'] ? ', ' . $references['dayZeroLabel'] : '');
        }

        return $text . ($parts ? implode(' ; ', $parts) : 'aucun repère fixé, déduis-les du texte') . "\n";
    }

    /** Le repère du « lendemain », avec son jour de la semaine calculé ici */
    private function previousText(Sequence $sequence): string
    {
        $previous = $this->timelineService->previousDated($sequence);
        if (!$previous) {
            return "SÉQUENCE PRÉCÉDENTE DATÉE : aucune\n";
        }

        return 'SÉQUENCE PRÉCÉDENTE DATÉE : « ' . $previous->getSequence()->getName() . ' » — '
            . $this->dateText($previous) . "\n";
    }

    private function personnagesText(Project $project): string
    {
        $names = array_map(
            fn ($p) => $this->timelineService->personnageName($p),
            $project->getPersonnages()->toArray()
        );

        return $names ? 'PERSONNAGES DU PROJET : ' . implode(', ', $names) . "\n" : '';
    }

    private function dateText(TimelineFact|TimelineSequence $item): string
    {
        $display = $this->calendar->format($item->getYear(), $item->getMonth(), $item->getDay(), $item->isApproximate(), null);
        $weekday = $this->calendar->weekdayOf($item->getYear(), $item->getMonth(), $item->getDay());

        return $display['absolute']
            . ' (' . (TimelineCalendar::PRECISION_LABELS[$item->getDatePrecision()] ?? $item->getDatePrecision()) . ')'
            . ($weekday ? ', ' . $weekday : '')
            . ($item->getTime() ? ', ' . $item->getTime() : '');
    }

    private function line(string $ref, array $fields): string
    {
        return $ref . ' | ' . implode(' | ', array_filter($fields, fn ($f) => $f !== null && $f !== '')) . "\n";
    }

    private function extractContent(Sequence $sequence): string
    {
        $content = '';
        foreach ($sequence->getScenes() as $scene) {
            $content .= '### ' . $scene->getName() . "\n";
            $text = trim(strip_tags($scene->getContent() ?? ''));
            if ($text !== '') {
                $content .= $text . "\n\n";
            }
        }

        return trim($content);
    }

    // ── Appel ────────────────────────────────────────────────────────

    private function ask(string $systemPrompt, string $userMessage, string $profile, int $maxTokens): array
    {
        try {
            $response = $this->iaConnection->getTextResponse($systemPrompt, $userMessage, [
                'profile' => $profile,
                'temperature' => 0.1, // une date doit être la même d'un appel à l'autre
                'max_tokens' => $maxTokens,
                'response_format' => ['type' => 'json_object'],
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        $clean = preg_replace('/```(?:json)?/i', '', $response);
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    }
}
