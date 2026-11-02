<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectTodo;
use App\Repository\PersonnageRepository;
use App\Repository\ProjectTodoRepository;
use App\Repository\SceneRepository;
use App\Repository\SequenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Todo d'un projet : tâches saisies par l'auteur et tâches ajoutées par les
 * analyses (via /todos/batch, qui ignore ce qui est déjà connu).
 */
#[Route('/api')]
class ProjectTodoController extends AbstractController
{
    public function __construct(
        private ProjectTodoRepository $todoRepository,
        private SequenceRepository $sequenceRepository,
        private SceneRepository $sceneRepository,
        private PersonnageRepository $personnageRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/project/{id}/todos', name: 'project_todo_list', methods: ['GET'])]
    public function list(int $id): JsonResponse
    {
        $project = $this->ownedProject($id);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], 404);
        }

        return $this->json(array_map(fn (ProjectTodo $t) => $t->toArray(), $this->todoRepository->findVisible($project)));
    }

    #[Route('/todo/update', name: 'project_todo_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!empty($data['id'])) {
            $todo = $this->todoRepository->find($data['id']);
            if (!$todo || !$this->ownedProject($todo->getProject()->getId())) {
                return $this->json(['error' => 'Todo not found'], 404);
            }
        } else {
            $project = $this->ownedProject((int) ($data['projectId'] ?? 0));
            if (!$project) {
                return $this->json(['error' => 'Project not found'], 404);
            }
            if (!trim($data['label'] ?? '')) {
                return $this->json(['error' => 'Missing label'], 400);
            }
            $todo = new ProjectTodo($project);
            $this->entityManager->persist($todo);
        }

        $this->hydrate($todo, $data);
        $this->entityManager->flush();

        return $this->json($todo->toArray());
    }

    /**
     * Ajout groupé par une analyse. Une tâche dont l'empreinte est déjà connue
     * (ouverte, cochée ou écartée) est ignorée. Ne renvoie que les créées.
     */
    #[Route('/project/{id}/todos/batch', name: 'project_todo_batch', methods: ['POST'])]
    public function batch(int $id, Request $request): JsonResponse
    {
        $project = $this->ownedProject($id);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $known = array_flip($this->todoRepository->findDedupeKeys($project));
        $created = [];

        foreach ($data['todos'] ?? [] as $item) {
            if (!trim($item['label'] ?? '') || empty($item['dedupeKey'])) {
                continue;
            }

            $todo = new ProjectTodo($project);
            $todo->setSource(ProjectTodo::SOURCE_IA)->setDedupeKey($item['dedupeKey']);
            if (isset($known[$todo->getDedupeKey()])) {
                continue;
            }
            $known[$todo->getDedupeKey()] = true;

            $this->hydrate($todo, $item);
            $this->entityManager->persist($todo);
            $created[] = $todo;
        }

        $this->entityManager->flush();

        return $this->json(array_map(fn (ProjectTodo $t) => $t->toArray(), $created), $created ? 201 : 200);
    }

    /** Une tâche d'analyse est écartée, pas effacée : sinon elle reviendrait */
    #[Route('/todo/delete/{id}', name: 'project_todo_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $todo = $this->todoRepository->find($id);
        if (!$todo || !$this->ownedProject($todo->getProject()->getId())) {
            return $this->json(['error' => 'Todo not found'], 404);
        }

        if ($todo->getDedupeKey()) {
            $todo->setDismissed(true);
        } else {
            $this->entityManager->remove($todo);
        }
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function hydrate(ProjectTodo $todo, array $data): void
    {
        $project = $todo->getProject();

        if (array_key_exists('label', $data) && trim($data['label'])) {
            $todo->setLabel(trim($data['label']));
        }
        if (array_key_exists('category', $data)) {
            $todo->setCategory((string) $data['category']);
        }
        if (array_key_exists('origin', $data)) {
            $todo->setOrigin($data['origin'] ? mb_substr((string) $data['origin'], 0, 50) : null);
        }
        if (array_key_exists('done', $data)) {
            $todo->setDone((bool) $data['done']);
        }

        // Les rattachements doivent appartenir au même projet
        if (array_key_exists('sequenceId', $data)) {
            $sequence = $data['sequenceId'] ? $this->sequenceRepository->find($data['sequenceId']) : null;
            $todo->setSequence($sequence && $sequence->getPart()->getProject() === $project ? $sequence : null);
        }
        if (array_key_exists('sceneId', $data)) {
            $scene = $data['sceneId'] ? $this->sceneRepository->find($data['sceneId']) : null;
            $todo->setScene($scene && $scene->getSequence()->getPart()->getProject() === $project ? $scene : null);
        }
        if (array_key_exists('personnageId', $data)) {
            $personnage = $data['personnageId'] ? $this->personnageRepository->find($data['personnageId']) : null;
            $todo->setPersonnage($personnage && $personnage->getProject() === $project ? $personnage : null);
        }
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
