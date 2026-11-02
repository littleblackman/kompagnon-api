<?php

namespace App\Service;

use App\Entity\ErrorLog;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Écrit une erreur dans la table error_log.
 *
 * Passe par DBAL et non par l'EntityManager : une exception Doctrine ferme
 * l'EntityManager, et c'est précisément ce genre d'erreur qu'on veut garder.
 * Ne lève jamais d'exception : un journal qui plante ne doit pas masquer
 * l'erreur d'origine. En cas d'échec, on se rabat sur le log Monolog.
 */
class ErrorLogger
{
    /** Attribut posé sur la requête une fois son erreur journalisée : une seule entrée par requête */
    public const REQUEST_LOGGED = '_error_logged';

    private const MAX_MESSAGE = 5000;
    private const MAX_TRACE = 60000;

    public function __construct(
        private Connection $connection,
        private Security $security,
        private LoggerInterface $logger,
        private RequestStack $requestStack,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    /**
     * @param array{
     *   source: string, level: string, message: string,
     *   exceptionClass?: ?string, statusCode?: ?int, method?: ?string, url?: ?string,
     *   file?: ?string, trace?: ?string, context?: ?array, userEmail?: ?string
     * } $entry
     */
    public function log(array $entry): void
    {
        try {
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $message = mb_substr(trim($entry['message']) ?: '(sans message)', 0, self::MAX_MESSAGE);
            $level = in_array($entry['level'], ErrorLog::LEVELS, true) ? $entry['level'] : 'error';

            $userEmail = $entry['userEmail'] ?? $this->security->getUser()?->getUserIdentifier();

            // Journalisé depuis un service (l'IA…) : la requête en cours situe l'erreur
            $request = $this->requestStack->getMainRequest();
            $entry += array_filter([
                'method' => $request?->getMethod(),
                'url' => $request?->getPathInfo(),
            ]);

            // Même source, même type, même message, même endroit = même erreur
            $fingerprint = sha1(implode('|', [
                $entry['source'],
                $entry['exceptionClass'] ?? '',
                $message,
                $entry['file'] ?? '',
            ]));

            $fields = [
                'level' => $level,
                'status_code' => $entry['statusCode'] ?? null,
                'method' => isset($entry['method']) ? mb_substr($entry['method'], 0, 10) : null,
                'url' => isset($entry['url']) ? mb_substr($entry['url'], 0, 2048) : null,
                'trace' => isset($entry['trace']) ? mb_substr($entry['trace'], 0, self::MAX_TRACE) : null,
                'context' => isset($entry['context']) ? json_encode($entry['context'], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'user_email' => $userEmail ? mb_substr($userEmail, 0, 180) : null,
                'last_seen_at' => $now,
            ];

            // Déjà vue et pas encore résolue : on compte, on garde le contexte le plus récent
            $updated = $this->connection->executeStatement(
                'UPDATE error_log SET occurrences = occurrences + 1, level = :level, status_code = :status_code,
                    method = :method, url = COALESCE(:url, url), trace = COALESCE(:trace, trace),
                    context = :context, user_email = COALESCE(:user_email, user_email), last_seen_at = :last_seen_at
                 WHERE fingerprint = :fingerprint AND resolved = 0',
                $fields + ['fingerprint' => $fingerprint]
            );

            if ($updated === 0) {
                $this->connection->insert('error_log', $fields + [
                    'source' => $entry['source'],
                    'message' => $message,
                    'exception_class' => isset($entry['exceptionClass']) ? mb_substr($entry['exceptionClass'], 0, 255) : null,
                    'file' => isset($entry['file']) ? mb_substr($entry['file'], 0, 255) : null,
                    'fingerprint' => $fingerprint,
                    'occurrences' => 1,
                    'resolved' => 0,
                    'created_at' => $now,
                ]);
            }

            // Un avertissement n'empêche pas de journaliser l'erreur qui suivrait
            if ($level !== 'warning') {
                $request?->attributes->set(self::REQUEST_LOGGED, true);
            }
        } catch (\Throwable $e) {
            $this->logger->error('ErrorLogger : impossible de journaliser en base', [
                'original' => $entry['message'] ?? null,
                'exception' => $e,
            ]);
        }
    }

    public function logException(\Throwable $exception, string $level, array $extra = []): void
    {
        $this->log($extra + [
            'source' => ErrorLog::SOURCE_API,
            'level' => $level,
            'message' => $exception->getMessage(),
            'exceptionClass' => $exception::class,
            'file' => $this->relativePath($exception->getFile()) . ':' . $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    private function relativePath(string $path): string
    {
        $root = rtrim($this->projectDir, '/') . '/';
        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
