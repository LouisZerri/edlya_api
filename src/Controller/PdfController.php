<?php

namespace App\Controller;

use App\Entity\EtatDesLieux;
use App\Entity\User;
use App\Service\PdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PdfController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }
    #[Route('/api/edl/{id}/pdf', name: 'api_edl_pdf', methods: ['GET'])]
    public function generatePdf(
        int $id,
        EntityManagerInterface $em,
        PdfGenerator $pdfGenerator
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $edl = $em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier l'accès
        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

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
        EntityManagerInterface $em,
        PdfGenerator $pdfGenerator
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $edl = $em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier l'accès
        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

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
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $edl = $this->em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        $logement = $edl->getLogement();

        // Déterminer si c'est un EDL d'entrée ou de sortie et trouver l'autre
        if ($edl->getType() === 'sortie') {
            $edlSortie = $edl;
            $edlEntree = $this->em->getRepository(EtatDesLieux::class)->findOneBy(
                ['logement' => $logement, 'type' => 'entree'],
                ['dateRealisation' => 'DESC']
            );
        } else {
            $edlEntree = $edl;
            $edlSortie = $this->em->getRepository(EtatDesLieux::class)->findOneBy(
                ['logement' => $logement, 'type' => 'sortie'],
                ['dateRealisation' => 'DESC']
            );
        }

        $comparatif = $this->buildComparatif($edlEntree, $edlSortie);

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
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $edlSortie = $this->em->getRepository(EtatDesLieux::class)->find($id);

        if (!$edlSortie) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($edlSortie->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

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

    private function buildComparatif(?EtatDesLieux $entree, ?EtatDesLieux $sortie): array
    {
        $comparatif = [
            'pieces' => [],
            'compteurs' => [],
            'cles' => [],
            'degradations' => [],
            'statistiques' => [
                'totalElements' => 0,
                'elementsAmeliores' => 0,
                'elementsDegrades' => 0,
                'elementsIdentiques' => 0,
            ],
        ];

        $etatScore = [
            'neuf' => 6,
            'tres_bon' => 5,
            'bon' => 4,
            'usage' => 3,
            'mauvais' => 2,
            'hors_service' => 1,
        ];

        $elementsEntree = [];
        if ($entree) {
            foreach ($entree->getPieces() as $piece) {
                foreach ($piece->getElements() as $element) {
                    $key = $piece->getNom() . '|' . $element->getNom() . '|' . $element->getType();
                    $elementsEntree[$key] = [
                        'piece' => $piece->getNom(),
                        'nom' => $element->getNom(),
                        'type' => $element->getType(),
                        'etat' => $element->getEtat(),
                        'observations' => $element->getObservations(),
                    ];
                }
            }
        }

        $processedKeys = [];
        if ($sortie) {
            foreach ($sortie->getPieces() as $piece) {
                $pieceName = $piece->getNom();

                if (!isset($comparatif['pieces'][$pieceName])) {
                    $comparatif['pieces'][$pieceName] = [];
                }

                foreach ($piece->getElements() as $element) {
                    $key = $pieceName . '|' . $element->getNom() . '|' . $element->getType();
                    $processedKeys[] = $key;

                    $entreeData = $elementsEntree[$key] ?? null;
                    $sortieData = [
                        'nom' => $element->getNom(),
                        'type' => $element->getType(),
                        'etat' => $element->getEtat(),
                        'observations' => $element->getObservations(),
                    ];

                    $evolution = 'nouveau';
                    if ($entreeData) {
                        $scoreEntree = $etatScore[$entreeData['etat']] ?? 0;
                        $scoreSortie = $etatScore[$sortieData['etat']] ?? 0;

                        if ($scoreSortie > $scoreEntree) {
                            $evolution = 'ameliore';
                            $comparatif['statistiques']['elementsAmeliores']++;
                        } elseif ($scoreSortie < $scoreEntree) {
                            $evolution = 'degrade';
                            $comparatif['statistiques']['elementsDegrades']++;

                            $comparatif['degradations'][] = [
                                'piece' => $pieceName,
                                'element' => $element->getNom(),
                                'type' => $element->getType(),
                                'etatEntree' => $entreeData['etat'],
                                'etatSortie' => $sortieData['etat'],
                                'observations' => $sortieData['observations'],
                            ];
                        } else {
                            $evolution = 'identique';
                            $comparatif['statistiques']['elementsIdentiques']++;
                        }
                    }

                    $comparatif['statistiques']['totalElements']++;

                    $comparatif['pieces'][$pieceName][] = [
                        'element' => $element->getNom(),
                        'type' => $element->getType(),
                        'entree' => $entreeData ? [
                            'etat' => $entreeData['etat'],
                            'observations' => $entreeData['observations'],
                        ] : null,
                        'sortie' => [
                            'etat' => $sortieData['etat'],
                            'observations' => $sortieData['observations'],
                        ],
                        'evolution' => $evolution,
                    ];
                }
            }
        }

        // Compteurs
        $compteursEntree = [];
        if ($entree) {
            foreach ($entree->getCompteurs() as $c) {
                $compteursEntree[$c->getType()] = [
                    'numero' => $c->getNumero(),
                    'index' => $c->getIndexValue(),
                ];
            }
        }

        if ($sortie) {
            foreach ($sortie->getCompteurs() as $c) {
                $entreeC = $compteursEntree[$c->getType()] ?? null;
                $comparatif['compteurs'][] = [
                    'type' => $c->getType(),
                    'entree' => $entreeC,
                    'sortie' => [
                        'numero' => $c->getNumero(),
                        'index' => $c->getIndexValue(),
                    ],
                    'consommation' => ($entreeC && $c->getIndexValue() && $entreeC['index'])
                        ? (int)$c->getIndexValue() - (int)$entreeC['index']
                        : null,
                ];
            }
        }

        // Clés
        $clesEntree = [];
        if ($entree) {
            foreach ($entree->getCles() as $c) {
                $clesEntree[$c->getType()] = $c->getNombre();
            }
        }

        if ($sortie) {
            foreach ($sortie->getCles() as $c) {
                $nbEntree = $clesEntree[$c->getType()] ?? 0;
                $comparatif['cles'][] = [
                    'type' => $c->getType(),
                    'entree' => $nbEntree,
                    'sortie' => $c->getNombre(),
                    'difference' => $c->getNombre() - $nbEntree,
                ];
            }
        }

        return $comparatif;
    }

}
