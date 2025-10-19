<?php

namespace App\Controller;

use App\Entity\Genre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/genre')]
class GenreController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * GET /api/genre/all
     * Retourne tous les genres avec leurs subgenres
     */
    #[Route('/all', name: 'genre_all', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $genres = $this->entityManager->getRepository(Genre::class)->findAll();

        $data = array_map(function(Genre $genre) {
            return [
                'id' => $genre->getId(),
                'name' => $genre->getName(),
                'description' => $genre->getDescription(),
                'subgenres' => array_map(function($subgenre) {
                    return [
                        'id' => $subgenre->getId(),
                        'name' => $subgenre->getName(),
                        'description' => $subgenre->getDescription(),
                    ];
                }, $genre->getSubgenres()->toArray())
            ];
        }, $genres);

        return $this->json($data);
    }
}
