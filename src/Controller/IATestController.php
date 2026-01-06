<?php

namespace App\Controller;

use App\Service\IAConnectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller de test pour l'IA
 * Routes temporaires pour tester la connexion
 */
class IATestController extends AbstractController
{
    #[Route('/api/ia/test/connection', name: 'api_ia_test_connection', methods: ['GET'])]
    public function testConnection(IAConnectionService $iaService): JsonResponse
    {
        try {
            $isConnected = $iaService->testConnection();
            $config = $iaService->getConfig();
            

            if ($isConnected) {
                return $this->json([
                    'success' => true,
                    'message' => 'Connexion à l\'IA réussie !',
                    'config' => $config
                ], 200);
            }

            return $this->json([
                'success' => false,
                'message' => 'La connexion a échoué',
                'config' => $config
            ], 500);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/ia/test/simple', name: 'api_ia_test_simple', methods: ['POST'])]
    public function testSimpleRequest(Request $request, IAConnectionService $iaService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $message = $data['message'] ?? 'Dis bonjour';

            $response = $iaService->getTextResponse(
                'Tu es un assistant de test sympathique.',
                $message,
                ['max_tokens' => 100]
            );

            return $this->json([
                'success' => true,
                'userMessage' => $message,
                'aiResponse' => $response
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/ia/test/full', name: 'api_ia_test_full', methods: ['POST'])]
    public function testFullRequest(Request $request, IAConnectionService $iaService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $systemPrompt = $data['systemPrompt'] ?? 'Tu es un assistant utile.';
            $userMessage = $data['userMessage'] ?? 'Test';
            $options = $data['options'] ?? [];

            $fullResponse = $iaService->sendRequest($systemPrompt, $userMessage, $options);

            return $this->json([
                'success' => true,
                'fullResponse' => $fullResponse
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/ia/test/analyse-text', name: 'api_ia_test_analyse_text', methods: ['POST'])]
    public function testAnalyseText(Request $request, IAConnectionService $iaService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $text = $data['text'] ?? '';

            if (empty($text)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le texte est obligatoire'
                ], 400);
            }

            $systemPrompt = "Tu es un expert en analyse littéraire. Analyse le texte fourni de manière concise et constructive.";
            $userMessage = "Analyse ce texte et donne-moi 3 points clés (forces et faiblesses):\n\n" . $text;

            $response = $iaService->getTextResponse($systemPrompt, $userMessage, [
                'temperature' => 0.7,
                'max_tokens' => 500
            ]);

            return $this->json([
                'success' => true,
                'text' => $text,
                'analysis' => $response
            ], 200);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}
