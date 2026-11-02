<?php

namespace App\Service;

use App\Entity\DramaticFunction;
use App\Entity\Personnage;
use App\Entity\PersonnageActantialSchema;
use App\Entity\PersonnageNarrativeArc;
use App\Entity\Sequence;
use App\Provider\ActantialSchemaProvider;
use App\Repository\DramaticFunctionRepository;
use App\Repository\LanguageLevelRepository;
use App\Repository\NarrativeArcRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Schéma actantiel d'un personnage, proposé par le modèle.
 *
 * Le personnage analysé est toujours le Sujet : le modèle ne choisit que
 * les cinq autres actants, à partir de la fiche et des séquences où il
 * apparaît.
 */
class PersonnageAnalyseService
{
    /** Un actant peut être tenu par plusieurs entités (deux adjuvants…) */
    private const MAX_CONCERNED = 4;

    /** Bornes du texte envoyé : au-delà, le coût grimpe sans gain de lecture */
    private const MAX_CHARS_TOTAL = 40000;
    /** Plancher par séquence, quand elles sont nombreuses à se partager le total */
    private const MIN_CHARS_PER_SEQUENCE = 1500;

    public function __construct(
        private IAConnectionService $iaConnection,
        private ActantialSchemaProvider $actantialSchemaProvider,
        private EntityManagerInterface $em,
        private LanguageLevelRepository $languageLevelRepository,
        private NarrativeArcRepository $narrativeArcRepository,
        private DramaticFunctionRepository $dramaticFunctionRepository,
        private PersonnageService $personnageService,
    ) {
    }

    private function fullName(Personnage $personnage): string
    {
        return trim($personnage->getFirstName() . ' ' . ($personnage->getLastName() ?? ''));
    }

