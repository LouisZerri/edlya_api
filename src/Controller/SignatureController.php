<?php

namespace App\Controller;

use App\Entity\EtatDesLieux;
use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SignatureController extends AbstractController
{
    public function __construct(
        private EmailService $emailService
    ) {
    }
    // ==========================================
    // ENDPOINTS AUTHENTIFIÉS (Bailleur/Agent)
    // ==========================================

    #[Route('/api/edl/{id}/signature', name: 'api_edl_signature_status', methods: ['GET'])]
    public function status(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $edl = $em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

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
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $edl = $em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        if ($edl->getSignatureBailleur() !== null) {
            return new JsonResponse(['error' => 'Le bailleur a déjà signé'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $signature = $data['signature'] ?? null;

        if (empty($signature)) {
            return new JsonResponse(['error' => 'La signature est requise'], Response::HTTP_BAD_REQUEST);
        }

        // La signature est un SVG encodé en base64
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
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $edl = $em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

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

        $edl->setSignatureLocataire($signature);
        $edl->setDateSignatureLocataire(new \DateTimeImmutable());
        $edl->setSignatureIp($request->getClientIp());
        $edl->setSignatureUserAgent($request->headers->get('User-Agent'));
        $edl->setStatut('signe');

        $em->flush();

        // Envoyer l'email de confirmation aux deux parties
        try {
            $this->emailService->sendSignatureConfirmation($edl);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas
        }

        return new JsonResponse([
            'message' => 'Signature locataire enregistrée',
            'dateSignatureLocataire' => $edl->getDateSignatureLocataire()->format('Y-m-d H:i:s'),
            'statut' => $edl->getStatut(),
        ]);
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
