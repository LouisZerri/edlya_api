<?php

namespace App\Controller;

use App\Controller\Trait\AuthorizationTrait;
use App\Entity\EtatDesLieux;
use App\Entity\User;
use App\Service\TypologieService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TypologieController extends AbstractController
{
    use AuthorizationTrait;
    public function __construct(
        private TypologieService $typologieService
    ) {
    }

    #[Route('/api/typologies', name: 'api_typologies', methods: ['GET'])]
    public function typologies(): JsonResponse
    {
        return new JsonResponse($this->typologieService->getTypologies());
    }

    #[Route('/api/degradations', name: 'api_degradations', methods: ['GET'])]
    public function degradations(): JsonResponse
    {
        return new JsonResponse($this->typologieService->getDegradationsParType());
    }

    #[Route('/api/edl/{id}/generer-pieces', name: 'api_edl_generer_pieces', methods: ['POST'])]
    public function genererPieces(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edl = $em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edl, $user)) return $denied;

        // Vérifier qu'il n'y a pas déjà des pièces
        if ($edl->getPieces()->count() > 0) {
            return new JsonResponse([
                'error' => 'L\'état des lieux contient déjà des pièces. Supprimez-les avant de regénérer.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $typologie = $data['typologie'] ?? null;

        if (empty($typologie)) {
            return new JsonResponse(['error' => 'La typologie est requise'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $pieces = $this->typologieService->genererPieces($edl, $typologie);

            return new JsonResponse([
                'message' => sprintf('%d pièces générées', count($pieces)),
                'pieces' => array_map(fn($p) => [
                    'id' => $p->getId(),
                    'nom' => $p->getNom(),
                    'ordre' => $p->getOrdre(),
                ], $pieces),
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => 'Typologie invalide. Veuillez réessayer.'], Response::HTTP_BAD_REQUEST);
        }
    }
}
