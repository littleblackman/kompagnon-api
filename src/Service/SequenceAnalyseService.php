<?php

namespace App\Service;

use App\Entity\Sequence;
use App\Repository\CriteriaRepository;

/**
 * Analyse d'une séquence par le modèle.
 *
 * Deux questions distinctes, donc deux prompts : juger si un texte tient
 * l'intention annoncée n'a rien à voir avec lui attribuer des intensités.
 * Les mélanger dans un seul appel dilue les deux réponses.
 */
class SequenceAnalyseService
{
    /** Verdicts d'alignement, du plus faible au plus fort */
    public const LEVEL_OFF = 1;
    public const LEVEL_PARTIAL = 2;
    public const LEVEL_OK = 3;

    public function __construct(
        private IAConnectionService $iaConnection,
        private CriteriaRepository $criteriaRepository,
    ) {
    }

    /**
     * Texte effectivement écrit dans la séquence, titres de scènes compris.
     */
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

    /**
     * Le modèle encadre parfois sa réponse de texte ou de balises Markdown.
     * On isole l'objet JSON du premier { au dernier }.
     */
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

    /** Ramène un verdict du modèle dans 1..3, ou null s'il est inexploitable */
    private function normaliseLevel(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }
        $level = (int) $value;

        return $level >= self::LEVEL_OFF && $level <= self::LEVEL_OK ? $level : null;
    }

    /**
     * Le contenu écrit tient-il ce que l'intention, l'idée esthétique et
     * l'information annoncent ?
     *
     * @return array{success: bool, message?: string, analysis?: array}
     */
    public function analyseAlignment(Sequence $sequence, ?string $only = null): array
    {
        $content = $this->extractContent($sequence);

        if ($content === '') {
            return [
                'success' => false,
                'message' => 'Cette séquence ne contient aucun texte à analyser.',
            ];
        }

        $fields = [
            'intention' => $sequence->getIntention(),
            'aesthetic' => $sequence->getAestheticIdea(),
            'information' => $sequence->getInformation(),
        ];

        // Analyse ciblée : un bouton par ligne n'interroge que sa ligne
        if ($only !== null) {
            if (!array_key_exists($only, $fields)) {
                return ['success' => false, 'message' => 'Champ inconnu : ' . $only];
            }
            $fields = [$only => $fields[$only]];
        }

        $declared = array_filter($fields, fn ($v) => trim((string) $v) !== '');
        if (!$declared) {
            return [
                'success' => false,
                'message' => $only !== null
                    ? 'Ce champ est vide : il n\'y a rien à confronter au texte.'
                    : 'Renseignez au moins une intention, une idée esthétique ou une information.',
            ];
        }

        $systemPrompt = <<<'PROMPT'
Tu es un lecteur professionnel de manuscrits. On te donne le texte d'une séquence
et les intentions que l'auteur avait déclarées pour elle.

Ta seule tâche : dire si le texte tient chacune de ces déclarations.

Barème, strictement :
3 = tenu — le texte réalise clairement ce qui est annoncé
2 = partiellement — on le devine, mais c'est faible ou inégal
1 = non tenu — le texte ne fait pas ce qui est annoncé

Règles :
- Juge le texte tel qu'il est, pas ce qu'il pourrait devenir.
- N'invente rien : si un champ n'est pas fourni, renvoie null pour ce champ.
- Le commentaire fait une phrase, cite un élément concret du texte, et
  s'adresse à l'auteur sans le flatter ni l'accabler.
- Réponds uniquement par un objet JSON, sans texte autour.

Format exact :
{
  "intention": {"level": 1, "comment": "..."},
  "aesthetic": {"level": 2, "comment": "..."},
  "information": {"level": 3, "comment": "..."}
}
PROMPT;

        $userMessage = "SÉQUENCE : " . $sequence->getName() . "\n\n";
        foreach ($declared as $key => $value) {
            $label = match ($key) {
                'intention' => 'INTENTION DÉCLARÉE',
                'aesthetic' => 'IDÉE ESTHÉTIQUE DÉCLARÉE',
                'information' => 'INFORMATION À FAIRE PASSER',
            };
            $userMessage .= $label . " : " . trim(strip_tags((string) $value)) . "\n";
        }
        $userMessage .= "\nTEXTE DE LA SÉQUENCE :\n" . $content;

        try {
            $response = $this->iaConnection->getTextResponse($systemPrompt, $userMessage, [
                'profile' => IAConnectionService::PROFILE_FAST, // lancée séquence par séquence
                'temperature' => 0.2, // un verdict doit être stable d'un appel à l'autre
                // Le modèle léger déborde souvent de la phrase demandée : un
                // JSON coupé net par max_tokens devient inexploitable
                'max_tokens' => 1200,
                'response_format' => ['type' => 'json_object'],
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $parsed = $this->parseJson($response);
        $analysis = [];

        foreach (array_keys($fields) as $key) {
            // Un champ non déclaré n'est pas jugé, même si le modèle a répondu
            if (!isset($declared[$key])) {
                $analysis[$key] = null;
                continue;
            }

            $level = $this->normaliseLevel($parsed[$key]['level'] ?? null);
            $analysis[$key] = $level === null ? null : [
                'level' => $level,
                'comment' => trim((string) ($parsed[$key]['comment'] ?? '')),
            ];
        }

        if (!array_filter($analysis)) {
            return ['success' => false, 'message' => 'Réponse du modèle inexploitable.'];
        }

        return ['success' => true, 'analysis' => $analysis];
    }

    /**
     * Propose une note d'intensité par critère, sur la même échelle 0-10 que
     * la saisie manuelle — l'auteur reste libre de les reprendre.
     *
     * @return array{success: bool, message?: string, ratings?: array}
     */
    public function suggestCriterias(Sequence $sequence): array
    {
        $content = $this->extractContent($sequence);

        if ($content === '') {
            return [
                'success' => false,
                'message' => 'Cette séquence ne contient aucun texte à analyser.',
            ];
        }

        $criterias = $this->criteriaRepository->findAll();
        if (!$criterias) {
            return ['success' => false, 'message' => 'Aucun critère n\'est configuré.'];
        }

        $list = '';
        foreach ($criterias as $criteria) {
            $list .= '- ' . $criteria->getName();
            if ($criteria->getExplanation()) {
                $list .= ' : ' . $criteria->getExplanation();
            }
            $list .= "\n";
        }

        $systemPrompt = <<<PROMPT
Tu es un lecteur professionnel de manuscrits. On te donne le texte d'une séquence.

Ta seule tâche : noter chaque critère de 0 à 10 d'après ce que le texte produit
réellement sur un lecteur.

Barème : 0 = absent, 5 = présent sans intensité particulière, 10 = très marqué.

Règles :
- Note ce qui est écrit, pas ce que la séquence semble vouloir faire.
- Une note basse n'est pas un défaut : une scène de transition est censée être
  peu intense.
- Le commentaire fait une phrase courte et justifie la note par le texte.
- Reprends exactement les noms de critères fournis.
- Réponds uniquement par un objet JSON, sans texte autour.

Critères à noter :
$list
Format exact :
{"ratings": [{"name": "Nom exact du critère", "value": 7, "comment": "..."}]}
PROMPT;

        $userMessage = "SÉQUENCE : " . $sequence->getName() . "\n\nTEXTE :\n" . $content;

        try {
            $response = $this->iaConnection->getTextResponse($systemPrompt, $userMessage, [
                'profile' => IAConnectionService::PROFILE_FAST, // lancée séquence par séquence
                'temperature' => 0.3,
                'max_tokens' => 1500,
                'response_format' => ['type' => 'json_object'],
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $parsed = $this->parseJson($response);
        $suggested = [];

        foreach ($parsed['ratings'] ?? [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || !is_numeric($row['value'] ?? null)) {
                continue;
            }

            // Rattacher au critère réel : le modèle rend un nom, le front a
            // besoin de l'identifiant pour enregistrer la note.
            $match = null;
            foreach ($criterias as $criteria) {
                if (mb_strtolower($criteria->getName()) === mb_strtolower($name)) {
                    $match = $criteria;
                    break;
                }
            }
            if (!$match) {
                continue;
            }

            $suggested[] = [
                'criteriaId' => $match->getId(),
                'name' => $match->getName(),
                'value' => max(0, min(10, (int) $row['value'])),
                'comment' => trim((string) ($row['comment'] ?? '')),
            ];
        }

        if (!$suggested) {
            return ['success' => false, 'message' => 'Réponse du modèle inexploitable.'];
        }

        return ['success' => true, 'ratings' => $suggested];
    }

    /** Longueur des colonnes intention / aesthetic_idea / information */
    private const FIELD_MAX_LENGTH = 255;

    /**
     * Coupe au dernier mot entier qui tient dans la colonne, plutôt que de
     * laisser la base tronquer en plein mot.
     */
    private function fitField(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
        if (mb_strlen($text) <= self::FIELD_MAX_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::FIELD_MAX_LENGTH - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false ? mb_substr($cut, 0, $space) : $cut, " ,;:") . '…';
    }

    /**
     * Propose une rédaction de l'intention, de l'idée esthétique et de
     * l'information, tirée de ce que le texte fait réellement. Rien n'est
     * enregistré : l'auteur reprend, retouche ou ignore.
     *
     * @return array{success: bool, message?: string, fields?: array}
     */
    public function suggestFields(Sequence $sequence): array
    {
        $content = $this->extractContent($sequence);

        if ($content === '') {
            return [
                'success' => false,
                'message' => 'Cette séquence ne contient aucun texte à analyser.',
            ];
        }

        $systemPrompt = <<<'PROMPT'
Tu es un dramaturge qui aide un auteur à tenir ses notes de travail. On te donne
le texte d'une séquence.

Ta seule tâche : rédiger trois notes courtes qui décrivent ce que la séquence
fait, comme l'auteur les écrirait dans son carnet.

- intention : l'émotion ou la sensation que la séquence produit chez le lecteur.
- aesthetic : l'approche artistique — rythme, point de vue, registre, images,
  traitement du temps ou de l'espace.
- information : ce que le lecteur apprend de nouveau sur l'histoire ou les
  personnages.

Règles :
- Décris ce qui est écrit, pas ce que la séquence pourrait devenir.
- Pas de jugement de valeur, pas de conseil : ce sont des notes, pas une critique.
- Une phrase par note, 200 caractères au plus, sans guillemets autour.
- Écris en français, au présent.
- Réponds uniquement par un objet JSON, sans texte autour.

Format exact :
{"intention": "...", "aesthetic": "...", "information": "..."}
PROMPT;

        $userMessage = "SÉQUENCE : " . $sequence->getName() . "\n\nTEXTE :\n" . $content;

        try {
            $response = $this->iaConnection->getTextResponse($systemPrompt, $userMessage, [
                'profile' => IAConnectionService::PROFILE_FAST, // lancée séquence par séquence
                'temperature' => 0.5, // une rédaction gagne à varier d'un appel à l'autre
                // Même marge que les deux autres analyses : un JSON coupé est perdu
                'max_tokens' => 1000,
                'response_format' => ['type' => 'json_object'],
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $parsed = $this->parseJson($response);
        $fields = [];

        foreach (['intention', 'aesthetic', 'information'] as $key) {
            $value = is_string($parsed[$key] ?? null) ? $this->fitField($parsed[$key]) : '';
            $fields[$key] = $value !== '' ? $value : null;
        }

        if (!array_filter($fields)) {
            return ['success' => false, 'message' => 'Réponse du modèle inexploitable.'];
        }

        return ['success' => true, 'fields' => $fields];
    }
}
