<?php

namespace App\Controller;

use App\Entity\PersonnageDramaticFunction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PersonnageDramaticFunctionController extends AbstractController
{
    /**
     * Retire une seule fonction du personnage. Passer par la sauvegarde de la
     * fiche (dramaticFunctionIds) recréerait toutes les autres et perdrait
     * leurs séquences.
     */
    #[Route('/api/personnage/{personnageId}/dramatic-function/{pdfId}/delete', name: 'api_personnage_dramatic_function_delete', methods: ['DELETE'])]
    public function deleteDramaticFunction(
        int $personnageId,
        int $pdfId,
        EntityManagerInterface $em
    ): JsonResponse {
        $pdf = $em->getRepository(PersonnageDramaticFunction::class)->find($pdfId);
        if (!$pdf || $pdf->getPersonnage()->getId() !== $personnageId) {
            return $this->json(['error' => 'Fonction dramatique non trouvée'], 404);
        }

        $pdf->getPersonnage()->removePersonnageDramaticFunction($pdf);
        $em->remove($pdf);
        $em->flush();

        return $this->json(['message' => 'Fonction dramatique retirée'], 200);
    }
}
