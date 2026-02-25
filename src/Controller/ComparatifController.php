<?php

namespace App\Controller;

use App\Entity\EtatDesLieux;
use App\Entity\Logement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ComparatifController extends AbstractController
{
    /**
     * Comparatif à partir d'un EDL (entrée ou sortie)
     * Trouve automatiquement l'EDL correspondant du même logement
     */
    #[Route('/api/edl/{id}/comparatif', name: 'api_edl_comparatif', methods: ['GET'])]
    public function comparatifEdl(
        int $id,
        EntityManagerInterface $em
    ): JsonResponse {
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

        $logement = $edl->getLogement();

        // Déterminer si c'est un EDL d'entrée ou de sortie et trouver l'autre
        if ($edl->getType() === 'sortie') {
            $edlSortie = $edl;
            // Chercher le dernier EDL d'entrée pour ce logement
            $edlEntree = $em->getRepository(EtatDesLieux::class)->findOneBy(
                ['logement' => $logement, 'type' => 'entree'],
                ['dateRealisation' => 'DESC']
            );
        } else {
            $edlEntree = $edl;
            // Chercher le dernier EDL de sortie pour ce logement
            $edlSortie = $em->getRepository(EtatDesLieux::class)->findOneBy(
                ['logement' => $logement, 'type' => 'sortie'],
                ['dateRealisation' => 'DESC']
            );
        }

        $comparatif = $this->buildComparatif($edlEntree, $edlSortie);

        return new JsonResponse([
            'logement' => [
                'id' => $logement->getId(),
                'nom' => $logement->getNom(),
                'adresse' => $logement->getAdresse(),
                'ville' => $logement->getVille(),
            ],
            'entree' => $edlEntree ? $this->formatEdlSummary($edlEntree) : null,
            'sortie' => $edlSortie ? $this->formatEdlSummary($edlSortie) : null,
            'comparatif' => $comparatif,
        ]);
    }

    #[Route('/api/logements/{id}/comparatif', name: 'api_logement_comparatif', methods: ['GET'])]
    public function comparatif(
        int $id,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $logement = $em->getRepository(Logement::class)->find($id);

        if (!$logement) {
            return new JsonResponse(['error' => 'Logement non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($logement->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        // Récupérer le dernier EDL d'entrée et de sortie signés ou terminés
        $edlEntree = $em->getRepository(EtatDesLieux::class)->findOneBy(
            ['logement' => $logement, 'type' => 'entree', 'statut' => ['termine', 'signe']],
            ['dateRealisation' => 'DESC']
        );

        $edlSortie = $em->getRepository(EtatDesLieux::class)->findOneBy(
            ['logement' => $logement, 'type' => 'sortie', 'statut' => ['termine', 'signe']],
            ['dateRealisation' => 'DESC']
        );

        if (!$edlEntree && !$edlSortie) {
            return new JsonResponse([
                'error' => 'Aucun état des lieux d\'entrée ou de sortie terminé/signé trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $comparatif = $this->buildComparatif($edlEntree, $edlSortie);

        return new JsonResponse([
            'logement' => [
                'id' => $logement->getId(),
                'nom' => $logement->getNom(),
                'adresse' => $logement->getAdresse(),
                'ville' => $logement->getVille(),
            ],
            'entree' => $edlEntree ? $this->formatEdlSummary($edlEntree) : null,
            'sortie' => $edlSortie ? $this->formatEdlSummary($edlSortie) : null,
            'comparatif' => $comparatif,
        ]);
    }

    private function formatEdlSummary(EtatDesLieux $edl): array
    {
        return [
            'id' => $edl->getId(),
            'type' => $edl->getType(),
            'dateRealisation' => $edl->getDateRealisation()->format('Y-m-d'),
            'locataireNom' => $edl->getLocataireNom(),
            'statut' => $edl->getStatut(),
        ];
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

        // Mapping des états vers un score (plus c'est haut, meilleur c'est)
        $etatScore = [
            'neuf' => 6,
            'tres_bon' => 5,
            'bon' => 4,
            'usage' => 3,
            'mauvais' => 2,
            'hors_service' => 1,
        ];

        // Indexer les éléments par pièce et nom pour l'entrée
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
                        'degradations' => $element->getDegradations(),
                    ];
                }
            }
        }

        // Comparer avec les éléments de sortie
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
                        'degradations' => $element->getDegradations(),
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

                            // Ajouter aux dégradations
                            $comparatif['degradations'][] = [
                                'piece' => $pieceName,
                                'element' => $element->getNom(),
                                'type' => $element->getType(),
                                'etatEntree' => $entreeData['etat'],
                                'etatSortie' => $sortieData['etat'],
                                'observations' => $sortieData['observations'],
                                'degradationsDetail' => $sortieData['degradations'],
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

        // Ajouter les éléments d'entrée qui n'existent plus en sortie
        if ($entree && $sortie) {
            foreach ($elementsEntree as $key => $data) {
                if (!in_array($key, $processedKeys)) {
                    $pieceName = $data['piece'];
                    if (!isset($comparatif['pieces'][$pieceName])) {
                        $comparatif['pieces'][$pieceName] = [];
                    }

                    $comparatif['pieces'][$pieceName][] = [
                        'element' => $data['nom'],
                        'type' => $data['type'],
                        'entree' => [
                            'etat' => $data['etat'],
                            'observations' => $data['observations'],
                        ],
                        'sortie' => null,
                        'evolution' => 'supprime',
                    ];
                }
            }
        }

        // Comparer les compteurs
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

        // Comparer les clés
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
