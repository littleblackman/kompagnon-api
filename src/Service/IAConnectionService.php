<?php

namespace App\Service;

use App\Entity\ErrorLog;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * Service de connexion à l'IA (Mistral)
 * Service générique qui gère la connexion et les appels API
 * Sans exposer les détails d'implémentation du LLM
 *
 * Deux profils, pour ne payer la réflexion que là où elle sert :
 * - fast : modèle léger sans réflexion, pour les analyses fréquentes
 * - deep : modèle qui raisonne, pour les lectures littéraires fines
 * Chaque modèle se règle dans le .env (MISTRAL_MODEL_FAST, MISTRAL_MODEL_DEEP).
 */
class IAConnectionService
{
    public const PROFILE_FAST = 'fast';
    public const PROFILE_DEEP = 'deep';

    /**
     * Budget de réflexion ajouté au max_tokens demandé : l'appelant raisonne
     * en taille de réponse, la réflexion consomme le même compteur.
     */
    private const REASONING_TOKENS = 6000;

    /**
     * Délai d'attente de la réponse, en secondes. Le défaut du client HTTP
     * (60 s d'inactivité) coupait les analyses longues d'un modèle qui
     * raisonne avant qu'il ait répondu.
     */
    private const DEFAULT_TIMEOUT = 300;

    private HttpClientInterface $httpClient;
    private string $apiKey;
    private string $apiUrl;

    /** @var array<string, array{model: string, reasoning: bool}> */
    private array $profiles;

    public function __construct(
        HttpClientInterface $httpClient,
        private ErrorLogger $errorLogger,
        private Connection $connection
    ) {
        $this->httpClient = $httpClient;

        // Configuration Mistral API
        $this->apiKey = $this->env('MISTRAL_API_KEY') ?? '';
        $this->apiUrl = $this->env('MISTRAL_API_URL') ?? 'https://api.mistral.ai/v1/chat/completions';

        // MISTRAL_MODEL reste accepté pour deep, c'était l'unique réglage avant les profils
        $this->profiles = [
            self::PROFILE_FAST => [
                'model' => $this->env('MISTRAL_MODEL_FAST') ?? 'ministral-14b-latest',
                'reasoning' => false,
            ],
            self::PROFILE_DEEP => [
                'model' => $this->env('MISTRAL_MODEL_DEEP') ?? $this->env('MISTRAL_MODEL') ?? 'mistral-large-4',
                'reasoning' => filter_var($this->env('MISTRAL_MODEL_DEEP_REASONING') ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
        ];
    }

    /**
     * Une variable posée par le serveur (fastcgi_param, SetEnv) n'arrive pas
     * dans $_ENV quand variables_order l'exclut, réglage courant en prod :
     * on la cherche aussi dans $_SERVER et getenv().
     */
    private function env(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Envoie une requête au LLM et retourne la réponse
     *
     * @param string $systemPrompt Le prompt système (instructions pour l'IA)
     * @param string $userMessage Le message de l'utilisateur
     * @param array $options Options supplémentaires (profile, temperature, max_tokens, response_format, reasoning_effort)
     * @return array La réponse complète de l'API
     * @throws \Exception Si la connexion échoue
     */
    public function sendRequest(string $systemPrompt, string $userMessage, array $options = []): array
    {
        $start = microtime(true);
        try {
            try {
                return $this->call($systemPrompt, $userMessage, $options);
            } finally {
                // Avant le journal d'erreur, qui écrit lui aussi en base
                $this->releaseIdleConnection();
            }
        } catch (\Exception $e) {
            // Les appelants rendent l'échec en 422 : sans cette trace, une
            // panne de l'IA (clé absente, quota, délai) ne laissait rien au journal
            $this->logFailure($e->getMessage(), 'error', $options, $start);
            throw $e;
        }
    }

    /**
     * Un modèle qui raisonne peut répondre après plusieurs minutes : MySQL a
     * entre-temps fermé la connexion restée inactive (wait_timeout), et le
     * flush qui suit l'analyse échouait sur « MySQL server has gone away ».
     * On la referme de notre côté ; Doctrine en rouvre une à la requête
     * suivante. Jamais au milieu d'une transaction, qui serait perdue.
     */
    private function releaseIdleConnection(): void
    {
        if (!$this->connection->isTransactionActive()) {
            $this->connection->close();
        }
    }

    private function call(string $systemPrompt, string $userMessage, array $options): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('MISTRAL_API_KEY n\'est pas configurée');
        }

        // Préparer les messages
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => $userMessage
            ]
        ];

        // Options par défaut
        $defaultOptions = [
            'profile' => self::PROFILE_FAST,
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'top_p' => 1.0,
            'reasoning_effort' => 'low',
            'timeout' => self::DEFAULT_TIMEOUT,
        ];

        $requestOptions = array_merge($defaultOptions, $options);

        $profile = $this->profiles[$requestOptions['profile']] ?? null;
        if (!$profile) {
            throw new \Exception('Profil IA inconnu : ' . $requestOptions['profile']);
        }

        // Payload de la requête
        $payload = [
            'model' => $profile['model'],
            'messages' => $messages,
            'temperature' => $requestOptions['temperature'],
            'max_tokens' => $requestOptions['max_tokens'],
            'top_p' => $requestOptions['top_p'],
        ];

        // Sortie structurée : ['type' => 'json_object'] ou
        // ['type' => 'json_schema', 'json_schema' => [...]]
        if (!empty($requestOptions['response_format'])) {
            $payload['response_format'] = $requestOptions['response_format'];
        }

        // Les modèles sans réflexion refusent le paramètre : on ne l'envoie qu'aux autres.
        // low | medium | high, ceux qui raisonnent ne descendent pas plus bas.
        if ($profile['reasoning']) {
            $payload['reasoning_effort'] = $requestOptions['reasoning_effort'];
            $payload['max_tokens'] += self::REASONING_TOKENS;
        }

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => $requestOptions['timeout'],
                'max_duration' => $requestOptions['timeout'],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new \Exception('Erreur API Mistral: ' . $response->getContent(false));
            }