    private function clean(?string $html): string
    {
        return trim(html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * Séquences où le personnage apparaît, dans l'ordre du récit.
     *
     * @return Sequence[]
     */
    private function sequencesOf(Personnage $personnage): array
    {
        $sequences = [];
        foreach ($personnage->getSequencePersonnages() as $link) {
            $sequence = $link->getSequence();
            $sequences[$sequence->getId()] = $sequence;
        }

        usort($sequences, fn (Sequence $a, Sequence $b) =>
            [$a->getPart()->getPosition(), $a->getPosition()]
            <=> [$b->getPart()->getPosition(), $b->getPosition()]
        );

        return $sequences;
    }

    /**
     * Le texte des séquences où le personnage apparaît, réparti dans le budget.
     *
     * Une séquence dépasse souvent de loin sa part : plutôt que son début, on
     * garde les paragraphes qui nomment le personnage, avec celui d'avant et
     * celui d'après pour le contexte (la réplique, le « il » qui suit). C'est
     * là que le récit révèle ou contredit la fiche, où qu'il le fasse dans la
     * séquence.
     *
     * @param bool $tagged Préfixer chaque séquence de son identifiant, pour
     *                     que le modèle puisse citer celles qu'il retient
     */
    private function extractContent(Personnage $personnage, bool $tagged = false): string
    {
        $sequences = $this->sequencesOf($personnage);
        if (!$sequences) {
            return '';
        }

        $budget = max(self::MIN_CHARS_PER_SEQUENCE, intdiv(self::MAX_CHARS_TOTAL, count($sequences)));
        $namePattern = $this->namePattern($personnage);
        $content = '';

        foreach ($sequences as $sequence) {
            $head = '## ' . ($tagged ? '[' . $sequence->getId() . '] ' : '') . $sequence->getName() . "\n";
            $intention = $this->clean($sequence->getIntention());
            if ($intention !== '') {
                $head .= 'Intention : ' . $intention . "\n";
            }

            $paragraphs = [];
            foreach ($sequence->getScenes() as $scene) {
                array_push($paragraphs, ...$this->paragraphs($scene->getContent()));
            }
            if (!$paragraphs) {
                continue;
            }

            $text = $this->passages($paragraphs, $namePattern, $budget - mb_strlen($head));
            $content .= $head . $text . "\n\n";
            if (mb_strlen($content) >= self::MAX_CHARS_TOTAL) {
                break;
            }
        }

        return trim(mb_substr($content, 0, self::MAX_CHARS_TOTAL));
    }

    /** Paragraphes d'une scène TinyMCE, en texte brut */
    private function paragraphs(?string $html): array
    {
        $parts = preg_split('#<br\s*/?>|</(?:p|div|li|h[1-6]|blockquote)>#i', (string) $html);

        return array_values(array_filter(
            array_map(fn (string $part) => trim(preg_replace('/\s+/u', ' ', $this->clean($part))), $parts),
            fn (string $part) => $part !== ''
        ));
    }

    /**
     * Prénom et nom, en mot entier et avec leur majuscule : « Marie » ne doit
     * pas attraper « mairie », ni un nom de trois lettres le milieu d'un mot.
     */
    private function namePattern(Personnage $personnage): ?string
    {
        $names = array_filter(
            [trim($personnage->getFirstName()), trim($personnage->getLastName() ?? '')],
            fn (string $name) => mb_strlen($name) >= 3
        );
        if (!$names) {
            return null;
        }

        return '/(?<!\p{L})(?:' . implode('|', array_map(fn ($n) => preg_quote($n, '/'), $names)) . ')(?!\p{L})/u';
    }

    /**
     * Les paragraphes qui nomment le personnage et leurs voisins, dans
     * l'ordre, les coupes marquées […]. Trop longs pour la part de la
     * séquence : on retire d'abord le contexte, puis on garde des mentions
     * réparties sur toute la séquence. Si le nom n'apparaît pas (la séquence
     * dit « il » ou « elle »), le début de la séquence, comme avant.
     */
    private function passages(array $paragraphs, ?string $namePattern, int $budget): string
    {
        $hits = $namePattern
            ? array_keys(array_filter($paragraphs, fn (string $p) => preg_match($namePattern, $p) === 1))
            : [];

        if (!$hits) {
            return mb_substr(implode("\n", $paragraphs), 0, max(0, $budget));
        }

        $render = function (array $indices) use ($paragraphs): string {
            sort($indices);
            $out = '';
            $previous = null;
            foreach ($indices as $index) {
                if ($previous !== null && $index > $previous + 1) {
                    $out .= "[…]\n";
                }
                $out .= $paragraphs[$index] . "\n";
                $previous = $index;
            }
            return $out;
        };

        $withContext = [];
        foreach ($hits as $index) {
            foreach ([$index - 1, $index, $index + 1] as $i) {
                if (isset($paragraphs[$i])) {
                    $withContext[$i] = $i;
                }
            }
        }

        $text = $render($withContext);
        if (mb_strlen($text) <= $budget) {
            return $text;
        }

        $text = $render($hits);
        if (mb_strlen($text) <= $budget) {
            return $text;
        }

        // Des mentions prises à intervalles réguliers, du début à la fin
        $average = max(1, intdiv(mb_strlen($text), count($hits)));
        $keep = max(1, min(count($hits), intdiv($budget, $average)));
        $step = count($hits) / $keep;
        $sampled = [];
        for ($k = 0; $k < $keep; $k++) {
            $sampled[] = $hits[(int) floor($k * $step)];
        }

        return mb_substr($render(array_unique($sampled)), 0, max(0, $budget));
    }

    /**
     * Ce que l'auteur a déclaré, et seulement ça : les sorties d'analyses
     * précédentes (analyse, langage) ne reviennent jamais en entrée.
     */
    private function describePersonnage(Personnage $personnage): string
    {
        $lines = ['Nom : ' . $this->fullName($personnage)];

        $fields = [
            'Âge' => $personnage->getAge(),
            'Histoire' => $this->clean($personnage->getBackground()),
            'Forces' => $personnage->getStrength(),
            'Faiblesses' => $personnage->getWeakness(),
        ];
        foreach ($fields as $label => $value) {
            if (trim((string) $value) !== '') {
                $lines[] = $label . ' : ' . $value;
            }
        }

        $functions = [];
        foreach ($personnage->getPersonnageDramaticFunctions() as $pdf) {
            $functions[] = $pdf->getDramaticFunction()->getName();
        }
        if ($functions) {
            $lines[] = 'Fonctions dramatiques : ' . implode(', ', $functions);
        }

        return implode("\n", $lines);
    }

    /**
     * Schéma de sortie imposé au modèle : les six rôles, toujours présents,
     * pour ne plus dépendre de la bonne volonté du modèle sur le format.
     *
     * @param string[] $roleNames
     */
    private function responseFormat(array $roleNames): array
    {
        $actant = [
            'type' => 'object',
            'properties' => [
                'concerned' => [
                    'type' => 'array',
                    'maxItems' => self::MAX_CONCERNED,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'personnage_id' => ['type' => ['integer', 'null']],
                        ],
                        'required' => ['name', 'personnage_id'],
                        'additionalProperties' => false,
                    ],
                ],
                'comment' => ['type' => 'string'],
            ],
            'required' => ['concerned', 'comment'],
            'additionalProperties' => false,
        ];

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'actantial_schema',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'roles' => [
                            'type' => 'object',
                            'properties' => array_fill_keys($roleNames, $actant),
                            'required' => $roleNames,
                            'additionalProperties' => false,
                        ],
                        'comment' => ['type' => 'string'],
                    ],
                    'required' => ['roles', 'comment'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /** Filet de sécurité si la sortie structurée n'est pas respectée : le JSON du premier { au dernier } */
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

    /**
     * Génère (ou régénère) le schéma actantiel global du personnage.
     *
     * @return array{success: bool, message?: string, schema?: array}
     */
    public function generateActantialSchema(Personnage $personnage): array
    {
        // Sans texte, il ne resterait que la fiche : le schéma serait inventé
        $content = $this->extractContent($personnage);
        if ($content === '') {
            return [
                'success' => false,
                'message' => 'Rien à analyser : associez le personnage à des séquences écrites.',
            ];
        }

        $roles = iterator_to_array($this->actantialSchemaProvider->provide(new \ApiPlatform\Metadata\GetCollection()));
        $rolesList = '';
        foreach ($roles as $role) {
            $rolesList .= '- ' . $role->role . ' : ' . $role->description . "\n";
        }

        // Le modèle rend des identifiants : on lui donne la liste fermée des
        // personnages du projet, puis on vérifie chaque identifiant rendu.
        $others = [];
        foreach ($personnage->getProject()->getPersonnages() as $other) {
            $others[$other->getId()] = $this->fullName($other);
        }
        $cast = '';
        foreach ($others as $id => $name) {
            $cast .= '- [' . $id . '] ' . $name . "\n";
        }

        $systemPrompt = <<<PROMPT
Tu es un dramaturge qui maîtrise le modèle actantiel de Greimas. On te donne le
nom d'un personnage et les passages du récit où il apparaît.

Ta seule tâche : établir le schéma actantiel dont ce personnage est le Sujet, tel
que le récit le montre, d'après ces seuls passages.

Les six actants :
$rolesList
Règles :
- Le Sujet est toujours le personnage analysé. Pour lui, le commentaire décrit sa quête.
- Un actant peut être un personnage, un groupe, une idée, une valeur ou un objet.
- Quand un actant est un personnage de la liste fournie, reprends son identifiant
  entre crochets dans "personnage_id" ; sinon mets null.
- Un actant peut être tenu par plusieurs entités (au plus 4).
- Si le texte ne permet pas de remplir un actant, renvoie une liste vide : n'invente rien.
- Chaque commentaire fait une ou deux phrases et s'appuie sur un élément concret du texte.
- Le commentaire général relève ce que le schéma révèle (tension, manque, ambiguïté),
  sans flatter ni accabler l'auteur.
- Réponds uniquement par un objet JSON, sans texte autour.

Format exact :
{
  "roles": {
    "Sujet": {"concerned": [{"name": "...", "personnage_id": 12}], "comment": "..."},
    "Objet": {"concerned": [{"name": "...", "personnage_id": null}], "comment": "..."},
    "Adjuvant": {"concerned": [], "comment": "..."},
    "Opposant": {"concerned": [], "comment": "..."},
    "Destinateur": {"concerned": [], "comment": "..."},
    "Destinataire": {"concerned": [], "comment": "..."}
  },
  "comment": "..."
}
PROMPT;

        $userMessage = "PERSONNAGE ANALYSÉ (identifiant " . $personnage->getId() . ") :\n"
            // Le nom seul : ce que l'auteur a déclaré n'a pas à orienter la lecture
            . 'Nom : ' . $this->fullName($personnage) . "\n\n"
            . "PERSONNAGES DU PROJET :\n" . $cast . "\n"
            . "PASSAGES OÙ IL APPARAÎT :\n" . $content;

        try {
            $response = $this->iaConnection->getTextResponse($systemPrompt, $userMessage, [
                // Lire les rapports de force entre personnages demande de raisonner
                'profile' => IAConnectionService::PROFILE_DEEP,
                'temperature' => 0.3,
                'max_tokens' => 2000,
                'response_format' => $this->responseFormat(array_map(fn ($role) => $role->role, $roles)),
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $parsed = $this->parseJson($response);
        $result = [];

        foreach ($roles as $role) {
            $raw = $parsed['roles'][$role->role] ?? null;
            if (!is_array($raw)) {
                continue;
            }

            $concerned = [];
            foreach (array_slice($raw['concerned'] ?? [], 0, self::MAX_CONCERNED) as $entry) {
                $name = trim((string) ($entry['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $id = is_numeric($entry['personnage_id'] ?? null) ? (int) $entry['personnage_id'] : null;
                // Identifiant hors projet : on garde le nom, pas le lien
                if ($id !== null && !isset($others[$id])) {
                    $id = null;
                }
                $concerned[] = [
                    'name' => $id !== null ? $others[$id] : $name,
                    'personnage_id' => $id,
                ];
            }

            $result[$role->role] = [
                'role' => $role->role,
                'concerned' => $concerned,
                'comment' => trim((string) ($raw['comment'] ?? '')) ?: null,
            ];
        }

        if (!$result) {
            return ['success' => false, 'message' => 'Réponse du modèle inexploitable.'];
        }

        // Le Sujet n'est pas négociable, quoi qu'ait rendu le modèle
        $result['Sujet'] = [
            'role' => 'Sujet',
            'concerned' => [['name' => $this->fullName($personnage), 'personnage_id' => $personnage->getId()]],
            'comment' => $result['Sujet']['comment'] ?? null,
        ];

        // Régénérer remplace le schéma global plutôt que d'en empiler
        $schema = $personnage->getGlobalActantialSchema();
        if (!$schema) {
            $schema = new PersonnageActantialSchema();
            $personnage->addActantialSchema($schema);
            $this->em->persist($schema);
        }
        $schema->setActantialRoles($result);
        $schema->setComment(trim((string) ($parsed['comment'] ?? '')) ?: null);
        $this->em->flush();

        return ['success' => true, 'schema' => $schema->toArray()];
    }

    /** Au-delà, la détection devient du bruit : un personnage suit rarement plus de deux arcs */
    private const MAX_DETECTED_ARCS = 2;

    /** Même garde-fou pour les fonctions dramatiques tenues hors fiche */
    private const MAX_DETECTED_FUNCTIONS = 2;

    /** Au-delà, une liste de manques ne se lit plus : on garde l'essentiel */
    private const MAX_MISSING = 5;

    /** Place du personnage dans une séquence */
    private const SEQUENCE_ROLES = ['central', 'secondaire', 'evoque'];

    /** Verdicts possibles pour une étape d'arc ou une fonction dramatique */
    private const STEP_STATUSES = ['joue', 'esquisse', 'absent'];

    /** Balises gardées dans un texte rendu par le modèle et destiné à l'éditeur */
    private const ALLOWED_TAGS = '<p><br><em><strong><ul><ol><li>';

    /**
     * Étapes d'un arc telles que l'auteur les a rattachées, ou à défaut
     * celles du référentiel.
     *
     * @return array<int, array{step: string, fromSequenceId: ?int, toSequenceId: ?int}>
     */
    private function stepsOf(PersonnageNarrativeArc $pna): array
    {
        $steps = [];
        foreach ($pna->getSteps() ?: $pna->getNarrativeArc()->getSteps() as $step) {
            $steps[] = is_array($step)
                ? [
                    'step' => (string) ($step['step'] ?? ''),
                    'fromSequenceId' => $step['fromSequenceId'] ?? null,
                    'toSequenceId' => $step['toSequenceId'] ?? null,
                ]
                : ['step' => (string) $step, 'fromSequenceId' => null, 'toSequenceId' => null];
        }

        return $steps;
    }

    /**
     * Le texte rendu finit dans un v-html : on ne garde que les balises de
     * mise en forme, dépouillées de tout attribut.
     */
    private function cleanHtml(mixed $html): string
    {
        $kept = strip_tags((string) $html, self::ALLOWED_TAGS);

        return trim(preg_replace('/<(\/?)(\w+)[^>]*>/', '<$1$2>', $kept));
    }

    /** Piste proposée pour un manque : où, et quoi, sans rédiger le texte */
    private function suggestionFormat(): array
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

    /** Ce qui manque au texte, du plus urgent au moins urgent */
    private function missingFormat(): array
    {
        return ['type' => 'array', 'maxItems' => self::MAX_MISSING, 'items' => $this->suggestionFormat()];
    }

    private function verificationFormat(): array
    {
        $verdict = fn (string $idKey) => [
            'type' => 'object',
            'properties' => [
                $idKey => ['type' => 'integer'],
                'status' => ['type' => 'string', 'enum' => self::STEP_STATUSES],
                'sequence_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'comment' => ['type' => 'string'],
                'suggestion' => $this->suggestionFormat(),
            ],
            'required' => [$idKey, 'status', 'sequence_ids', 'comment', 'suggestion'],
            'additionalProperties' => false,
        ];
        $sentences = ['type' => 'array', 'items' => ['type' => 'string']];
        $withMissing = function (array $item): array {
            $item['properties']['missing'] = $this->missingFormat();
            $item['required'][] = 'missing';
            return $item;
        };

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'personnage_verification',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'arcs' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'arc_id' => ['type' => 'integer'],
                                    'steps' => ['type' => 'array', 'items' => $verdict('index')],
                                    'comment' => ['type' => 'string'],
                                    'missing' => $this->missingFormat(),
                                ],
                                'required' => ['arc_id', 'steps', 'comment', 'missing'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'detected_arcs' => [
                            'type' => 'array',
                            'maxItems' => self::MAX_DETECTED_ARCS,
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'arc_id' => ['type' => 'integer'],
                                    'weight' => ['type' => 'integer'],
                                    'comment' => ['type' => 'string'],
                                    'steps' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'index' => ['type' => 'integer'],
                                                'sequence_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                            ],
                                            'required' => ['index', 'sequence_ids'],
                                            'additionalProperties' => false,
                                        ],
                                    ],
                                    'missing' => $this->missingFormat(),
                                ],
                                'required' => ['arc_id', 'weight', 'comment', 'steps', 'missing'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'functions' => ['type' => 'array', 'items' => $withMissing($verdict('function_id'))],
                        'detected_functions' => [
                            'type' => 'array',
                            'maxItems' => self::MAX_DETECTED_FUNCTIONS,
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'function_id' => ['type' => 'integer'],
                                    'weight' => ['type' => 'integer'],
                                    'comment' => ['type' => 'string'],
                                    'sequence_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                    'missing' => $this->missingFormat(),
                                ],
                                'required' => ['function_id', 'weight', 'comment', 'sequence_ids', 'missing'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'sequences' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'sequence_id' => ['type' => 'integer'],
                                    'role' => ['type' => 'string', 'enum' => self::SEQUENCE_ROLES],
                                    'summary' => ['type' => 'string'],
                                ],
                                'required' => ['sequence_id', 'role', 'summary'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'novelties' => $sentences,
                        'contradictions' => $sentences,
                        'analysis' => ['type' => 'string'],
                        'language_analysis' => ['type' => 'string'],
                        'language_levels' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'level_id' => ['type' => 'integer'],
                                    'weight' => ['type' => 'integer'],
                                    'comment' => ['type' => 'string'],
                                    'quote' => ['type' => 'string'],
                                ],
                                'required' => ['level_id', 'weight', 'comment', 'quote'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['arcs', 'detected_arcs', 'functions', 'detected_functions', 'sequences', 'novelties', 'contradictions', 'analysis', 'language_analysis', 'language_levels'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Confronte la fiche de l'auteur au texte et enregistre ce qui relève de
     * l'analyse : verdicts sur les arcs et les fonctions dramatiques, ce qui
     * est détecté hors fiche, écarts, pistes pour les manques, analyse du
     * texte et langage. La fiche n'est jamais réécrite, à deux exceptions
     * près, qui ne remplacent rien de ce que l'auteur a saisi :
     * - les séquences d'une fonction jouée (champ que l'auteur ne saisit pas) ;
     * - la place d'une étape d'arc jouée que l'auteur n'avait pas placée.
     *
     * @return array{success: bool, message?: string, verification?: array}
     */
    public function verifyPersonnage(Personnage $personnage): array
    {
        $content = $this->extractContent($personnage, true);

        if ($content === '') {
            return [
                'success' => false,
                'message' => 'Rien à vérifier : associez le personnage à des séquences écrites.',
            ];
        }

        // Séquences fournies, dans l'ordre du récit : seules celles-ci
        // peuvent revenir dans une réponse
        $sequences = [];
        foreach ($this->sequencesOf($personnage) as $sequence) {
            $sequences[$sequence->getId()] = $sequence;
        }
        $rank = array_flip(array_keys($sequences));
        $keepSequences = fn (mixed $ids): array => array_values(array_unique(array_filter(
            array_map('intval', is_array($ids) ? $ids : []),
            fn (int $id) => isset($sequences[$id])
        )));

        $arcs = [];
        $arcsText = '';
        foreach ($personnage->getPersonnageNarrativeArcs() as $pna) {
            $steps = $this->stepsOf($pna);
            $arcs[$pna->getId()] = ['pna' => $pna, 'steps' => $steps];

            $arcsText .= '- [' . $pna->getId() . '] ' . $pna->getNarrativeArc()->getName()
                . ' (' . $pna->getNarrativeArc()->getTendency() . ")\n";
            foreach ($steps as $index => $step) {
                $bounds = array_filter([$step['fromSequenceId'], $step['toSequenceId']]);
                $arcsText .= '  ' . $index . '. ' . $step['step']
                    . ($bounds ? ' — placée par l\'auteur en séquence(s) ' . implode(', ', array_unique($bounds)) : '')
                    . "\n";
            }
        }

        $functions = [];
        $functionsText = '';
        foreach ($personnage->getPersonnageDramaticFunctions() as $pdf) {
            $function = $pdf->getDramaticFunction();
            $functions[$pdf->getId()] = $pdf;
            $functionsText .= '- [' . $pdf->getId() . '] ' . $this->describeFunction($function) . "\n";
        }

        $systemPrompt = <<<'PROMPT'
Tu es un dramaturge qui relit le travail d'un auteur. On te donne la fiche d'un
personnage (ce que l'auteur a voulu), ses arcs narratifs étape par étape, ses
fonctions dramatiques, et les séquences où il apparaît (chaque séquence est
précédée de son identifiant entre crochets). Tu confrontes la fiche au texte :
tu ne la réécris jamais.

PISTES — Plusieurs tâches demandent une piste pour ce qui manque au texte.
Une piste dit en une ou deux phrases ce qui pourrait se passer pour combler le
manque, et "sequence_id" désigne la séquence fournie où elle s'insérerait le
mieux (null si elle demande une séquence nouvelle). Tu ne rédiges jamais le
texte à la place de l'auteur : ni dialogue, ni phrase du récit. Quand rien ne
manque, renvoie {"sequence_id": null, "text": ""}.

Tes tâches :

1. ARCS — Pour chaque étape de chaque arc, dis si le texte la joue :
   - "joue" : l'étape a lieu, on peut la montrer du doigt ;
   - "esquisse" : elle est amorcée ou seulement évoquée ;
   - "absent" : rien dans le texte ne la porte.
   Donne les identifiants des séquences où elle se joue (liste vide si absente),
   un commentaire d'une phrase appuyé sur le texte, et une piste si elle est
   esquissée ou absente. Si l'auteur a placé l'étape ailleurs que là où le
   texte la joue, dis-le. Le commentaire de l'arc dit en une ou deux phrases
   s'il progresse et s'il aboutit.
   "missing" : ce qui manque au texte pour faire avancer l'arc, puis pour le
   boucler (étape non jouée, bascule absente, résolution qui n'arrive pas…),
   du plus urgent au moins urgent, au plus cinq éléments au format d'une piste.
   Liste vide si l'arc est joué jusqu'au bout et bouclé.

2. ARCS DÉTECTÉS — Parmi les arcs du référentiel fourni (ceux que l'auteur n'a
   pas attribués), repère ceux que le personnage suit réellement dans le
   texte, au plus deux, du plus net au moins net :
   - pour chaque étape de l'arc, les séquences où elle se joue (liste vide si
     le texte ne la porte pas encore) ;
   - weight (0 à 100) : à quel point la trajectoire colle à cet arc ;
   - comment : une ou deux phrases qui justifient, appuyées sur le texte ;
   - missing : comme pour les arcs de la fiche, ce qui manque pour le boucler.
   Ne propose un arc que si au moins deux de ses étapes se jouent. Sinon,
   renvoie une liste vide.

3. FONCTIONS DRAMATIQUES — Pour chaque fonction que l'auteur a donnée au
   personnage, dis si le texte la lui fait tenir ("joue", "esquisse",
   "absent"), dans quelles séquences, avec un commentaire d'une phrase
   appuyé sur le texte, et une piste si elle est esquissée ou absente.
   "missing" : ce que le personnage doit encore faire dans le texte pour
   remplir pleinement la fonction, d'après ses caractéristiques, au plus cinq
   éléments au format d'une piste. Liste vide si la fonction est remplie.

4. FONCTIONS DÉTECTÉES — Parmi les fonctions du référentiel fourni (celles que
   l'auteur n'a pas données), repère celles que le personnage tient
   nettement dans le texte, au plus deux : séquences où il la tient, weight
   (0 à 100), commentaire qui justifie, et "missing" : ce qui lui manque
   pour la remplir pleinement. Liste vide sinon.

5. ÉCARTS AVEC LA FICHE — Deux listes de phrases courtes, chacune appuyée sur
   un élément concret du texte :
   - novelties : ce que le récit révèle du personnage (passé, liens, traits)
     et que la fiche ne dit pas ;
   - contradictions : ce que le récit montre et que la fiche contredit.
   Listes vides si le texte et la fiche concordent.

6. ANALYSE — Un bilan de trois à cinq phrases, d'après le texte seul : où en
   sont les arcs et les fonctions, ce que le personnage fait dans le récit,
   ce qui manque. Sans flatter ni accabler. HTML simple, en paragraphes <p>.

7. NIVEAUX DE LANGAGE — Qualifie la façon dont le personnage parle, d'après
   ses seules répliques et ses pensées rapportées (pas la narration, pas les
   autres personnages). Choisis dans la liste fournie, par identifiant :
   - un ou plusieurs niveaux ; le poids (0 à 100) dit lequel domine ;
   - le commentaire dit dans quelles circonstances il tient ce niveau
     (« avec ses supérieurs », « quand il s'emporte ») ;
   - quote : une réplique courte du texte qui l'illustre, recopiée telle quelle.
   S'il ne parle pas dans le texte, renvoie une liste vide.

8. LANGAGE — Décris comment parle le personnage, toujours d'après ses seules
   répliques et pensées : vocabulaire, syntaxe et longueur des phrases, tics
   ou expressions récurrentes, ce qui change selon l'interlocuteur ou la
   situation, et si sa parole évolue au fil du récit. Signale une réplique
   qui détonne avec sa voix habituelle. Appuie chaque remarque sur un mot ou
   une tournure du texte. Quatre à six phrases, HTML simple en <p>.
   S'il ne parle pas dans le texte, renvoie une chaîne vide.

9. SÉQUENCE PAR SÉQUENCE — Pour chaque séquence fournie, dans l'ordre :
   - role : "central" s'il porte la séquence, "secondaire" s'il y agit sans
     la porter, "evoque" s'il n'y est que mentionné ;
   - summary : une phrase sur ce qu'il y fait et ce qu'on y apprend de lui.

Dans tous les textes, cite une séquence par son identifiant entre crochets,
par exemple [12], et jamais par son nom : l'auteur retrouve la séquence par
ce renvoi.

Écris en français. Réponds uniquement par un objet JSON, sans texte autour.

Format exact :
{
  "arcs": [
    {"arc_id": 3, "steps": [{"index": 0, "status": "absent", "sequence_ids": [], "comment": "...", "suggestion": {"sequence_id": 12, "text": "..."}}], "comment": "...", "missing": [{"sequence_id": 14, "text": "..."}]}
  ],
  "detected_arcs": [{"arc_id": 4, "weight": 70, "comment": "...", "steps": [{"index": 0, "sequence_ids": [12]}], "missing": [{"sequence_id": null, "text": "..."}]}],
  "functions": [{"function_id": 7, "status": "joue", "sequence_ids": [12, 14], "comment": "...", "suggestion": {"sequence_id": null, "text": ""}, "missing": []}],
  "detected_functions": [{"function_id": 2, "weight": 60, "comment": "...", "sequence_ids": [12], "missing": []}],
  "sequences": [{"sequence_id": 12, "role": "central", "summary": "..."}],
  "novelties": ["..."],
  "contradictions": ["..."],
  "analysis": "<p>...</p>",
  "language_analysis": "<p>...</p>",
  "language_levels": [{"level_id": 3, "weight": 70, "comment": "...", "quote": "..."}]
}
PROMPT;

        // Référentiels, moins ce qui est déjà sur la fiche : la détection
        // ne propose que du neuf
        $assignedArcIds = [];
        foreach ($personnage->getPersonnageNarrativeArcs() as $pna) {
            $assignedArcIds[$pna->getNarrativeArc()->getId()] = true;
        }
        $referentialArcs = [];
        $referentialText = '';
        foreach ($this->narrativeArcRepository->findAll() as $arc) {
            if (isset($assignedArcIds[$arc->getId()])) {
                continue;
            }
            $referentialArcs[$arc->getId()] = $arc;
            $referentialText .= '- [' . $arc->getId() . '] ' . $arc->getName() . ' (' . $arc->getTendency() . ') : '
                . $arc->getDescription() . "\n";
            foreach (array_values($arc->getSteps()) as $index => $step) {
                $referentialText .= '  ' . $index . '. ' . (is_array($step) ? ($step['step'] ?? '') : $step) . "\n";
            }
        }

        $assignedFunctionIds = [];
        foreach ($functions as $pdf) {
            $assignedFunctionIds[$pdf->getDramaticFunction()->getId()] = true;
        }
        $referentialFunctions = [];
        $referentialFunctionsText = '';
        foreach ($this->dramaticFunctionRepository->findAll() as $function) {
            if (isset($assignedFunctionIds[$function->getId()])) {
                continue;
            }
            $referentialFunctions[$function->getId()] = $function;
            $referentialFunctionsText .= '- [' . $function->getId() . '] ' . $this->describeFunction($function) . "\n";
        }

        $languageLevels = [];
        $levelsText = '';
        foreach ($this->languageLevelRepository->findAllOrdered() as $level) {
            $languageLevels[$level->getId()] = $level;
            $levelsText .= '- [' . $level->getId() . '] ' . $level->getName() . ' : ' . $level->getDescription()
                . ($level->getMarkers() ? ' (' . implode(', ', $level->getMarkers()) . ')' : '') . "\n";
        }

        $userMessage = "FICHE DE L'AUTEUR :\n" . $this->describePersonnage($personnage) . "\n\n"
            . "ARCS NARRATIFS :\n" . ($arcsText !== '' ? $arcsText : "(aucun arc défini : renvoie une liste vide)\n") . "\n"
            . "RÉFÉRENTIEL DES ARCS (pour la détection) :\n" . ($referentialText !== '' ? $referentialText : "(tous déjà attribués : renvoie une liste vide)\n") . "\n"
            . "FONCTIONS DRAMATIQUES DONNÉES PAR L'AUTEUR :\n" . ($functionsText !== '' ? $functionsText : "(aucune : renvoie une liste vide)\n") . "\n"
            . "RÉFÉRENTIEL DES FONCTIONS (pour la détection) :\n" . ($referentialFunctionsText !== '' ? $referentialFunctionsText : "(toutes déjà données : renvoie une liste vide)\n") . "\n"
            . "NIVEAUX DE LANGAGE DISPONIBLES :\n" . $levelsText . "\n"
            . "SÉQUENCES OÙ IL APPARAÎT :\n" . $content;

        try {
            $response = $this->iaConnection->getTextResponse($systemPrompt, $userMessage, [
                // Suivre un arc sur tout le récit demande de raisonner
                'profile' => IAConnectionService::PROFILE_DEEP,
                'temperature' => 0.3,
                'max_tokens' => 9000,
                'response_format' => $this->verificationFormat(),
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $parsed = $this->parseJson($response);
        if (!$parsed) {
            return ['success' => false, 'message' => 'Réponse du modèle inexploitable.'];
        }

        /** Première et dernière séquence, dans l'ordre du récit */
        $boundsOf = function (array $ids) use ($rank): ?array {
            if (!$ids) {
                return null;
            }
            usort($ids, fn (int $a, int $b) => $rank[$a] <=> $rank[$b]);

            return [$ids[0], $ids[count($ids) - 1]];
        };

        // Les séquences restent citées « [id] » dans les textes : l'id est la
        // clé stable que le front transforme en renvoi cliquable (II.3)
        $text = fn (mixed $value): ?string => trim(strip_tags((string) $value)) ?: null;

        $missingOf = function (array $raw) use ($sequences, $text): array {
            $items = [];
            foreach (array_slice($raw['missing'] ?? [], 0, self::MAX_MISSING) as $entry) {
                $label = $text($entry['text'] ?? '');
                if ($label === null) {
                    continue;
                }
                $sequenceId = is_numeric($entry['sequence_id'] ?? null) ? (int) $entry['sequence_id'] : null;
                $items[] = [
                    'sequenceId' => $sequenceId !== null && isset($sequences[$sequenceId]) ? $sequenceId : null,
                    'text' => $label,
                ];
            }

            return $items;
        };

        // Une piste n'a de sens que pour un manque ; une séquence hors de
        // celles fournies est écartée, pas la piste
        $suggestionOf = function (array $raw, string $status) use ($sequences, $text): ?array {
            $suggestion = $text($raw['suggestion']['text'] ?? '');
            if ($status === 'joue' || $suggestion === null) {
                return null;
            }
            $sequenceId = is_numeric($raw['suggestion']['sequence_id'] ?? null) ? (int) $raw['suggestion']['sequence_id'] : null;

            return [
                'sequenceId' => $sequenceId !== null && isset($sequences[$sequenceId]) ? $sequenceId : null,
                'text' => $suggestion,
            ];
        };

        // ── Arcs de la fiche : verdicts, et place des étapes jouées non placées ──
        $arcVerdicts = [];
        $autoPlaced = [];
        foreach ($parsed['arcs'] ?? [] as $rawArc) {
            $arcId = is_numeric($rawArc['arc_id'] ?? null) ? (int) $rawArc['arc_id'] : null;
            if ($arcId === null || !isset($arcs[$arcId])) {
                continue;
            }
            ['pna' => $pna, 'steps' => $arcSteps] = $arcs[$arcId];

            $steps = [];
            foreach ($rawArc['steps'] ?? [] as $rawStep) {
                $index = is_numeric($rawStep['index'] ?? null) ? (int) $rawStep['index'] : null;
                $status = $rawStep['status'] ?? null;
                if ($index === null || !isset($arcSteps[$index]) || isset($steps[$index]) || !in_array($status, self::STEP_STATUSES, true)) {
                    continue;
                }
                $sequenceIds = $keepSequences($rawStep['sequence_ids'] ?? []);

                $steps[$index] = [
                    'index' => $index,
                    'step' => $arcSteps[$index]['step'],
                    'status' => $status,
                    'sequenceIds' => $sequenceIds,
                    'comment' => $text($rawStep['comment'] ?? ''),
                    'suggestion' => $suggestionOf($rawStep, $status),
                ];

                $bounds = $status === 'joue' ? $boundsOf($sequenceIds) : null;
                if ($bounds && !$arcSteps[$index]['fromSequenceId'] && !$arcSteps[$index]['toSequenceId']) {
                    [$arcSteps[$index]['fromSequenceId'], $arcSteps[$index]['toSequenceId']] = $bounds;
                    $autoPlaced[] = ['personnageNarrativeArcId' => $arcId, 'index' => $index];
                    $arcs[$arcId]['dirty'] = true;
                }
            }
            ksort($steps);

            if (!empty($arcs[$arcId]['dirty'])) {
                $pna->setSteps($arcSteps);
            }

            $arcVerdicts[] = [
                'personnageNarrativeArcId' => $arcId,
                'arcName' => $pna->getNarrativeArc()->getName(),
                'comment' => $text($rawArc['comment'] ?? ''),
                'steps' => array_values($steps),
                'missing' => $missingOf($rawArc),
            ];
        }

        // ── Arcs détectés : hors référentiel, déjà attribué ou en double, écarté ──
        $detectedArcs = [];
        foreach ($parsed['detected_arcs'] ?? [] as $rawArc) {
            $arcId = is_numeric($rawArc['arc_id'] ?? null) ? (int) $rawArc['arc_id'] : null;
            if ($arcId === null || !isset($referentialArcs[$arcId]) || isset($detectedArcs[$arcId])) {
                continue;
            }
            $arc = $referentialArcs[$arcId];
            $arcSteps = array_values($arc->getSteps());

            $found = [];
            foreach ($rawArc['steps'] ?? [] as $rawStep) {
                $index = is_numeric($rawStep['index'] ?? null) ? (int) $rawStep['index'] : null;
                if ($index !== null && isset($arcSteps[$index])) {
                    $found[$index] = $keepSequences($rawStep['sequence_ids'] ?? []);
                }
            }

            $steps = [];
            foreach ($arcSteps as $index => $step) {
                $steps[] = [
                    'index' => $index,
                    'step' => is_array($step) ? (string) ($step['step'] ?? '') : (string) $step,
                    'sequenceIds' => $found[$index] ?? [],
                ];
            }

            $detectedArcs[$arcId] = [
                'narrativeArc' => [
                    'id' => $arc->getId(),
                    'name' => $arc->getName(),
                    'description' => $arc->getDescription(),
                    'tendency' => $arc->getTendency(),
                ],
                'weight' => max(0, min(100, (int) ($rawArc['weight'] ?? 50))),
                'comment' => $text($rawArc['comment'] ?? ''),
                'steps' => $steps,
                'missing' => $missingOf($rawArc),
            ];
            if (count($detectedArcs) >= self::MAX_DETECTED_ARCS) {
                break;
            }
        }

        // ── Fonctions de la fiche : verdicts, et séquences où le texte les joue ──
        $functionVerdicts = [];
        $seenFunctions = [];
        foreach ($parsed['functions'] ?? [] as $rawFunction) {
            $pdfId = is_numeric($rawFunction['function_id'] ?? null) ? (int) $rawFunction['function_id'] : null;
            $status = $rawFunction['status'] ?? null;
            if ($pdfId === null || !isset($functions[$pdfId]) || isset($seenFunctions[$pdfId]) || !in_array($status, self::STEP_STATUSES, true)) {
                continue;
            }
            $seenFunctions[$pdfId] = true;
            $pdf = $functions[$pdfId];
            $sequenceIds = $keepSequences($rawFunction['sequence_ids'] ?? []);

            $functionVerdicts[] = [
                'personnageDramaticFunctionId' => $pdfId,
                'functionName' => $pdf->getDramaticFunction()->getName(),
                'status' => $status,
                'sequenceIds' => $sequenceIds,
                'comment' => $text($rawFunction['comment'] ?? ''),
                'suggestion' => $suggestionOf($rawFunction, $status),
                'missing' => $missingOf($rawFunction),
            ];

            // Champ que l'auteur ne saisit pas : il suit le texte
            $bounds = $status === 'joue' ? $boundsOf($sequenceIds) : null;
            $pdf->setFromSequence($bounds ? $sequences[$bounds[0]] : null);
            $pdf->setToSequence($bounds ? $sequences[$bounds[1]] : null);
        }

        // ── Fonctions détectées ──
        $detectedFunctions = [];
        foreach ($parsed['detected_functions'] ?? [] as $rawFunction) {
            $functionId = is_numeric($rawFunction['function_id'] ?? null) ? (int) $rawFunction['function_id'] : null;
            if ($functionId === null || !isset($referentialFunctions[$functionId]) || isset($detectedFunctions[$functionId])) {
                continue;
            }
            $function = $referentialFunctions[$functionId];
            $detectedFunctions[$functionId] = [
                'dramaticFunction' => [
                    'id' => $function->getId(),
                    'name' => $function->getName(),
                    'description' => $function->getDescription(),
                    'tendency' => $function->getTendency(),
                ],
                'weight' => max(0, min(100, (int) ($rawFunction['weight'] ?? 50))),
                'comment' => $text($rawFunction['comment'] ?? ''),
                'sequenceIds' => $keepSequences($rawFunction['sequence_ids'] ?? []),
                'missing' => $missingOf($rawFunction),
            ];
            if (count($detectedFunctions) >= self::MAX_DETECTED_FUNCTIONS) {
                break;
            }
        }

        // ── Langage : uniquement lu dans le texte, il remplace le précédent ──
        $levels = [];
        foreach ($parsed['language_levels'] ?? [] as $rawLevel) {
            $levelId = is_numeric($rawLevel['level_id'] ?? null) ? (int) $rawLevel['level_id'] : null;
            if ($levelId === null || !isset($languageLevels[$levelId]) || isset($levels[$levelId])) {
                continue;
            }
            $levels[$levelId] = [
                'languageLevelId' => $levelId,
                'weight' => max(0, min(100, (int) ($rawLevel['weight'] ?? 50))),
                'comment' => $text($rawLevel['comment'] ?? ''),
                'quote' => $text($rawLevel['quote'] ?? ''),
            ];
        }

        $sentences = fn (string $key) => array_values(array_filter(array_map($text, $parsed[$key] ?? [])));

        // ── Séquence par séquence : une entrée par séquence fournie, dans l'ordre du récit ──
        $bySequence = [];
        foreach ($parsed['sequences'] ?? [] as $rawSequence) {
            $sequenceId = is_numeric($rawSequence['sequence_id'] ?? null) ? (int) $rawSequence['sequence_id'] : null;
            if ($sequenceId === null || !isset($sequences[$sequenceId]) || isset($bySequence[$sequenceId])) {
                continue;
            }
            $bySequence[$sequenceId] = [
                'sequenceId' => $sequenceId,
                'role' => in_array($rawSequence['role'] ?? null, self::SEQUENCE_ROLES, true) ? $rawSequence['role'] : 'secondaire',
                'summary' => $text($rawSequence['summary'] ?? ''),
            ];
        }
        uksort($bySequence, fn (int $a, int $b) => $rank[$a] <=> $rank[$b]);

        $verification = [
            'analyzedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'arcs' => ['verdicts' => $arcVerdicts, 'detected' => array_values($detectedArcs)],
            'functions' => ['verdicts' => $functionVerdicts, 'detected' => array_values($detectedFunctions)],
            'sequences' => array_values($bySequence),
            'novelties' => $sentences('novelties'),
            'contradictions' => $sentences('contradictions'),
            'autoPlaced' => $autoPlaced,
        ];

        $personnage->setVerification($verification)
            ->setAnalysis($this->cleanHtml($parsed['analysis'] ?? '') ?: null)
            ->setLanguageAnalysis($this->cleanHtml($parsed['language_analysis'] ?? '') ?: null);
        $this->personnageService->syncLanguageLevels($personnage, array_values($levels));
        $this->em->flush();

        return ['success' => true, 'verification' => $verification];
    }

    private function describeFunction(DramaticFunction $function): string
    {
        $characteristics = $function->getCharacteristics();

        return $function->getName() . ' : ' . ($function->getDescription() ?? '')
            . ($characteristics ? ' (' . implode(', ', $characteristics) . ')' : '');
    }
}
