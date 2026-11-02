<?php

namespace App\Service;

use App\Entity\Project;
use App\Repository\GenreRepository;
use App\Repository\SubgenreRepository;
use App\Repository\NarrativeStructureRepository;

/**
 * Service d'analyse de projet avec IA
 * Utilise IAConnectionService pour analyser le contenu et suggérer genre/subgenre/structure
 */
class ProjectAnalyseService
{
    private IAConnectionService $iaConnection;
    private GenreRepository $genreRepository;
    private SubgenreRepository $subgenreRepository;
    private NarrativeStructureRepository $narrativeStructureRepository;

    public function __construct(
        IAConnectionService $iaConnection,
        GenreRepository $genreRepository,
        SubgenreRepository $subgenreRepository,
        NarrativeStructureRepository $narrativeStructureRepository
    ) {
        $this->iaConnection = $iaConnection;
        $this->genreRepository = $genreRepository;
        $this->subgenreRepository = $subgenreRepository;
        $this->narrativeStructureRepository = $narrativeStructureRepository;
    }

    /**
     * Budget de caractères envoyé au modèle. Le texte complet d'un roman le
     * dépasse de loin : on garde toute la structure et on répartit le reste
     * entre les scènes, pour que l'IA lise le livre entier et pas son incipit.
     */
    private const CONTENT_BUDGET = 60000;

    /** En dessous, un extrait de scène ne dit plus rien du texte */
    private const MIN_SCENE_EXCERPT = 300;

    /**
     * Extrait le contenu organisationnel d'un projet
     * (descriptions et intentions des parties et séquences)
     */
    public function extractOrganizationalContent(Project $project): string
    {
        return $this->buildContent($project, false);
    }

    /**
     * Extrait le contenu complet d'un projet : la structure, plus un extrait
     * de chaque scène dimensionné pour tenir dans le budget
     */
    public function extractFullContent(Project $project): string
    {
        return $this->buildContent($project, true);
    }

    private function buildContent(Project $project, bool $withScenes): string
    {
        $outline = $this->buildOutline($project);
        if (!$withScenes) {
            return $this->truncate(implode('', array_map(fn ($block) => $block['text'], $outline)), self::CONTENT_BUDGET);
        }

        $outlineLength = array_sum(array_map(fn ($block) => mb_strlen($block['text']), $outline));
        $sceneCount = array_sum(array_map(fn ($block) => count($block['scenes']), $outline));

        // La structure passe en entier ; ce qui reste se partage entre les scènes
        $perScene = $sceneCount
            ? max(self::MIN_SCENE_EXCERPT, intdiv(max(0, self::CONTENT_BUDGET - $outlineLength), $sceneCount))
            : 0;

        $content = '';
        foreach ($outline as $block) {
            $content .= $block['text'];
            foreach ($block['scenes'] as $scene) {
                $text = $this->cleanText($scene->getContent());
                if ($text === '') {
                    continue;
                }
                $content .= '#### ' . $scene->getName() . "\n" . $this->excerpt($text, $perScene) . "\n\n";
            }
        }

        return $this->truncate($content, self::CONTENT_BUDGET * 2);
    }

    /**
     * Le plan du projet, séquence par séquence, avec les scènes à extraire
     *
     * @return array<int, array{text: string, scenes: array}>
     */
    private function buildOutline(Project $project): array
    {
        $blocks = [];
        $head = '# ' . $project->getName() . "\n\n";
        if ($project->getDescription()) {
            $head .= $this->cleanText($project->getDescription()) . "\n\n";
        }
        $blocks[] = ['text' => $head, 'scenes' => []];

        foreach ($project->getParts() as $part) {
            $text = '## ' . $part->getName() . "\n";
            if ($part->getDescription()) {
                $text .= $this->cleanText($part->getDescription()) . "\n\n";
            }
            $blocks[] = ['text' => $text, 'scenes' => []];

            foreach ($part->getSequences() as $sequence) {
                $text = '### ' . $sequence->getName() . "\n";
                foreach ([
                    'Description' => $sequence->getDescription(),
                    'Intention' => $sequence->getIntention(),
                    'Information' => $sequence->getInformation(),
                ] as $label => $value) {
                    if ($value && ($clean = $this->cleanText($value)) !== '') {
                        $text .= $label . ' : ' . $clean . "\n";
                    }
                }
                $blocks[] = ['text' => $text . "\n", 'scenes' => $sequence->getScenes()->toArray()];
            }
        }

        return $blocks;
    }