            $data = $response->toArray();

            return $data;

        } catch (TransportException $e) {
            throw new \Exception('Erreur de connexion à l\'API Mistral: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors de l\'appel à l\'API: ' . $e->getMessage());
        }
    }

    /**
     * Méthode simplifiée pour obtenir directement le texte de réponse
     *
     * @param string $systemPrompt
     * @param string $userMessage
     * @param array $options
     * @return string Le contenu texte de la réponse
     * @throws \Exception
     */
    public function getTextResponse(string $systemPrompt, string $userMessage, array $options = []): string
    {
        $start = microtime(true);
        $response = $this->sendRequest($systemPrompt, $userMessage, $options);

        // Réponse coupée faute de tokens : le JSON attendu derrière sera incomplet
        if (($response['choices'][0]['finish_reason'] ?? null) === 'length') {
            $this->logFailure('Réponse de l\'IA tronquée (max_tokens atteint)', 'warning', $options, $start, $response['usage'] ?? null);
        }

        $content = $response['choices'][0]['message']['content'] ?? null;

        if (is_string($content)) {
            return $content;
        }

        // Les modèles qui raisonnent rendent une liste de blocs : on ne garde
        // que le texte de la réponse, pas la réflexion qui la précède
        if (is_array($content)) {
            $text = '';
            foreach ($content as $block) {
                if (($block['type'] ?? null) === 'text') {
                    $text .= $block['text'] ?? '';
                }
            }
            if ($text !== '') {
                return $text;
            }
        }

        $this->logFailure('Format de réponse inattendu de l\'API', 'error', $options, $start);
        throw new \Exception('Format de réponse inattendu de l\'API');
    }

    private function logFailure(string $message, string $level, array $options, float $start, ?array $usage = null): void
    {
        $profile = $options['profile'] ?? self::PROFILE_FAST;

        $this->errorLogger->log([
            'source' => ErrorLog::SOURCE_API,
            'level' => $level,
            'message' => $message,
            // Regroupe les échecs IA entre eux, quelle que soit la route
            'file' => 'IA ' . $profile,
            'context' => array_filter([
                'model' => $this->profiles[$profile]['model'] ?? null,
                'maxTokens' => $options['max_tokens'] ?? null,
                'durationSeconds' => round(microtime(true) - $start, 1),
                'usage' => $usage,
            ]),
        ]);
    }

    /**
     * Test de connexion simple
     *
     * @return bool True si la connexion fonctionne
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->getTextResponse(
                'Tu es un assistant de test.',
                'Réponds juste "OK" si tu me reçois.',
                ['max_tokens' => 10]
            );

            return !empty($response);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Récupère les informations de configuration (sans exposer la clé API)
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'models' => array_map(fn ($p) => $p['model'], $this->profiles),
            'api_url' => $this->apiUrl,
            'api_key_configured' => !empty($this->apiKey),
        ];
    }
}
