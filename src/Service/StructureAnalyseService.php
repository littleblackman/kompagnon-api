<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Sequence;
use App\Repository\NarrativePartRepository;
use App\Repository\NarrativeStructureRepository;
use App\Repository\SubgenreRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Le texte suit-il la structure choisie ? Analyse en deux temps, pour tenir
 * un roman entier (72 000 mots et plus) :
 *
 * 1. Lecture de chaque séquence, en entier, par le modèle rapide : les beats
 *    du sous-genre qu'elle joue, et un résumé. Le résultat est rangé sur la
 *    séquence avec l'empreinte du texte lu : une séquence inchangée n'est
 *    jamais relue. Le front enchaîne ces lectures, une requête chacune.
 * 2. Synthèse par le modèle qui raisonne, à partir de ces lectures : verdict
 *    par beat, état de chaque section, ordre, et ce qui manque.
 *
 * Rien n'est réécrit : la structure et le texte restent à l'auteur.
 */
class StructureAnalyseService
{
    /**
     * Taille d'un morceau lu en un appel. Une séquence plus longue (certaines
     * dépassent 150 000 caractères) est lue scène par scène, en plusieurs
     * morceaux, et ses beats sont fusionnés : rien n'est sauté.
     */
    private const CHUNK_CHARS = 30000;

    private const READING_STATUSES = ['joue', 'esquisse'];
    private const BEAT_STATUSES = ['joue', 'esquisse', 'absent'];
    private const SECTION_STATUSES = ['couverte', 'partielle', 'absente'];

    private const MAX_MISSING = 5;
    private const MAX_EXAMPLES = 5;

    /** Version du format de lecture : la changer force une relecture */
    private const READING_VERSION = 1;

    public function __construct(
        private IAConnectionService $iaConnection,
        private SubgenreRepository $subgenreRepository,
        private NarrativeStructureRepository $narrativeStructureRepository,
        private NarrativePartRepository $narrativePartRepository,
        private EntityManagerInterface $em,
    ) {
    }

    // ── Cadre : structure, sections, beats attendus ──────────────────────

    /**
     * Ce que le projet s'est donné pour cadre, tel que l'analyse l'utilise.
     *
     * @return array{subgenre: array, structure: array, sections: array, beats: array, warnings: string[], key: string}|null
     */
    public function frameOf(Project $project): ?array
    {
        $reference = $project->getReferenceNarrativeComponents() ?? [];
        $subgenre = !empty($reference['subgenre_id']) ? $this->subgenreRepository->find($reference['subgenre_id']) : null;
        $structure = !empty($reference['narrative_structure_id']) ? $this->narrativeStructureRepository->find($reference['narrative_structure_id']) : null;
        if (!$subgenre || !$structure) {
            return null;
        }

        $segments = [];
        foreach ($this->narrativePartRepository->findAll() as $part) {
            $segments[$part->getCode()] = $part->getName();
        }

        $warnings = [];
        $sections = [];
        foreach ($structure->getNarrativePartOrder() ?? [] as $name => $codes) {
            $sectionSegments = [];
            foreach ((array) $codes as $code) {
                $known = isset($segments[$code]);
                if (!$known) {
                    $warnings[] = sprintf('La section « %s » cite le segment %s, absent du référentiel des segments.', $name, $code);
                }
                $sectionSegments[] = ['code' => $code, 'name' => $segments[$code] ?? null];
            }
            $sections[] = ['name' => (string) $name, 'segments' => $sectionSegments];
        }

        // Beats du sous-genre, rattachés à leur segment par la relation (le
        // code texte de l'event type n'est pas fiable)
        $beats = [];
        foreach ($subgenre->getSubgenreEventTypes() as $link) {
            $eventType = $link->getEventType();
            if (!$eventType) {
                continue;
            }
            $segmentCode = $eventType->getNarrativePart()?->getCode();
            $examples = [];
            foreach ($eventType->getEvents() as $event) {
                if (!$event->isOptional() && count($examples) < self::MAX_EXAMPLES) {
                    $examples[] = $event->getName();
                }
            }
            $beats[$eventType->getId()] = [
                'id' => $eventType->getId(),
                'name' => $eventType->getName(),
                'description' => $eventType->getDescription(),
                'segmentCode' => $segmentCode,
                'segmentName' => $segmentCode ? ($segments[$segmentCode] ?? $segmentCode) : null,
                'sections' => array_values(array_map(
                    fn (array $section) => $section['name'],
                    array_filter($sections, fn (array $section) => in_array($segmentCode, array_column($section['segments'], 'code'), true))
                )),
                'mandatory' => $link->isMandatory(),
                'weight' => $link->getWeight(),
                'examples' => $examples,
            ];
        }
        ksort($beats);

        foreach ($beats as $beat) {
            if (!$beat['sections']) {
                $warnings[] = sprintf('Le beat « %s » (segment %s) ne correspond à aucune section de la structure.', $beat['name'], $beat['segmentCode'] ?? '—');
            }
        }

        return [
            'subgenre' => ['id' => $subgenre->getId(), 'name' => $subgenre->getName()],
            'structure' => ['id' => $structure->getId(), 'name' => $structure->getName()],
            'sections' => $sections,
            'beats' => $beats,
            'warnings' => $warnings,
            // Un changement de sous-genre ou de beats invalide les lectures
            'key' => sha1(self::READING_VERSION . '|' . $subgenre->getId() . '|' . implode(',', array_keys($beats))),
        ];
    }