    /** HTML de TinyMCE → texte brut, entités décodées et espaces resserrés */
    private function cleanText(?string $html): string
    {
        // Une fin de paragraphe vaut un espace, sinon « …y va.</p><p>Le petit » se colle
        $html = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6])>#i', ' ', (string) $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Début et fin de la scène : l'ouverture pose la situation, la chute dit
     * où elle mène. Le milieu est élidé.
     */
    private function excerpt(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $head = (int) round($length * 0.6);
        $tail = $length - $head;

        return mb_substr($text, 0, $head) . ' […] ' . mb_substr($text, -$tail);
    }

    /** Coupe en caractères, jamais au milieu d'un caractère multi-octets */
    private function truncate(string $content, int $length): string
    {
        if (mb_strlen($content) <= $length) {
            return $content;
        }

        return mb_substr($content, 0, $length) . "\n\n[...contenu tronqué...]";
    }

    /**
     * Analyse le projet avec l'IA et retourne des suggestions
     *
     * @param Project $project
     * @param bool $useFullContent True pour texte complet, false pour notes d'organisation
     * @return array Résultat avec suggestions de genre/subgenre/structure
     */
    public function analyzeProject(Project $project, bool $useFullContent = false): array
    {
        try {
            // Extraire le contenu selon le mode
            $content = $useFullContent
                ? $this->extractFullContent($project)
                : $this->extractOrganizationalContent($project);

            // Récupérer les genres, sous-genres et structures disponibles
            $availableGenres = $this->genreRepository->findAll();
            $availableSubgenres = $this->subgenreRepository->findAll();
            $availableStructures = $this->narrativeStructureRepository->findAll();

            // Construire les listes pour le prompt
            $genresList = array_map(fn($g) => $g->getName(), $availableGenres);
            $subgenresList = array_map(fn($s) => $s->getName() . ' (genre: ' . $s->getGenre()->getName() . ')', $availableSubgenres);
            $structuresList = array_map(fn($s) => $s->getName(), $availableStructures);

            $systemPrompt = "Tu es un expert en analyse narrative et littéraire. Tu dois analyser le contenu fourni et déterminer :
1. Le genre littéraire principal
2. Le sous-genre approprié
3. La structure narrative utilisée

Voici les options disponibles dans la base de données :

GENRES DISPONIBLES :
" . implode(", ", $genresList) . "

SOUS-GENRES DISPONIBLES :
" . implode("\n", $subgenresList) . "

STRUCTURES NARRATIVES DISPONIBLES :
" . implode(", ", $structuresList) . "

IMPORTANT : Tu dois choisir parmi ces options existantes uniquement. Ne propose pas de nouveaux genres/sous-genres/structures.

Réponds au format JSON suivant :
{
  \"genre\": \"nom du genre\",
  \"subgenre\": \"nom du sous-genre\",
  \"structure\": \"nom de la structure narrative\",
  \"confidence\": \"high|medium|low\",
  \"explanation\": \"explication courte de ton choix\"
}";

            $userMessage = "Analyse ce contenu et détermine le genre, sous-genre et structure narrative :\n\n" . $content;

            $response = $this->iaConnection->getTextResponse($systemPrompt, $userMessage, [
                // Rare et structurant pour le projet : on paie la réflexion
                'profile' => IAConnectionService::PROFILE_DEEP,
                'temperature' => 0.3, // Basse température pour plus de cohérence
                'max_tokens' => 800
            ]);

            // Parser la réponse JSON
            $analysis = $this->parseAnalysisResponse($response);

            // Trouver les entités correspondantes
            $suggestedGenre = null;
            $suggestedSubgenre = null;
            $suggestedStructure = null;

            if (isset($analysis['genre'])) {
                $suggestedGenre = $this->findGenreByName($analysis['genre']);
            }

            if (isset($analysis['subgenre'])) {
                $suggestedSubgenre = $this->findSubgenreByName($analysis['subgenre']);
            }

            if (isset($analysis['structure'])) {
                $suggestedStructure = $this->findStructureByName($analysis['structure']);
            }

            return [
                'success' => true,
                'contentType' => $useFullContent ? 'full' : 'organizational',
                'rawResponse' => $response,
                'analysis' => $analysis,
                'suggestions' => [
                    'genre' => $suggestedGenre ? [
                        'id' => $suggestedGenre->getId(),
                        'name' => $suggestedGenre->getName()
                    ] : null,
                    'subgenre' => $suggestedSubgenre ? [
                        'id' => $suggestedSubgenre->getId(),
                        'name' => $suggestedSubgenre->getName(),
                        'genreName' => $suggestedSubgenre->getGenre()->getName()
                    ] : null,
                    'structure' => $suggestedStructure ? [
                        'id' => $suggestedStructure->getId(),
                        'name' => $suggestedStructure->getName()
                    ] : null,
                ],
                'confidence' => $analysis['confidence'] ?? 'unknown',
                'explanation' => $analysis['explanation'] ?? ''
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'analyse: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Parse la réponse JSON de l'IA
     */
    private function parseAnalysisResponse(string $response): array
    {
        // Extraire le JSON de la réponse (parfois l'IA ajoute du texte avant/après)
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches)) {
            $jsonStr = $matches[0];
            $decoded = json_decode($jsonStr, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Si pas de JSON valide, retourner un tableau vide
        return [];
    }

    /**
     * Trouve un genre par son nom (insensible à la casse)
     */
    private function findGenreByName(string $name): ?object
    {
        $genres = $this->genreRepository->findAll();
        foreach ($genres as $genre) {
            if (strcasecmp($genre->getName(), $name) === 0) {
                return $genre;
            }
        }
        return null;
    }

    /**
     * Trouve un sous-genre par son nom (insensible à la casse)
     */
    private function findSubgenreByName(string $name): ?object
    {
        $subgenres = $this->subgenreRepository->findAll();
        foreach ($subgenres as $subgenre) {
            if (strcasecmp($subgenre->getName(), $name) === 0) {
                return $subgenre;
            }
        }
        return null;
    }

    /**
     * Trouve une structure narrative par son nom (insensible à la casse)
     */
    private function findStructureByName(string $name): ?object
    {
        $structures = $this->narrativeStructureRepository->findAll();
        foreach ($structures as $structure) {
            if (strcasecmp($structure->getName(), $name) === 0) {
                return $structure;
            }
        }
        return null;
    }
}
