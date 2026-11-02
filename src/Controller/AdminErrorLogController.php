<?php

namespace App\Controller;

use App\Entity\ErrorLog;
use App\Repository\ErrorLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Réservé à ROLE_ADMIN, contrairement aux autres contrôleurs admin :
 * les traces et les emails des utilisateurs n'ont pas à sortir.
 */
#[Route('/api/admin/error-log')]
#[IsGranted('ROLE_ADMIN')]
class AdminErrorLogController extends AbstractController
{
    private const PAGE_SIZE = 50;

    public function __construct(
        private ErrorLogRepository $errorLogRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * GET /api/admin/error-log?source=&level=&resolved=0|1&q=&page=
     * Liste paginée, avec les compteurs des erreurs non résolues
     */
    #[Route('', name: 'admin_error_log_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $resolved = $request->query->get('resolved');
        $page = max(1, $request->query->getInt('page', 1));

        $result = $this->errorLogRepository->search([
            'source' => $request->query->get('source'),
            'level' => $request->query->get('level'),
            'resolved' => $resolved === null || $resolved === '' ? null : $resolved === '1',
            'q' => trim((string) $request->query->get('q')),
        ], $page, self::PAGE_SIZE);

        return $this->json([
            'items' => array_map(fn(ErrorLog $log) => $this->serialize($log), $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'pageSize' => self::PAGE_SIZE,
            'unresolved' => $this->errorLogRepository->countUnresolvedByLevel(),
        ]);
    }

    /**
     * PUT /api/admin/error-log/{id}
     * Marque une erreur résolue (ou la rouvre)
     */
    #[Route('/{id}', name: 'admin_error_log_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $log = $this->errorLogRepository->find($id);

        if (!$log) {
            return $this->json(['error' => 'Error log not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['resolved'])) {
            $log->setResolved((bool) $data['resolved']);
        }

        $this->entityManager->flush();

        return $this->json($this->serialize($log));
    }

    /**
     * DELETE /api/admin/error-log/resolved
     * Purge toutes les erreurs résolues
     */
    #[Route('/resolved', name: 'admin_error_log_purge', methods: ['DELETE'])]
    public function purgeResolved(): JsonResponse
    {
        return $this->json(['deleted' => $this->errorLogRepository->deleteResolved()]);
    }

    /**
     * DELETE /api/admin/error-log/{id}
     */
    #[Route('/{id}', name: 'admin_error_log_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $log = $this->errorLogRepository->find($id);

        if (!$log) {
            return $this->json(['error' => 'Error log not found'], 404);
        }

        $this->entityManager->remove($log);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function serialize(ErrorLog $log): array
    {
        return [
            'id' => $log->getId(),
            'source' => $log->getSource(),
            'level' => $log->getLevel(),
            'message' => $log->getMessage(),
            'exceptionClass' => $log->getExceptionClass(),
            'statusCode' => $log->getStatusCode(),
            'method' => $log->getMethod(),
            'url' => $log->getUrl(),
            'file' => $log->getFile(),
            'trace' => $log->getTrace(),
            'context' => $log->getContext(),
            'userEmail' => $log->getUserEmail(),
            'occurrences' => $log->getOccurrences(),
            'resolved' => $log->isResolved(),
            'createdAt' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'lastSeenAt' => $log->getLastSeenAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