    // ── Texte d'une séquence ──────────────────────────────────────────────

    private function cleanText(?string $html): string
    {
        $html = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6])>#i', ' ', (string) $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Texte des scènes, titres compris, dans l'ordre */
    private function sceneTexts(Sequence $sequence): array
    {
        $texts = [];
        foreach ($sequence->getScenes() as $scene) {
            $content = $this->cleanText($scene->getContent());
            if ($content !== '') {
                $texts[] = '#### ' . $scene->getName() . "\n" . $content;
            }
        }

        return $texts;
    }

    private function sequenceText(Sequence $sequence): string
    {
        return implode("\n\n", $this->sceneTexts($sequence));
    }

    /**
     * Morceaux d'au plus CHUNK_CHARS, coupés entre les scènes ; une scène
     * plus longue qu'un morceau est elle-même découpée.
     *
     * @return string[]
     */
    private function sequenceChunks(Sequence $sequence): array
    {
        $chunks = [];
        $current = '';
        foreach ($this->sceneTexts($sequence) as $scene) {
            foreach (mb_str_split($scene, self::CHUNK_CHARS) as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) > self::CHUNK_CHARS) {
                    $chunks[] = $current;
                    $current = '';
                }
                $current .= ($current === '' ? '' : "\n\n") . $piece;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** Empreinte du texte lu et du cadre : si l'une change, la lecture est périmée */
    private function readingHash(Sequence $sequence, array $frame): string
    {
        return sha1($frame['key'] . '|' . $this->sequenceText($sequence));
    }

    public function isReadingFresh(Sequence $sequence, array $frame): bool
    {
        return ($sequence->getStructureReading()['hash'] ?? null) === $this->readingHash($sequence, $frame);
    }

    /** @return Sequence[] dans l'ordre du récit */
    public function sequencesOf(Project $project): array
    {
        $sequences = [];
        foreach ($project->getParts() as $part) {
            foreach ($part->getSequences() as $sequence) {
                $sequences[] = $sequence;
            }
        }

        return $sequences;
    }

    private function parseJson(string $response): array
    {
        $clean = preg_replace('/```(?:json)?/i', '', $response);
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }
        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    }

    private function text(mixed $value): ?string
    {
        return trim(strip_tags((string) $value)) ?: null;
    }

    // ── 1. Lecture d'une séquence ─────────────────────────────────────────

    /**
     * Lit la séquence et range ce qu'elle joue. Sans `force`, une lecture
     * dont l'empreinte correspond au texte actuel est rendue telle quelle.
     *
     * @return array{success: bool, message?: string, cached?: bool, reading?: array}
     */
    public function readSequence(Sequence $sequence, bool $force = false): array
    {
        $frame = $this->frameOf($sequence->getPart()->getProject());
        if (!$frame) {
            return ['success' => false, 'message' => 'Choisissez d\'abord un sous-genre et une structure pour le projet.'];
        }

        if (!$force && $this->isReadingFresh($sequence, $frame)) {
            return ['success' => true, 'cached' => true, 'reading' => $sequence->getStructureReading()];
        }

        $chunks = $this->sequenceChunks($sequence);
        $hash = $this->readingHash($sequence, $frame);

        // Une séquence vide ne coûte pas d'appel : elle ne joue rien
        if (!$chunks) {
            $reading = ['hash' => $hash, 'readAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM), 'empty' => true, 'summary' => null, 'beats' => []];
            $sequence->setStructureReading($reading);
            $this->em->flush();

            return ['success' => true, 'cached' => false, 'reading' => $reading];
        }

        $beatsText = '';
        foreach ($frame['beats'] as $beat) {
            $beatsText .= '- [' . $beat['id'] . '] ' . $beat['name']
                . ($beat['description'] ? ' : ' . $beat['description'] : '')
                . ($beat['examples'] ? ' (par exemple : ' . implode(', ', $beat['examples']) . ')' : '') . "\n";
        }

        $systemPrompt = <<<'PROMPT'
Tu es un dramaturge qui relit une séquence d'un roman. On te donne la liste des
beats attendus par le genre du récit, et le texte de la séquence (ou un
morceau de celle-ci, quand elle est longue).

Ta tâche :
- summary : une phrase qui dit ce qui se passe dans la séquence, sur le plan
  de l'intrigue (pas du style) ;
- beats : les beats de la liste que la séquence joue, et seulement ceux-là.
  Pour chacun : "event_type_id" (son identifiant entre crochets), "status"
  ("joue" s'il a lieu, "esquisse" s'il est seulement amorcé ou évoqué) et
  "comment", une phrase appuyée sur un élément précis du texte.
  Une séquence ne joue souvent aucun beat, ou un seul : n'en force aucun.

Réponds uniquement par un objet JSON, en français :
{"summary": "...", "beats": [{"event_type_id": 4, "status": "joue", "comment": "..."}]}
PROMPT;

        $beats = [];
        $summaries = [];
        foreach ($chunks as $index => $chunk) {
            $label = count($chunks) > 1 ? sprintf(' (morceau %d sur %d)', $index + 1, count($chunks)) : '';
            $userMessage = "BEATS ATTENDUS :\n" . $beatsText . "\nSÉQUENCE : " . $sequence->getName() . $label . "\n\n" . $chunk;

            try {
                $response = $this->iaConnection->getTextResponse($systemPrompt, $userMessage, [
                    // Lancée pour chaque séquence du roman : le modèle rapide suffit
                    'profile' => IAConnectionService::PROFILE_FAST,
                    'temperature' => 0.2,
                    'max_tokens' => 1200,
                    'response_format' => ['type' => 'json_object'],
                ]);
            } catch (\Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }

            $parsed = $this->parseJson($response);
            if (!$parsed) {
                return ['success' => false, 'message' => 'Réponse du modèle inexploitable.'];
            }

            if ($summary = $this->text($parsed['summary'] ?? '')) {
                $summaries[] = $summary;
            }

            // Fusion des morceaux : un beat joué l'emporte sur un beat esquissé
            foreach ($parsed['beats'] ?? [] as $raw) {
                $id = is_numeric($raw['event_type_id'] ?? null) ? (int) $raw['event_type_id'] : null;
                $status = $raw['status'] ?? null;
                if ($id === null || !isset($frame['beats'][$id]) || !in_array($status, self::READING_STATUSES, true)) {
                    continue;
                }
                if (isset($beats[$id]) && !($beats[$id]['status'] === 'esquisse' && $status === 'joue')) {
                    continue;
                }
                $beats[$id] = ['eventTypeId' => $id, 'status' => $status, 'comment' => $this->text($raw['comment'] ?? '')];
            }
        }

        $reading = [
            'hash' => $hash,
            'readAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'empty' => false,
            'summary' => $summaries ? implode(' ', $summaries) : null,
            'chunks' => count($chunks),
            'beats' => array_values($beats),
        ];
        $sequence->setStructureReading($reading);
        $this->em->flush();

        return ['success' => true, 'cached' => false, 'reading' => $reading];
    }

    // ── 2. Synthèse ───────────────────────────────────────────────────────

    private function itemFormat(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sequence_id' => ['type' => ['integer', 'null']],
                'text' => ['type' => 'string'],
            ],
            'required' => ['sequence_id', 'text'],
            'additionalProperties' => false,
        ];
    }

    private function synthesisFormat(): array
    {
        $ids = ['type' => 'array', 'items' => ['type' => 'integer']];

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'structure_verification',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'beats' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'event_type_id' => ['type' => 'integer'],
                                    'status' => ['type' => 'string', 'enum' => self::BEAT_STATUSES],
                                    'sequence_ids' => $ids,
                                    'comment' => ['type' => 'string'],
                                    'suggestion' => $this->itemFormat(),
                                ],
                                'required' => ['event_type_id', 'status', 'sequence_ids', 'comment', 'suggestion'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'sections' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'section_index' => ['type' => 'integer'],
                                    'status' => ['type' => 'string', 'enum' => self::SECTION_STATUSES],
                                    'sequence_ids' => $ids,
                                    'comment' => ['type' => 'string'],
                                ],
                                'required' => ['section_index', 'status', 'sequence_ids', 'comment'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'order_issues' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'missing' => ['type' => 'array', 'maxItems' => self::MAX_MISSING, 'items' => $this->itemFormat()],
                        'comment' => ['type' => 'string'],
                    ],
                    'required' => ['beats', 'sections', 'order_issues', 'missing', 'comment'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Confronte les lectures de toutes les séquences à la structure. Toutes
     * les lectures doivent être à jour : le front les enchaîne avant.
     *
     * @return array{success: bool, message?: string, staleSequenceIds?: int[], verification?: array}
     */
    public function synthesize(Project $project): array
    {
        $frame = $this->frameOf($project);
        if (!$frame) {
            return ['success' => false, 'message' => 'Choisissez d\'abord un sous-genre et une structure pour le projet.'];
        }

        $sequences = $this->sequencesOf($project);
        $stale = array_values(array_map(
            fn (Sequence $s) => $s->getId(),
            array_filter($sequences, fn (Sequence $s) => !$this->isReadingFresh($s, $frame))
        ));
        if ($stale) {
            return ['success' => false, 'message' => 'Des séquences n\'ont pas encore été lues.', 'staleSequenceIds' => $stale];
        }

        $known = [];
        $digest = '';
        $partIndex = 0;
        foreach ($project->getParts() as $part) {
            $partIndex++;
            $digest .= '## Partie ' . $partIndex . ' : ' . $part->getName() . "\n";
            $index = 0;
            foreach ($part->getSequences() as $sequence) {
                $index++;
                $known[$sequence->getId()] = $sequence;
                $reading = $sequence->getStructureReading();
                $digest .= '- [' . $sequence->getId() . '] (' . $partIndex . '.' . $index . ') ' . $sequence->getName();
                if (!empty($reading['empty'])) {
                    $digest .= " — séquence vide\n";
                    continue;
                }
                $digest .= ' — ' . ($reading['summary'] ?? '') . "\n";
                foreach ($reading['beats'] ?? [] as $beat) {
                    $digest .= '    · ' . ($frame['beats'][$beat['eventTypeId']]['name'] ?? '?')
                        . ' (' . $beat['status'] . ') : ' . ($beat['comment'] ?? '') . "\n";
                }
            }
        }

        $sectionsText = '';
        foreach ($frame['sections'] as $index => $section) {
            $sectionsText .= '- [' . $index . '] ' . $section['name'] . ' : ' . implode(', ', array_map(
                fn (array $s) => $s['name'] ?? $s['code'],
                $section['segments']
            )) . "\n";
        }
        $beatsText = '';
        foreach ($frame['beats'] as $beat) {
            $beatsText .= '- (beat ' . $beat['id'] . ') ' . $beat['name']
                . ' — segment ' . ($beat['segmentName'] ?? '—')
                . ($beat['mandatory'] ? ' — OBLIGATOIRE' : '')
                . ($beat['description'] ? ' : ' . $beat['description'] : '') . "\n";
        }

        $systemPrompt = <<<'PROMPT'
Tu es un dramaturge qui vérifie qu'un roman suit la structure que son auteur a
choisie. Tu ne lis pas le texte lui-même : on te donne, séquence par séquence
dans l'ordre du récit, ce qu'une première lecture y a relevé (résumé et beats).
Chaque séquence est précédée de son identifiant entre crochets.

Tes tâches :

1. BEATS — Pour chaque beat attendu par le genre ("event_type_id" : le numéro
   indiqué « beat N »), un verdict : "joue",
   "esquisse" ou "absent", les séquences où il se joue, un commentaire d'une
   phrase, et une piste si le beat est esquissé ou absent : ce qui pourrait se
   passer et où ("sequence_id" : la séquence où ça s'insérerait le mieux, null
   s'il faut une séquence nouvelle). Sans piste : {"sequence_id": null, "text": ""}.
   Insiste sur les beats obligatoires.

2. SECTIONS — Pour chaque section de la structure, désignée par son numéro
   ("section_index", le nombre entre crochets) : "couverte", "partielle" ou
   "absente", les séquences qui la portent, et un commentaire d'une phrase.

3. ORDRE — Phrases courtes sur ce qui arrive trop tôt, trop tard ou dans le
   désordre par rapport à la structure. Attention : l'ordre des sections tel
   qu'on te le donne peut être erroné ; juge l'ordre attendu d'après la nature
   de la structure (son nom et ses sections), pas d'après la liste.

4. MANQUES — "missing" : ce qui manque au texte pour tenir la structure, du plus
   urgent au moins urgent, au plus cinq éléments au format d'une piste.

5. BILAN — "comment" : trois à cinq phrases sur la façon dont le récit tient sa
   structure. Sans flatter ni accabler.

Ne rédige jamais le texte à la place de l'auteur. Dans tous les textes, cite
une séquence par son identifiant entre crochets, par exemple [12], et un beat
ou une section par son nom, jamais par un numéro : les crochets sont réservés
aux séquences.
Écris en français. Réponds uniquement par un objet JSON.
PROMPT;

        $userMessage = 'STRUCTURE : ' . $frame['structure']['name'] . "\n" . $sectionsText . "\n"
            . 'BEATS ATTENDUS (sous-genre ' . $frame['subgenre']['name'] . ") :\n" . $beatsText . "\n"
            . "LECTURE SÉQUENCE PAR SÉQUENCE :\n" . $digest;

        try {
            $response = $this->iaConnection->getTextResponse($systemPrompt, $userMessage, [
                'profile' => IAConnectionService::PROFILE_DEEP,
                'temperature' => 0.3,
                'max_tokens' => 6000,
                'response_format' => $this->synthesisFormat(),
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $parsed = $this->parseJson($response);
        if (!$parsed) {
            return ['success' => false, 'message' => 'Réponse du modèle inexploitable.'];
        }

        // Malgré la consigne, le modèle cite parfois un beat par son numéro
        // (« [11] », « beat 12 ») : les crochets étant réservés aux séquences,
        // on le remplace par son nom
        $named = function (?string $value) use ($frame, $known): ?string {
            if ($value === null) {
                return null;
            }
            $value = preg_replace_callback('/\(?\bbeats?\s+(\d+)\)?/iu', function (array $m) use ($frame) {
                return isset($frame['beats'][(int) $m[1]]) ? '« ' . $frame['beats'][(int) $m[1]]['name'] . ' »' : $m[0];
            }, $value);

            return preg_replace_callback('/\[(\d+)\]/', function (array $m) use ($frame, $known) {
                $id = (int) $m[1];
                if (isset($known[$id])) {
                    return $m[0];
                }
                if (isset($frame['beats'][$id])) {
                    return '« ' . $frame['beats'][$id]['name'] . ' »';
                }
                // Sinon, un numéro de section
                return isset($frame['sections'][$id]) ? '« ' . $frame['sections'][$id]['name'] . ' »' : $m[0];
            }, $value);
        };
        $say = fn (mixed $value): ?string => $named($this->text($value));

        $keep = fn (mixed $ids): array => array_values(array_unique(array_filter(
            array_map('intval', is_array($ids) ? $ids : []),
            fn (int $id) => isset($known[$id])
        )));
        $item = function (mixed $raw) use ($known, $say): ?array {
            $text = $say($raw['text'] ?? '');
            if ($text === null) {
                return null;
            }
            $id = is_numeric($raw['sequence_id'] ?? null) ? (int) $raw['sequence_id'] : null;

            return ['sequenceId' => $id !== null && isset($known[$id]) ? $id : null, 'text' => $text];
        };

        // Chaque beat attendu reçoit un verdict, même si le modèle l'a oublié
        $beats = [];
        foreach ($parsed['beats'] ?? [] as $raw) {
            $id = is_numeric($raw['event_type_id'] ?? null) ? (int) $raw['event_type_id'] : null;
            $status = $raw['status'] ?? null;
            if ($id === null || !isset($frame['beats'][$id]) || isset($beats[$id]) || !in_array($status, self::BEAT_STATUSES, true)) {
                continue;
            }
            $beats[$id] = [
                'eventTypeId' => $id,
                'status' => $status,
                'sequenceIds' => $keep($raw['sequence_ids'] ?? []),
                'comment' => $say($raw['comment'] ?? ''),
                'suggestion' => $status === 'joue' ? null : $item($raw['suggestion'] ?? []),
            ];
        }
        foreach ($frame['beats'] as $id => $beat) {
            $beats[$id] ??= ['eventTypeId' => $id, 'status' => 'absent', 'sequenceIds' => [], 'comment' => null, 'suggestion' => null];
            $beats[$id] += [
                'name' => $beat['name'],
                'segmentName' => $beat['segmentName'],
                'sections' => $beat['sections'],
                'mandatory' => $beat['mandatory'],
            ];
        }
        ksort($beats);

        $sectionsByIndex = [];
        foreach ($parsed['sections'] ?? [] as $raw) {
            $index = is_numeric($raw['section_index'] ?? null) ? (int) $raw['section_index'] : null;
            if ($index !== null && isset($frame['sections'][$index]) && in_array($raw['status'] ?? null, self::SECTION_STATUSES, true)) {
                $sectionsByIndex[$index] ??= $raw;
            }
        }
        $sections = [];
        foreach ($frame['sections'] as $index => $section) {
            $raw = $sectionsByIndex[$index] ?? null;
            $sections[] = [
                'name' => $section['name'],
                'segments' => $section['segments'],
                'status' => $raw['status'] ?? 'absente',
                'sequenceIds' => $keep($raw['sequence_ids'] ?? []),
                'comment' => $say($raw['comment'] ?? ''),
            ];
        }

        $verification = [
            'analyzedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'structure' => $frame['structure'],
            'subgenre' => $frame['subgenre'],
            'frameKey' => $frame['key'],
            'comment' => $say($parsed['comment'] ?? ''),
            'beats' => array_values($beats),
            'sections' => $sections,
            'orderIssues' => array_values(array_filter(array_map($say, $parsed['order_issues'] ?? []))),
            'missing' => array_values(array_filter(array_map($item, array_slice($parsed['missing'] ?? [], 0, self::MAX_MISSING)))),
            // Empreintes des lectures synthétisées : une séquence réécrite depuis rend l'analyse périmée
            'readings' => array_combine(
                array_map(fn (Sequence $s) => (string) $s->getId(), $sequences),
                array_map(fn (Sequence $s) => $s->getStructureReading()['hash'] ?? null, $sequences)
            ) ?: [],
        ];

        $project->setStructureVerification($verification);
        $this->em->flush();

        return ['success' => true, 'verification' => $verification];
    }

    /**
     * État de l'analyse pour la page : cadre, dernière synthèse, séquences à
     * (re)lire et séquences modifiées depuis la synthèse.
     */
    public function stateOf(Project $project): array
    {
        $frame = $this->frameOf($project);
        $verification = $project->getStructureVerification();
        if (!$frame) {
            return ['frame' => null, 'verification' => $verification, 'sequences' => []];
        }

        $sequences = [];
        $changedSinceSynthesis = [];
        foreach ($this->sequencesOf($project) as $sequence) {
            $fresh = $this->isReadingFresh($sequence, $frame);
            $sequences[] = ['id' => $sequence->getId(), 'fresh' => $fresh];

            $synthesized = $verification['readings'][(string) $sequence->getId()] ?? null;
            if ($verification && $synthesized !== $this->readingHash($sequence, $frame)) {
                $changedSinceSynthesis[] = $sequence->getId();
            }
        }

        return [
            'frame' => [
                'structure' => $frame['structure'],
                'subgenre' => $frame['subgenre'],
                'sections' => $frame['sections'],
                'beats' => array_values($frame['beats']),
                'warnings' => $frame['warnings'],
            ],
            'verification' => $verification,
            'sequences' => $sequences,
            'changedSinceSynthesis' => $changedSinceSynthesis,
            // Changer de structure ou de sous-genre rend la synthèse caduque
            'frameChanged' => $verification && ($verification['frameKey'] ?? null) !== $frame['key']
                || $verification && ($verification['structure']['id'] ?? null) !== $frame['structure']['id'],
        ];
    }
}
