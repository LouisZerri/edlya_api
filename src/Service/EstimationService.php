<?php

namespace App\Service;

use App\Entity\Element;
use App\Entity\EtatDesLieux;

/**
 * Service de calcul des estimations et retenues sur caution
 * Contient la logique métier et les grilles tarifaires
 */
class EstimationService
{
    // Scores d'état pour comparer les dégradations (6 = meilleur, 1 = pire)
    public const ETAT_SCORES = [
        'neuf' => 6,
        'tres_bon' => 5,
        'bon' => 4,
        'usage' => 3,
        'mauvais' => 2,
        'hors_service' => 1,
    ];

    // Grille tarifaire indicative par type d'élément (en euros)
    public const TARIFS_ELEMENTS = [
        'sol' => ['nettoyage' => 50, 'reparation' => 150, 'remplacement' => 500],
        'mur' => ['nettoyage' => 30, 'reparation' => 100, 'remplacement' => 300],
        'plafond' => ['nettoyage' => 40, 'reparation' => 120, 'remplacement' => 400],
        'menuiserie' => ['nettoyage' => 30, 'reparation' => 80, 'remplacement' => 250],
        'electricite' => ['nettoyage' => 20, 'reparation' => 60, 'remplacement' => 150],
        'plomberie' => ['nettoyage' => 30, 'reparation' => 100, 'remplacement' => 300],
        'chauffage' => ['nettoyage' => 40, 'reparation' => 150, 'remplacement' => 500],
        'equipement' => ['nettoyage' => 20, 'reparation' => 50, 'remplacement' => 150],
        'mobilier' => ['nettoyage' => 30, 'reparation' => 80, 'remplacement' => 200],
        'electromenager' => ['nettoyage' => 30, 'reparation' => 100, 'remplacement' => 400],
        'autre' => ['nettoyage' => 30, 'reparation' => 80, 'remplacement' => 200],
    ];

    // Tarifs des clés (en euros)
    public const TARIFS_CLES = [
        'porte_entree' => 150,
        'boite_lettres' => 30,
        'cave' => 50,
        'garage' => 80,
        'parking' => 50,
        'local_velo' => 40,
        'portail' => 100,
        'interphone' => 60,
        'badge' => 80,
        'telecommande' => 100,
        'autre' => 50,
    ];

    // Grille de vétusté standard (répartition locataire/bailleur selon durée de location)
    public const GRILLE_VETUSTE = [
        ['annees' => '0-2', 'taux_locataire' => 100, 'taux_bailleur' => 0],
        ['annees' => '2-4', 'taux_locataire' => 80, 'taux_bailleur' => 20],
        ['annees' => '4-6', 'taux_locataire' => 60, 'taux_bailleur' => 40],
        ['annees' => '6-8', 'taux_locataire' => 40, 'taux_bailleur' => 60],
        ['annees' => '8-10', 'taux_locataire' => 20, 'taux_bailleur' => 80],
        ['annees' => '10+', 'taux_locataire' => 0, 'taux_bailleur' => 100],
    ];

    /**
     * Calcule le taux de vétusté (% à charge du locataire) basé sur la durée de location
     */
    public function calculerTauxVetuste(?\DateTimeInterface $dateEntree, \DateTimeInterface $dateSortie): int
    {
        if (!$dateEntree) {
            return 100;
        }

        $annees = $dateEntree->diff($dateSortie)->y;

        return match (true) {
            $annees >= 10 => 0,
            $annees >= 8 => 20,
            $annees >= 6 => 40,
            $annees >= 4 => 60,
            $annees >= 2 => 80,
            default => 100,
        };
    }

    /**
     * Détermine le type d'intervention nécessaire selon l'état
     */
    public function determinerIntervention(string $etat): string
    {
        return match ($etat) {
            'hors_service' => 'remplacement',
            'mauvais' => 'reparation',
            default => 'nettoyage',
        };
    }

    /**
     * Récupère les photos d'un élément sous forme de tableau
     */
    public function getPhotosArray(Element $element): array
    {
        $photos = [];
        foreach ($element->getPhotos() as $photo) {
            $photos[] = [
                'id' => $photo->getId(),
                'chemin' => $photo->getChemin(),
                'legende' => $photo->getLegende(),
            ];
        }
        return $photos;
    }

