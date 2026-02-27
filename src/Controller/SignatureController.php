<?php

namespace App\Controller;

use App\Controller\Trait\AuthorizationTrait;
use App\Entity\EtatDesLieux;
use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SignatureController extends AbstractController
{
    use AuthorizationTrait;
    public function __construct(
        private EmailService $emailService,
        private LoggerInterface $logger
    ) {
    }
    // ==========================================
    // ENDPOINTS AUTHENTIFIÉS (Bailleur/Agent)
    // ==========================================

    #[Route('/api/edl/{id}/signature', name: 'api_edl_signature_status', methods: ['GET'])]
    public function status(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edl = $em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edl, $user)) return $denied;

        return new JsonResponse([
            'edlId' => $edl->getId(),
            'statut' => $edl->getStatut(),
            'signatureBailleur' => $edl->getSignatureBailleur() !== null,
            'dateSignatureBailleur' => $edl->getDateSignatureBailleur()?->format('Y-m-d H:i:s'),
            'signatureLocataire' => $edl->getSignatureLocataire() !== null,
            'dateSignatureLocataire' => $edl->getDateSignatureLocataire()?->format('Y-m-d H:i:s'),
            'etape' => $this->getEtapeSignature($edl),
        ]);
    }

    #[Route('/api/edl/{id}/signature/bailleur', name: 'api_edl_signature_bailleur', methods: ['POST'])]
    public function signerBailleur(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edl = $em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edl, $user)) return $denied;

        if ($edl->getSignatureBailleur() !== null) {
            return new JsonResponse(['error' => 'Le bailleur a déjà signé'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $signature = $data['signature'] ?? null;

        if (empty($signature)) {
            return new JsonResponse(['error' => 'La signature est requise'], Response::HTTP_BAD_REQUEST);
        }

        // Limiter la taille (500 Ko max pour un SVG base64)
        if (strlen($signature) > 500000) {
            return new JsonResponse(['error' => 'La signature est trop volumineuse'], Response::HTTP_BAD_REQUEST);
        }

        $edl->setSignatureBailleur($signature);
        $edl->setDateSignatureBailleur(new \DateTimeImmutable());
        $edl->setStatut('termine');

        $em->flush();

        return new JsonResponse([
            'message' => 'Signature bailleur enregistrée',
            'dateSignatureBailleur' => $edl->getDateSignatureBailleur()->format('Y-m-d H:i:s'),
            'statut' => $edl->getStatut(),
        ]);
    }

    #[Route('/api/edl/{id}/signature/locataire', name: 'api_edl_signature_locataire', methods: ['POST'])]
    public function signerLocataireDirecte(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edl = $em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edl, $user)) return $denied;

        if ($edl->getSignatureBailleur() === null) {
            return new JsonResponse([
                'error' => 'Le bailleur doit signer avant le locataire'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($edl->getSignatureLocataire() !== null) {
            return new JsonResponse(['error' => 'Le locataire a déjà signé'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $signature = $data['signature'] ?? null;

        if (empty($signature)) {
            return new JsonResponse(['error' => 'La signature est requise'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($signature) > 500000) {
            return new JsonResponse(['error' => 'La signature est trop volumineuse'], Response::HTTP_BAD_REQUEST);
        }

        $edl->setSignatureLocataire($signature);
        $edl->setDateSignatureLocataire(new \DateTimeImmutable());
        $edl->setSignatureIp($request->getClientIp());
        $edl->setSignatureUserAgent($request->headers->get('User-Agent'));
        $edl->setStatut('signe');

        $em->flush();

        // Envoyer l'email de confirmation
        $emailSent = false;
        try {
            $this->emailService->sendSignatureConfirmation($edl);
            $emailSent = true;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur envoi email signature EDL #' . $edl->getId() . ': ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        $response = [
            'message' => 'Signature locataire enregistrée',
            'dateSignatureLocataire' => $edl->getDateSignatureLocataire()->format('Y-m-d H:i:s'),
            'statut' => $edl->getStatut(),
            'emailSent' => $emailSent,
        ];

        if (empty($edl->getLocataireEmail())) {
            $response['warning'] = 'Le locataire n\'a pas d\'adresse email. L\'email de confirmation n\'a pas pu lui être envoyé.';
        }

        return new JsonResponse($response);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function getEtapeSignature(EtatDesLieux $edl): int
    {
        if ($edl->getSignatureLocataire() !== null) {
            return 3; // Tout est signé
        }
        if ($edl->getSignatureBailleur() !== null) {
            return 2; // Bailleur a signé
        }
        return 1; // Pas encore de signature
    }
}
