<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * Service de connexion à l'IA (Mistral)
 * Service générique qui gère la connexion et les appels API
 * Sans exposer les détails d'implémentation du LLM
 */
class IAConnectionService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

        // Configuration Mistral API
        $this->apiKey = $_ENV['MISTRAL_API_KEY'] ?? '';
        $this->apiUrl = $_ENV['MISTRAL_API_URL'] ?? 'https://api.mistral.ai/v1/chat/completions';
        $this->model = $_ENV['MISTRAL_MODEL'] ?? 'mistral-large-latest';
    }

    /**
     * Envoie une requête au LLM et retourne la réponse
     *
     * @param string $systemPrompt Le prompt système (instructions pour l'IA)
     * @param string $userMessage Le message de l'utilisateur
     * @param array $options Options supplémentaires (temperature, max_tokens, etc.)
     * @return array La réponse complète de l'API
     * @throws \Exception Si la connexion échoue
     */
    public function sendRequest(string $systemPrompt, string $userMessage, array $options = []): array
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
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'top_p' => 1.0,
        ];

        $requestOptions = array_merge($defaultOptions, $options);

        // Payload de la requête
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $requestOptions['temperature'],
            'max_tokens' => $requestOptions['max_tokens'],
            'top_p' => $requestOptions['top_p'],
        ];

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
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
        $response = $this->sendRequest($systemPrompt, $userMessage, $options);

        // Extraire le texte de la réponse
        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }

        throw new \Exception('Format de réponse inattendu de l\'API');
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
            'model' => $this->model,
            'api_url' => $this->apiUrl,
            'api_key_configured' => !empty($this->apiKey),
        ];
    }
}
