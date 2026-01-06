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
     * Extrait le contenu organisationnel d'un projet
     * (descriptions des parties et séquences)
     */
    public function extractOrganizationalContent(Project $project): string
    {
        $content = "# " . $project->getName() . "\n\n";

        if ($project->getDescription()) {
            $content .= strip_tags($project->getDescription()) . "\n\n";
        }

        foreach ($project->getParts() as $part) {
            $content .= "## " . $part->getName() . "\n";

            if ($part->getDescription()) {
                $content .= strip_tags($part->getDescription()) . "\n\n";
            }

            foreach ($part->getSequences() as $sequence) {
                $content .= "### " . $sequence->getName() . "\n";

                if ($sequence->getDescription()) {
                    $content .= strip_tags($sequence->getDescription()) . "\n\n";
                }
            }
        }

        return $content;
    }

    /**
     * Extrait le contenu complet d'un projet
     * (descriptions + contenu des scènes)
     */
    public function extractFullContent(Project $project): string
    {
        $content = "# " . $project->getName() . "\n\n";

        if ($project->getDescription()) {
            $content .= strip_tags($project->getDescription()) . "\n\n";
        }

        foreach ($project->getParts() as $part) {
            $content .= "## " . $part->getName() . "\n";

            if ($part->getDescription()) {
                $content .= strip_tags($part->getDescription()) . "\n\n";
            }

            foreach ($part->getSequences() as $sequence) {
                $content .= "### " . $sequence->getName() . "\n";

                if ($sequence->getDescription()) {
                    $content .= strip_tags($sequence->getDescription()) . "\n\n";
                }

                foreach ($sequence->getScenes() as $scene) {
                    $content .= "#### " . $scene->getName() . "\n";

                    if ($scene->getContent()) {
                        $content .= strip_tags($scene->getContent()) . "\n\n";
                    }
                }
            }
        }

        return $content;
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

            // Limiter la longueur si nécessaire (Mistral a une limite de tokens)
            if (strlen($content) > 15000) {
                $content = substr($content, 0, 15000) . "\n\n[...contenu tronqué...]";
            }

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
