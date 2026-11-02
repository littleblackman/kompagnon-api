<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Sequence;
use App\Entity\TimelineFact;
use App\Entity\TimelineIssue;
use App\Repository\SequenceRepository;
use App\Repository\TimelineFactRepository;
use App\Repository\TimelineIssueRepository;
use App\Repository\TimelineSequenceRepository;
use App\Service\TimelineAnalyseService;
use App\Service\TimelineService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Chronologie d'un projet. Chaque écriture renvoie la chronologie complète,
 * recalculée : le front remplace son état, il ne recalcule rien.
 */
#[Route('/api')]
class TimelineController extends AbstractController
{
    public function __construct(
        private TimelineService $timelineService,
        private TimelineAnalyseService $analyseService,
        private SequenceRepository $sequenceRepository,
        private TimelineSequenceRepository $timelineSequenceRepository,
        private TimelineFactRepository $factRepository,
        private TimelineIssueRepository $issueRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/project/{id}/timeline', name: 'timeline_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $project = $this->ownedProject($id);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], 404);
        }

        return $this->json($this->timelineService->build($project));
    }

    #[Route('/project/{id}/timeline/settings', name: 'timeline_settings', methods: ['POST'])]
    public function settings(int $id, Request $request): JsonResponse
    {
        $project = $this->ownedProject($id);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], 404);
        }

        $this->timelineService->updateSettings($project, $this->body($request));

        return $this->json($this->timelineService->build($project));
    }

    // ── Analyse ──────────────────────────────────────────────────────

    /**
     * Lecture d'une séquence. Le front les enchaîne dans l'ordre du livre :
     * chacune s'appuie sur la date de la précédente.
     */
    #[Route('/sequence/{id}/timeline/extract', name: 'timeline_extract_sequence', methods: ['POST'])]
    public function extractSequence(int $id): JsonResponse
    {
        $sequence = $this->ownedSequence($id);
        if (!$sequence) {
            return $this->json(['success' => false, 'message' => 'Séquence non trouvée'], 404);
        }

        return $this->analyse(
            fn () => $this->analyseService->extractSequence($sequence),
            $sequence->getPart()->getProject()
        );
    }

    #[Route('/project/{id}/timeline/extract-fiches', name: 'timeline_extract_fiches', methods: ['POST'])]
    public function extractFiches(int $id): JsonResponse
    {
        $project = $this->ownedProject($id);
        if (!$project) {
            return $this->json(['success' => false, 'message' => 'Project not found'], 404);
        }

        return $this->analyse(fn () => $this->analyseService->extractFiches($project), $project);
    }

    #[Route('/project/{id}/timeline/coherence', name: 'timeline_coherence', methods: ['POST'])]
    public function coherence(int $id): JsonResponse
    {
        $project = $this->ownedProject($id);
        if (!$project) {
            return $this->json(['success' => false, 'message' => 'Project not found'], 404);
        }

        return $this->analyse(fn () => $this->analyseService->checkCoherence($project), $project);
    }

    // ── Validation et saisie de l'auteur ─────────────────────────────

    /** Tout ce qui est encore proposé passe validé ; ?sequenceId= pour une seule séquence */
    #[Route('/project/{id}/timeline/validate', name: 'timeline_validate', methods: ['POST'])]
    public function validate(int $id, Request $request): JsonResponse
    {
        $project = $this->ownedProject($id);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], 404);
        }

        $data = $this->body($request);
        $sequence = !empty($data['sequenceId']) ? $this->ownedSequence((int) $data['sequenceId']) : null;
        $this->timelineService->validateProposed($project, $sequence);

        return $this->json($this->timelineService->build($project));
    }

    #[Route('/timeline/fact/update', name: 'timeline_fact_update', methods: ['POST'])]
    public function updateFact(Request $request): JsonResponse
    {
        $data = $this->body($request);

        if (!empty($data['id'])) {
            $fact = $this->factRepository->find($data['id']);
            if (!$fact || !$this->ownedProject($fact->getProject()->getId())) {
                return $this->json(['error' => 'Fact not found'], 404);
            }
        } else {
            $project = $this->ownedProject((int) ($data['projectId'] ?? 0));
            if (!$project) {
                return $this->json(['error' => 'Project not found'], 404);
            }
            if (!trim((string) ($data['label'] ?? ''))) {
                return $this->json(['error' => 'Missing label'], 400);
            }
            // Saisi par l'auteur : validé d'office
            $fact = (new TimelineFact($project))
                ->setOrigin(TimelineFact::ORIGIN_MANUAL)
                ->setStatus(TimelineFact::STATUS_VALIDATED)
                ->setLabel((string) $data['label']);
            $this->entityManager->persist($fact);
        }

        $sequence = null;
        if (!empty($data['sequenceId'])) {
            $sequence = $this->ownedSequence((int) $data['sequenceId']);
            if ($sequence && $sequence->getPart()->getProject() !== $fact->getProject()) {
                $sequence = null;
            }
        }

        $this->timelineService->hydrateFact($fact, $data, $sequence);
        $this->entityManager->flush();

        return $this->json($this->timelineService->build($fact->getProject()));
    }

    #[Route('/timeline/fact/delete/{id}', name: 'timeline_fact_delete', methods: ['DELETE'])]
    public function deleteFact(int $id): JsonResponse
    {
        $fact = $this->factRepository->find($id);
        if (!$fact || !$this->ownedProject($fact->getProject()->getId())) {
            return $this->json(['error' => 'Fact not found'], 404);
        }

        $project = $fact->getProject();
        // Un fait de l'analyse est rejeté, pas effacé : sinon elle le reproposerait
        if ($fact->getOrigin() === TimelineFact::ORIGIN_IA) {
            $fact->setStatus(TimelineFact::STATUS_REJECTED);
        } else {
            $this->entityManager->remove($fact);
        }
        $this->entityManager->flush();

        return $this->json($this->timelineService->build($project));
    }

    #[Route('/timeline/sequence/update', name: 'timeline_sequence_update', methods: ['POST'])]
    public function updateSequence(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $ts = $this->timelineSequenceRepository->find((int) ($data['id'] ?? 0));
        if (!$ts || !$this->ownedProject($ts->getProject()->getId())) {
            return $this->json(['error' => 'Timeline sequence not found'], 404);
        }

        $this->timelineService->hydrateTimelineSequence($ts, $data);
        $this->entityManager->flush();

        return $this->json($this->timelineService->build($ts->getProject()));
    }

    #[Route('/timeline/issue/update', name: 'timeline_issue_update', methods: ['POST'])]
    public function updateIssue(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $issue = $this->issueRepository->find((int) ($data['id'] ?? 0));
        if (!$issue || !$this->ownedProject($issue->getProject()->getId())) {
            return $this->json(['error' => 'Issue not found'], 404);
        }

        $issue->setStatus((string) ($data['status'] ?? TimelineIssue::STATUS_OPEN));
        $this->entityManager->flush();

        return $this->json($this->timelineService->build($issue->getProject()));
    }

    // ── Outils ───────────────────────────────────────────────────────

    /** 422 : la requête est valide, c'est le contenu qui n'est pas analysable */
    private function analyse(callable $run, Project $project): JsonResponse
    {
        try {
            $result = $run();
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json(['success' => true, 'timeline' => $this->timelineService->build($project)]);
    }

    private function body(Request $request): array
    {
        return json_decode($request->getContent(), true) ?? [];
    }

    private function ownedSequence(int $id): ?Sequence
    {
        $sequence = $this->sequenceRepository->find($id);

        return $sequence && $this->ownedProject($sequence->getPart()->getProject()->getId()) ? $sequence : null;
    }

    private function ownedProject(int $id): ?Project
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);
        $user = $this->getUser();

        if (!$project || !$user || $project->getUser()->getUserIdentifier() !== $user->getUserIdentifier()) {
            return null;
        }

        return $project;
    }
}
