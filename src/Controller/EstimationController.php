<?php

namespace App\Controller;

use App\Controller\Trait\AuthorizationTrait;
use App\Entity\CoutReparation;
use App\Entity\EtatDesLieux;
use App\Entity\Logement;
use App\Entity\User;
use App\Repository\EtatDesLieuxRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EstimationController extends AbstractController
{
    use AuthorizationTrait;
    // Grille tarifaire indicative par type d'élément et niveau de dégradation
    private const GRILLE_TARIFS = [
        'sol' => [
            'usage' => 50,
            'mauvais' => 150,
            'hors_service' => 400,
        ],
        'mur' => [
            'usage' => 30,
            'mauvais' => 100,
            'hors_service' => 250,
        ],
        'plafond' => [
            'usage' => 40,
            'mauvais' => 120,
            'hors_service' => 300,
        ],
        'menuiserie' => [
            'usage' => 50,
            'mauvais' => 150,
            'hors_service' => 350,
        ],
        'electricite' => [
            'usage' => 30,
            'mauvais' => 80,
            'hors_service' => 200,
        ],
        'plomberie' => [
            'usage' => 60,
            'mauvais' => 180,
            'hors_service' => 450,
        ],
        'chauffage' => [
            'usage' => 50,
            'mauvais' => 200,
            'hors_service' => 500,
        ],
        'equipement' => [
            'usage' => 30,
            'mauvais' => 100,
            'hors_service' => 250,
        ],
        'mobilier' => [
            'usage' => 40,
            'mauvais' => 120,
            'hors_service' => 300,
        ],
        'electromenager' => [
            'usage' => 80,
            'mauvais' => 250,
            'hors_service' => 600,
        ],
        'autre' => [
            'usage' => 30,
            'mauvais' => 100,
            'hors_service' => 200,
        ],
    ];

    // Coût par clé manquante
    private const COUT_CLE = 25;

    #[Route('/api/logements/{id}/estimations', name: 'api_logement_estimations', methods: ['GET'])]
    public function estimations(
        int $id,
        EntityManagerInterface $em,
        EtatDesLieuxRepository $edlRepo
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $logement = $em->getRepository(Logement::class)->find($id);

        if (!$logement) {
            return new JsonResponse(['error' => 'Logement non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($logement, $user)) return $denied;

        // Récupérer le dernier EDL d'entrée et de sortie (avec eager loading)
        $edlEntree = $edlRepo->findLastByLogementTypeAndStatuts($logement, 'entree', ['termine', 'signe']);
        $edlSortie = $edlRepo->findLastByLogementTypeAndStatuts($logement, 'sortie', ['termine', 'signe']);

        if (!$edlEntree || !$edlSortie) {
            return new JsonResponse([
                'error' => 'Un état des lieux d\'entrée ET de sortie (terminé/signé) est requis pour calculer les estimations'
            ], Response::HTTP_BAD_REQUEST);
        }

        $estimations = $this->calculateEstimations($edlEntree, $edlSortie);

        return new JsonResponse([
            'logement' => [
                'id' => $logement->getId(),
                'nom' => $logement->getNom(),
            ],
            'edlEntree' => [
                'id' => $edlEntree->getId(),
                'date' => $edlEntree->getDateRealisation()->format('Y-m-d'),
            ],
            'edlSortie' => [
                'id' => $edlSortie->getId(),
                'date' => $edlSortie->getDateRealisation()->format('Y-m-d'),
            ],
            'estimations' => $estimations,
        ]);
    }

    private function calculateEstimations(EtatDesLieux $entree, EtatDesLieux $sortie): array
    {
        $etatScore = [
            'neuf' => 6,
            'tres_bon' => 5,
            'bon' => 4,
            'usage' => 3,
            'mauvais' => 2,
            'hors_service' => 1,
        ];

        // Indexer les éléments d'entrée
        $elementsEntree = [];
        foreach ($entree->getPieces() as $piece) {
            foreach ($piece->getElements() as $element) {
                $key = $piece->getNom() . '|' . $element->getNom() . '|' . $element->getType();
                $elementsEntree[$key] = [
                    'etat' => $element->getEtat(),
                    'type' => $element->getType(),
                ];
            }
        }

        $degradations = [];
        $totalEstimation = 0;

        // Analyser les éléments de sortie
        foreach ($sortie->getPieces() as $piece) {
            foreach ($piece->getElements() as $element) {
                $key = $piece->getNom() . '|' . $element->getNom() . '|' . $element->getType();
                $entreeData = $elementsEntree[$key] ?? null;

                $etatSortie = $element->getEtat();
                $scoreSortie = $etatScore[$etatSortie] ?? 0;

                // Si nouvel élément en mauvais état ou dégradation par rapport à l'entrée
                $isDegraded = false;
                $etatEntree = null;

                if ($entreeData) {
                    $etatEntree = $entreeData['etat'];
                    $scoreEntree = $etatScore[$etatEntree] ?? 0;
                    $isDegraded = $scoreSortie < $scoreEntree;
                } else {
                    // Nouvel élément : considéré comme dégradé si pas en bon état
                    $isDegraded = in_array($etatSortie, ['usage', 'mauvais', 'hors_service']);
                }

                if ($isDegraded) {
                    $type = $element->getType();
                    $tarif = self::GRILLE_TARIFS[$type][$etatSortie] ?? 0;

                    // Utiliser l'estimation personnalisée si disponible dans les dégradations
                    $customEstimation = null;
                    if ($element->getDegradations() && isset($element->getDegradations()['estimationReparation'])) {
                        $customEstimation = (float) $element->getDegradations()['estimationReparation'];
                        $tarif = $customEstimation;
                    }

                    if ($tarif > 0) {
                        $degradations[] = [
                            'piece' => $piece->getNom(),
                            'element' => $element->getNom(),
                            'type' => $type,
                            'etatEntree' => $etatEntree ?? 'N/A',
                            'etatSortie' => $etatSortie,
                            'estimationGrille' => self::GRILLE_TARIFS[$type][$etatSortie] ?? 0,
                            'estimationPersonnalisee' => $customEstimation,
                            'montantRetenu' => $tarif,
                            'observations' => $element->getObservations(),
                        ];

                        $totalEstimation += $tarif;
                    }
                }
            }
        }

        // Calculer les clés manquantes
        $clesEntree = [];
        foreach ($entree->getCles() as $cle) {
            $clesEntree[$cle->getType()] = $cle->getNombre();
        }

        $clesManquantes = [];
        foreach ($sortie->getCles() as $cle) {
            $nbEntree = $clesEntree[$cle->getType()] ?? 0;
            $diff = $nbEntree - $cle->getNombre();

            if ($diff > 0) {
                $montant = $diff * self::COUT_CLE;
                $clesManquantes[] = [
                    'type' => $cle->getType(),
                    'quantiteEntree' => $nbEntree,
                    'quantiteSortie' => $cle->getNombre(),
                    'manquantes' => $diff,
                    'coutUnitaire' => self::COUT_CLE,
                    'montant' => $montant,
                ];
                $totalEstimation += $montant;
            }
        }

        return [
            'degradations' => $degradations,
            'clesManquantes' => $clesManquantes,
            'sousTotal' => [
                'degradations' => array_sum(array_column($degradations, 'montantRetenu')),
                'cles' => array_sum(array_column($clesManquantes, 'montant')),
            ],
            'total' => $totalEstimation,
            'grilleUtilisee' => self::GRILLE_TARIFS,
            'coutCleUnitaire' => self::COUT_CLE,
        ];
    }

    #[Route('/api/couts-reparation', name: 'api_couts_reparation', methods: ['GET'])]
    public function coutsReparation(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $couts = $em->getRepository(CoutReparation::class)->findBy(['actif' => true], ['typeElement' => 'ASC', 'nom' => 'ASC']);

        $grouped = [];
        foreach ($couts as $cout) {
            $type = $cout->getTypeElement();
            $grouped[$type][] = [
                'id' => $cout->getId(),
                'nom' => $cout->getNom(),
                'description' => $cout->getDescription(),
                'unite' => $cout->getUnite(),
                'prix_unitaire' => $cout->getPrixUnitaire(),
            ];
        }

        return new JsonResponse($grouped);
    }
}
