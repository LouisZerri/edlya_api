<?php

namespace App\Controller;

use App\Controller\Trait\AuthorizationTrait;
use App\Entity\EtatDesLieux;
use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailController extends AbstractController
{
    use AuthorizationTrait;
    public function __construct(
        private EntityManagerInterface $em,
        private EmailService $emailService,
    ) {
    }

    #[Route('/api/edl/{id}/email/comparatif', name: 'api_edl_email_comparatif', methods: ['POST'])]
    public function sendComparatif(int $id, Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edl = $this->em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edl, $user)) return $denied;

        $body = json_decode($request->getContent(), true) ?? [];
        $email = $body['email'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }

        $this->emailService->sendComparatifEmail($edl, $email);

        return new JsonResponse(['message' => 'Email envoyé avec succès']);
    }

    #[Route('/api/edl/{id}/email/estimations', name: 'api_edl_email_estimations', methods: ['POST'])]
    public function sendEstimations(int $id, Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edl = $this->em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edl, $user)) return $denied;

        if ($edl->getType() !== 'sortie') {
            return new JsonResponse([
                'error' => 'Les estimations ne concernent que les états des lieux de sortie'
            ], Response::HTTP_BAD_REQUEST);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $email = $body['email'] ?? '';
        $lignes = $body['lignes'] ?? [];

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($lignes)) {
            return new JsonResponse(['error' => 'Aucune ligne de devis fournie'], Response::HTTP_BAD_REQUEST);
        }

        $this->emailService->sendEstimationsEmail($edl, $email, $lignes);

        return new JsonResponse(['message' => 'Email envoyé avec succès']);
    }
}
