<?php

namespace App\Controller;

use App\Entity\EtatDesLieux;
use App\Entity\Partage;
use App\Entity\User;
use App\Repository\PartageRepository;
use App\Service\EmailService;
use App\Service\PdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PartageController extends AbstractController
{
    public function __construct(
        private EmailService $emailService
    ) {
    }
    // ==========================================
    // ENDPOINTS AUTHENTIFIÉS
    // ==========================================

    #[Route('/api/edl/{id}/partages', name: 'api_edl_partages_list', methods: ['GET'])]
    public function list(int $id, EntityManagerInterface $em): JsonResponse
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

        $partages = [];
        foreach ($edl->getPartages() as $partage) {
            $partages[] = [
                'id' => $partage->getId(),
                'token' => $partage->getToken(),
                'email' => $partage->getEmail(),
                'type' => $partage->getType(),
                'expireAt' => $partage->getExpireAt()->format('Y-m-d H:i:s'),
                'consulteAt' => $partage->getConsulteAt()?->format('Y-m-d H:i:s'),
                'isExpired' => $partage->isExpired(),
                'createdAt' => $partage->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($partages);
    }

    #[Route('/api/edl/{id}/partages', name: 'api_edl_partages_create', methods: ['POST'])]
    public function create(int $id, Request $request, EntityManagerInterface $em): JsonResponse
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

        $data = json_decode($request->getContent(), true);

        $type = $data['type'] ?? 'lien';
        $email = $data['email'] ?? null;
        $expireDays = $data['expireDays'] ?? 7;

        if ($type === 'email' && empty($email)) {
            return new JsonResponse([
                'error' => 'L\'email est requis pour un partage par email'
            ], Response::HTTP_BAD_REQUEST);
        }

        $partage = new Partage();
        $partage->setEtatDesLieux($edl);
        $partage->setType($type);
        $partage->setEmail($email);
        $partage->setExpireAt(new \DateTime("+{$expireDays} days"));

        $em->persist($partage);
        $em->flush();

        // Envoyer l'email si type === 'email'
        if ($type === 'email' && !empty($email)) {
            try {
                $this->emailService->sendPartageEmail($partage);
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas
            }
        }

        return new JsonResponse([
            'id' => $partage->getId(),
            'token' => $partage->getToken(),
            'email' => $partage->getEmail(),
            'type' => $partage->getType(),
            'expireAt' => $partage->getExpireAt()->format('Y-m-d H:i:s'),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/partages/{id}', name: 'api_partages_delete', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $partage = $em->getRepository(Partage::class)->find($id);

        if (!$partage) {
            return new JsonResponse(['error' => 'Partage non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($partage->getEtatDesLieux()->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($partage);
        $em->flush();

        return new JsonResponse(['message' => 'Partage supprimé']);
    }

    // ==========================================
    // ENDPOINTS PUBLICS (sans authentification)
    // ==========================================

    #[Route('/api/partage/{token}', name: 'api_partage_public', methods: ['GET'])]
    public function publicAccess(string $token, PartageRepository $partageRepo, EntityManagerInterface $em): JsonResponse
    {
        $partage = $partageRepo->findValidByToken($token);

        if (!$partage) {
            return new JsonResponse([
                'error' => 'Lien de partage invalide ou expiré'
            ], Response::HTTP_NOT_FOUND);
        }

        // Marquer comme consulté
        if ($partage->getConsulteAt() === null) {
            $partage->setConsulteAt(new \DateTime());
            $em->flush();
        }

        $edl = $partage->getEtatDesLieux();
        $logement = $edl->getLogement();

        // Construire les données de l'EDL
        $pieces = [];
        foreach ($edl->getPieces() as $piece) {
            $elements = [];
            foreach ($piece->getElements() as $element) {
                $photos = [];
                foreach ($element->getPhotos() as $photo) {
                    $photos[] = [
                        'id' => $photo->getId(),
                        'chemin' => $photo->getChemin(),
                        'legende' => $photo->getLegende(),
                    ];
                }

                $elements[] = [
                    'id' => $element->getId(),
                    'nom' => $element->getNom(),
                    'type' => $element->getType(),
                    'etat' => $element->getEtat(),
                    'observations' => $element->getObservations(),
                    'degradations' => $element->getDegradations(),
                    'photos' => $photos,
                ];
            }

            $pieces[] = [
                'id' => $piece->getId(),
                'nom' => $piece->getNom(),
                'ordre' => $piece->getOrdre(),
                'observations' => $piece->getObservations(),
                'elements' => $elements,
            ];
        }

        $compteurs = [];
        foreach ($edl->getCompteurs() as $compteur) {
            $compteurs[] = [
                'id' => $compteur->getId(),
                'type' => $compteur->getType(),
                'numero' => $compteur->getNumero(),
                'index' => $compteur->getIndexValue(),
                'commentaire' => $compteur->getCommentaire(),
            ];
        }

        $cles = [];
        foreach ($edl->getCles() as $cle) {
            $cles[] = [
                'id' => $cle->getId(),
                'type' => $cle->getType(),
                'nombre' => $cle->getNombre(),
                'commentaire' => $cle->getCommentaire(),
            ];
        }

        return new JsonResponse([
            'edl' => [
                'id' => $edl->getId(),
                'type' => $edl->getType(),
                'dateRealisation' => $edl->getDateRealisation()->format('Y-m-d'),
                'locataireNom' => $edl->getLocataireNom(),
                'observationsGenerales' => $edl->getObservationsGenerales(),
                'statut' => $edl->getStatut(),
                'signatureBailleur' => $edl->getSignatureBailleur() !== null,
                'signatureLocataire' => $edl->getSignatureLocataire() !== null,
                'dateSignatureBailleur' => $edl->getDateSignatureBailleur()?->format('Y-m-d H:i:s'),
                'dateSignatureLocataire' => $edl->getDateSignatureLocataire()?->format('Y-m-d H:i:s'),
            ],
            'logement' => [
                'nom' => $logement->getNom(),
                'adresse' => $logement->getAdresse(),
                'codePostal' => $logement->getCodePostal(),
                'ville' => $logement->getVille(),
                'type' => $logement->getType(),
                'surface' => $logement->getSurface(),
            ],
            'bailleur' => [
                'nom' => $edl->getUser()->getName(),
                'entreprise' => $edl->getUser()->getEntreprise(),
            ],
            'pieces' => $pieces,
            'compteurs' => $compteurs,
            'cles' => $cles,
        ]);
    }

    #[Route('/api/partage/{token}/pdf', name: 'api_partage_pdf', methods: ['GET'])]
    public function publicPdf(
        string $token,
        PartageRepository $partageRepo,
        EntityManagerInterface $em,
        PdfGenerator $pdfGenerator
    ): Response {
        $partage = $partageRepo->findValidByToken($token);

        if (!$partage) {
            return new JsonResponse([
                'error' => 'Lien de partage invalide ou expiré'
            ], Response::HTTP_NOT_FOUND);
        }

        // Marquer comme consulté
        if ($partage->getConsulteAt() === null) {
            $partage->setConsulteAt(new \DateTime());
            $em->flush();
        }

        $edl = $partage->getEtatDesLieux();
        $pdfContent = $pdfGenerator->generateEtatDesLieux($edl);

        $filename = sprintf(
            'edl-%s-%s-%s.pdf',
            $edl->getType(),
            $edl->getId(),
            $edl->getDateRealisation()->format('Y-m-d')
        );

        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
