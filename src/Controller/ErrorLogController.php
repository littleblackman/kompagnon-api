<?php

namespace App\Controller;

use App\Entity\ErrorLog;
use App\Service\ErrorLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Point d'entrée des erreurs remontées par le front.
 * Accessible sans être connecté : une erreur sur /login doit aussi arriver.
 */
class ErrorLogController extends AbstractController
{
    public function __construct(private ErrorLogger $errorLogger) {}

    /**
     * POST /api/error-log
     */
    #[Route('/api/error-log', name: 'api_error_log_report', methods: ['POST'])]
    public function report(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || empty($data['message']) || !is_string($data['message'])) {
            return $this->json(['error' => 'Message is required'], 400);
        }

        $context = is_array($data['context'] ?? null) ? $data['context'] : [];
        $context['userAgent'] = mb_substr((string) $request->headers->get('User-Agent'), 0, 300);

        $this->errorLogger->log([
            'source' => ErrorLog::SOURCE_FRONT,
            'level' => in_array($data['level'] ?? null, ErrorLog::LEVELS, true) ? $data['level'] : 'error',
            'message' => $data['message'],
            'exceptionClass' => is_string($data['name'] ?? null) ? $data['name'] : null,
            'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
            'file' => is_string($data['file'] ?? null) ? $data['file'] : null,
            'trace' => is_string($data['stack'] ?? null) ? $data['stack'] : null,
            'context' => $context,
        ]);

        return $this->json(['success' => true], 201);
    }
}