    /**
     * Indexe les éléments d'un EDL par clé unique (piece|element|type)
     */
    public function indexerElements(EtatDesLieux $edl): array
    {
        $index = [];
        foreach ($edl->getPieces() as $piece) {
            foreach ($piece->getElements() as $element) {
                $key = $piece->getNom() . '|' . $element->getNom() . '|' . $element->getType();
                $index[$key] = [
                    'etat' => $element->getEtat(),
                    'type' => $element->getType(),
                ];
            }
        }
        return $index;
    }

    /**
     * Compare les clés entre EDL d'entrée et de sortie
     * Retourne la liste des clés manquantes avec leur coût
     */
    public function comparerCles(?EtatDesLieux $edlEntree, EtatDesLieux $edlSortie): array
    {
        $clesEntree = [];
        if ($edlEntree) {
            foreach ($edlEntree->getCles() as $cle) {
                $clesEntree[$cle->getType()] = ($clesEntree[$cle->getType()] ?? 0) + $cle->getNombre();
            }
        }

        $clesSortie = [];
        foreach ($edlSortie->getCles() as $cle) {
            $clesSortie[$cle->getType()] = ($clesSortie[$cle->getType()] ?? 0) + $cle->getNombre();
        }

        $clesManquantes = [];
        foreach ($clesEntree as $type => $nombreEntree) {
            $nombreSortie = $clesSortie[$type] ?? 0;
            $diff = $nombreEntree - $nombreSortie;
            if ($diff > 0) {
                $coutUnitaire = self::TARIFS_CLES[$type] ?? 50;
                $clesManquantes[] = [
                    'type' => $type,
                    'nombre_entree' => $nombreEntree,
                    'nombre_sortie' => $nombreSortie,
                    'manquantes' => $diff,
                    'cout_unitaire' => $coutUnitaire,
                    'cout_total' => $coutUnitaire * $diff,
                ];
            }
        }

        return $clesManquantes;
    }

    /**
     * Collecte les dégradations entre deux EDL (pour l'estimation IA)
     */
    public function collecterDegradations(EtatDesLieux $entree, EtatDesLieux $sortie): array
    {
        $elementsEntree = $this->indexerElements($entree);
        $degradations = [];

        foreach ($sortie->getPieces() as $piece) {
            foreach ($piece->getElements() as $element) {
                $key = $piece->getNom() . '|' . $element->getNom() . '|' . $element->getType();
                $entreeData = $elementsEntree[$key] ?? null;

                $etatSortie = $element->getEtat();
                $scoreSortie = self::ETAT_SCORES[$etatSortie] ?? 0;

                $etatEntree = $entreeData['etat'] ?? null;
                $scoreEntree = self::ETAT_SCORES[$etatEntree] ?? 0;

                $isDegraded = $entreeData
                    ? $scoreSortie < $scoreEntree
                    : in_array($etatSortie, ['usage', 'mauvais', 'hors_service']);

                if ($isDegraded) {
                    $degradations[] = [
                        'piece' => $piece->getNom(),
                        'piece_id' => $piece->getId(),
                        'element' => $element->getNom(),
                        'element_id' => $element->getId(),
                        'type' => $element->getType(),
                        'etat_entree' => $etatEntree ?? 'N/A',
                        'etat_sortie' => $etatSortie,
                        'observations' => $element->getObservations(),
                        'degradations_specifiques' => $element->getDegradations(),
                        'photos' => $this->getPhotosArray($element),
                    ];
                }
            }
        }

        return $degradations;
    }

