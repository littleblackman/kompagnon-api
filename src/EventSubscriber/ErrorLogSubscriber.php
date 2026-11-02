<?php

namespace App\EventSubscriber;

use App\Service\ErrorLogger;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Journalise les erreurs serveur de l'API.
 *
 * Deux portes d'entrée :
 * - les exceptions non attrapées (kernel.exception) ;
 * - les réponses 5xx construites à la main par un contrôleur qui a attrapé
 *   l'exception lui-même (`catch` → `json([...], 500)`), sinon invisibles.
 *
 * Les 4xx (404, 401, validation…) ne sont pas des pannes : on les ignore.
 */
class ErrorLogSubscriber implements EventSubscriberInterface
{
    private const LOGGED = '_error_logged';

    public function __construct(private ErrorLogger $errorLogger) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onException', 0],
            KernelEvents::RESPONSE => ['onResponse', -100],
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->isApi($request)) {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        if ($status < 500) {
            return;
        }

        // Erreur PHP (TypeError…) ou base injoignable : plus grave qu'une exception métier
        $level = ($exception instanceof \Error || $exception instanceof DBALException) ? 'critical' : 'error';

        $this->errorLogger->logException($exception, $level, $this->requestInfo($request, $status));
        $request->attributes->set(self::LOGGED, true);
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$event->isMainRequest() || !$this->isApi($request)
            || $response->getStatusCode() < 500 || $request->attributes->get(self::LOGGED)) {
            return;
        }

        $body = json_decode((string) $response->getContent(), true);
        $message = is_array($body) ? ($body['error'] ?? $body['message'] ?? $body['detail'] ?? null) : null;

        $this->errorLogger->log($this->requestInfo($request, $response->getStatusCode()) + [
            'source' => 'api',
            'level' => 'error',
            'message' => is_string($message) ? $message : 'Réponse ' . $response->getStatusCode() . ' sans message',
            // Pas d'exception sous la main : la route tient lieu d'emplacement
            'file' => 'route ' . ($request->attributes->get('_route') ?? '?'),
        ]);
    }

    private function isApi(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/');
    }

    private function requestInfo(Request $request, int $status): array
    {
        return [
            'statusCode' => $status,
            'method' => $request->getMethod(),
            'url' => $request->getPathInfo() . ($request->getQueryString() ? '?' . $request->getQueryString() : ''),
            'context' => array_filter([
                'route' => $request->attributes->get('_route'),
                'payload' => $this->payloadSummary($request),
            ]),
        ];
    }

    /**
     * Corps de la requête, sans mot de passe, et tronqué : on veut comprendre
     * l'erreur, pas archiver les scènes des utilisateurs.
     */
    private function payloadSummary(Request $request): ?array
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return null;
        }

        array_walk_recursive($data, function (&$value, $key) {
            if (is_string($key) && preg_match('/pass|token|secret/i', $key)) {
                $value = '***';
            } elseif (is_string($value) && mb_strlen($value) > 200) {
                $value = mb_substr($value, 0, 200) . '…';
            }
        });

        return $data;
    }
}
