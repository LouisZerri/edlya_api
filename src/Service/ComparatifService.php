<?php

namespace App\Service;

use App\Entity\EtatDesLieux;

class ComparatifService
{
    private const ETAT_SCORE = [
        'neuf' => 6,
        'tres_bon' => 5,
        'bon' => 4,
        'usage' => 3,
        'mauvais' => 2,
        'hors_service' => 1,
    ];

    public function buildComparatif(?EtatDesLieux $entree, ?EtatDesLieux $sortie): array
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
                        $scoreEntree = self::ETAT_SCORE[$entreeData['etat']] ?? 0;
                        $scoreSortie = self::ETAT_SCORE[$sortieData['etat']] ?? 0;

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