    /**
     * Calcule les estimations complètes pour un EDL de sortie
     * Retourne un tableau avec toutes les données pour la réponse API
     */
    public function calculerEstimations(?EtatDesLieux $edlEntree, EtatDesLieux $edlSortie, float $depotGarantie): array
    {
        // Calculer le taux de vétusté
        $tauxVetuste = $this->calculerTauxVetuste(
            $edlEntree?->getDateRealisation(),
            $edlSortie->getDateRealisation()
        );

        // Indexer les éléments d'entrée si disponible
        $elementsEntree = $edlEntree ? $this->indexerElements($edlEntree) : [];

        // Collecter les dégradations avec calcul des coûts
        $degradations = [];
        $totalRetenues = 0;

        foreach ($edlSortie->getPieces() as $piece) {
            foreach ($piece->getElements() as $element) {
                $key = $piece->getNom() . '|' . $element->getNom() . '|' . $element->getType();
                $entreeData = $elementsEntree[$key] ?? null;

                $etatSortie = $element->getEtat();
                $scoreSortie = self::ETAT_SCORES[$etatSortie] ?? 0;

                $etatEntree = $entreeData['etat'] ?? 'bon';
                $scoreEntree = self::ETAT_SCORES[$etatEntree] ?? 0;

                // Déterminer si dégradé
                $isDegraded = $entreeData
                    ? $scoreSortie < $scoreEntree
                    : in_array($etatSortie, ['mauvais', 'hors_service']);

                if ($isDegraded) {
                    $intervention = $this->determinerIntervention($etatSortie);
                    $type = $element->getType() ?? 'autre';
                    $coutBrut = self::TARIFS_ELEMENTS[$type][$intervention] ?? 80;
                    $coutApresVetuste = round($coutBrut * $tauxVetuste / 100, 2);

                    $degradations[] = [
                        'piece' => $piece->getNom(),
                        'piece_id' => $piece->getId(),
                        'element' => $element->getNom(),
                        'element_id' => $element->getId(),
                        'type' => $type,
                        'etat_entree' => $etatEntree,
                        'etat_sortie' => $etatSortie,
                        'observations' => $element->getObservations(),
                        'degradations_specifiques' => $element->getDegradations(),
                        'intervention' => $intervention,
                        'cout_brut' => $coutBrut,
                        'taux_vetuste' => $tauxVetuste,
                        'cout_apres_vetuste' => $coutApresVetuste,
                        'photos' => $this->getPhotosArray($element),
                    ];

                    $totalRetenues += $coutApresVetuste;
                }
            }
        }

        // Comparer les clés
        $clesManquantes = $this->comparerCles($edlEntree, $edlSortie);
        $coutCles = array_sum(array_column($clesManquantes, 'cout_total'));
        $totalRetenues += $coutCles;

        $aRestituer = max(0, $depotGarantie - $totalRetenues);

        return [
            'edl_id' => $edlSortie->getId(),
            'edl_entree_id' => $edlEntree?->getId(),
            'logement' => [
                'id' => $edlSortie->getLogement()->getId(),
                'nom' => $edlSortie->getLogement()->getNom(),
                'adresse' => $edlSortie->getLogement()->getAdresse(),
            ],
            'locataire' => [
                'nom' => $edlSortie->getLocataireNom(),
                'email' => $edlSortie->getLocataireEmail(),
            ],
            'dates' => [
                'entree' => $edlEntree?->getDateRealisation()?->format('Y-m-d'),
                'sortie' => $edlSortie->getDateRealisation()->format('Y-m-d'),
                'duree_location_mois' => $edlEntree
                    ? $edlEntree->getDateRealisation()->diff($edlSortie->getDateRealisation())->m
                      + ($edlEntree->getDateRealisation()->diff($edlSortie->getDateRealisation())->y * 12)
                    : null,
            ],
            'depot_garantie' => $depotGarantie,
            'total_retenues' => round($totalRetenues, 2),
            'a_restituer' => round($aRestituer, 2),
            'taux_vetuste_applique' => $tauxVetuste,
            'degradations' => $degradations,
            'cles_manquantes' => $clesManquantes,
            'grille_vetuste' => self::GRILLE_VETUSTE,
            'resume' => [
                'nb_degradations' => count($degradations),
                'nb_cles_manquantes' => array_sum(array_column($clesManquantes, 'manquantes')),
                'cout_degradations' => round($totalRetenues - $coutCles, 2),
                'cout_cles' => round($coutCles, 2),
            ],
        ];
    }
}
