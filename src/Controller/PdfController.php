<?php

namespace App\Controller;

use App\Controller\Trait\AuthorizationTrait;
use App\Entity\User;
use App\Repository\EtatDesLieuxRepository;
use App\Service\ComparatifService;
use App\Service\PdfGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PdfController extends AbstractController
{
    use AuthorizationTrait;
    public function __construct(
        private EtatDesLieuxRepository $edlRepo,
        private ComparatifService $comparatifService,
    ) {
    }
    #[Route('/api/edl/{id}/pdf', name: 'api_edl_pdf', methods: ['GET'])]
    public function generatePdf(
        int $id,
        EtatDesLieuxRepository $edlRepo,
        PdfGenerator $pdfGenerator
    ): Response {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edl = $edlRepo->findWithFullRelations($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edl, $user)) return $denied;

        $pdfContent = $pdfGenerator->generateEtatDesLieux($edl);

        $filename = sprintf(
            'edl-%s-%s-%s.pdf',
            $edl->getType(),
            $edl->getId(),
            $edl->getDateRealisation()->format('Y-m-d')
        );

        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/api/edl/{id}/pdf/preview', name: 'api_edl_pdf_preview', methods: ['GET'])]
    public function previewPdf(
        int $id,
        EtatDesLieuxRepository $edlRepo,
        PdfGenerator $pdfGenerator
    ): Response {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edl = $edlRepo->findWithFullRelations($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edl, $user)) return $denied;

        $pdfContent = $pdfGenerator->generateEtatDesLieux($edl);

        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }

    #[Route('/api/edl/{id}/comparatif/pdf', name: 'api_edl_comparatif_pdf', methods: ['GET'])]
    public function comparatifPdf(
        int $id,
        PdfGenerator $pdfGenerator
    ): Response {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edl = $this->edlRepo->findWithFullRelations($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edl, $user)) return $denied;

        $logement = $edl->getLogement();

        // Déterminer si c'est un EDL d'entrée ou de sortie et trouver l'autre
        if ($edl->getType() === 'sortie') {
            $edlSortie = $edl;
            $edlEntree = $this->edlRepo->findLastByLogementAndType($logement, 'entree');
        } else {
            $edlEntree = $edl;
            $edlSortie = $this->edlRepo->findLastByLogementAndType($logement, 'sortie');
        }

        $comparatif = $this->comparatifService->buildComparatif($edlEntree, $edlSortie);

        // Calculate duration in months if both EDLs exist
        $dureeMois = null;
        if ($edlEntree && $edlSortie) {
            $duree = $edlEntree->getDateRealisation()->diff($edlSortie->getDateRealisation());
            $dureeMois = $duree->m + ($duree->y * 12);
        }

        $comparatif['duree_mois'] = $dureeMois;

        $data = [
            'logement' => [
                'nom' => $logement->getNom(),
                'adresse' => $logement->getAdresse(),
                'codePostal' => $logement->getCodePostal(),
                'ville' => $logement->getVille(),
                'type' => $logement->getType(),
                'surface' => $logement->getSurface(),
            ],
            'entree' => $edlEntree,
            'sortie' => $edlSortie,
            'comparatif' => $comparatif,
        ];

        $pdfContent = $pdfGenerator->generateComparatif($data);

        $filename = sprintf(
            'comparatif-edl-%s-%s.pdf',
            $id,
            date('Y-m-d')
        );

        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/api/edl/{id}/estimations/pdf', name: 'api_edl_estimations_pdf', methods: ['POST'])]
    public function estimationsPdf(
        int $id,
        Request $request,
        PdfGenerator $pdfGenerator
    ): Response {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edlSortie = $this->edlRepo->findWithFullRelations($id);

        if (!$edlSortie) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edlSortie, $user)) return $denied;

        if ($edlSortie->getType() !== 'sortie') {
            return new JsonResponse([
                'error' => 'Les estimations ne peuvent être calculées que sur un état des lieux de sortie'
            ], Response::HTTP_BAD_REQUEST);
        }

        $logement = $edlSortie->getLogement();

        // Lire les lignes du devis depuis le body JSON
        $body = json_decode($request->getContent(), true) ?? [];
        $lignesInput = $body['lignes'] ?? [];

        $totalHT = 0;
        $lignesDevis = [];

        foreach ($lignesInput as $ligne) {
            if (!empty($ligne['description']) && isset($ligne['quantite']) && isset($ligne['prix_unitaire'])) {
                $montant = floatval($ligne['quantite']) * floatval($ligne['prix_unitaire']);
                $totalHT += $montant;

                $lignesDevis[] = [
                    'piece' => $ligne['piece'] ?? '',
                    'description' => $ligne['description'],
                    'quantite' => floatval($ligne['quantite']),
                    'unite' => $ligne['unite'] ?? 'unité',
                    'prix_unitaire' => floatval($ligne['prix_unitaire']),
                    'montant' => $montant,
                ];
            }
        }

        $tva = $totalHT * 0.20;
        $totalTTC = $totalHT + $tva;

        $data = [
            'logement' => [
                'nom' => $logement->getNom(),
                'adresse' => $logement->getAdresse(),
                'codePostal' => $logement->getCodePostal(),
                'ville' => $logement->getVille(),
            ],
            'locataire' => [
                'nom' => $edlSortie->getLocataireNom(),
                'email' => $edlSortie->getLocataireEmail(),
            ],
            'date_sortie' => $edlSortie->getDateRealisation()->format('d/m/Y'),
            'lignes' => $lignesDevis,
            'totalHT' => $totalHT,
            'tva' => $tva,
            'totalTTC' => $totalTTC,
        ];

        $pdfContent = $pdfGenerator->generateEstimations($data);

        $filename = sprintf(
            'devis-reparations-%s-%s.pdf',
            $id,
            date('Y-m-d')
        );

        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

}
