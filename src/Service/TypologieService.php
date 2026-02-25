<?php

namespace App\Service;

use App\Entity\EtatDesLieux;
use App\Entity\Piece;
use Doctrine\ORM\EntityManagerInterface;

class TypologieService
{
    private const TYPOLOGIES = [
        'studio' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Pièce principale', 'ordre' => 1],
            ['nom' => 'Coin cuisine', 'ordre' => 2],
            ['nom' => 'Salle de bain/WC', 'ordre' => 3],
        ],
        'f1' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Salle de bain', 'ordre' => 3],
            ['nom' => 'WC', 'ordre' => 4],
        ],
        't1' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Salle de bain', 'ordre' => 3],
            ['nom' => 'WC', 'ordre' => 4],
        ],
        'f2' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre', 'ordre' => 3],
            ['nom' => 'Salle de bain', 'ordre' => 4],
            ['nom' => 'WC', 'ordre' => 5],
        ],
        't2' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre', 'ordre' => 3],
            ['nom' => 'Salle de bain', 'ordre' => 4],
            ['nom' => 'WC', 'ordre' => 5],
        ],
        'f3' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre 1', 'ordre' => 3],
            ['nom' => 'Chambre 2', 'ordre' => 4],
            ['nom' => 'Salle de bain', 'ordre' => 5],
            ['nom' => 'WC', 'ordre' => 6],
        ],
        't3' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre 1', 'ordre' => 3],
            ['nom' => 'Chambre 2', 'ordre' => 4],
            ['nom' => 'Salle de bain', 'ordre' => 5],
            ['nom' => 'WC', 'ordre' => 6],
        ],
        'f4' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre 1', 'ordre' => 3],
            ['nom' => 'Chambre 2', 'ordre' => 4],
            ['nom' => 'Chambre 3', 'ordre' => 5],
            ['nom' => 'Salle de bain', 'ordre' => 6],
            ['nom' => 'WC', 'ordre' => 7],
        ],
        't4' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre 1', 'ordre' => 3],
            ['nom' => 'Chambre 2', 'ordre' => 4],
            ['nom' => 'Chambre 3', 'ordre' => 5],
            ['nom' => 'Salle de bain', 'ordre' => 6],
            ['nom' => 'WC', 'ordre' => 7],
        ],
        'f5' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre 1', 'ordre' => 3],
            ['nom' => 'Chambre 2', 'ordre' => 4],
            ['nom' => 'Chambre 3', 'ordre' => 5],
            ['nom' => 'Chambre 4', 'ordre' => 6],
            ['nom' => 'Salle de bain 1', 'ordre' => 7],
            ['nom' => 'Salle de bain 2', 'ordre' => 8],
            ['nom' => 'WC', 'ordre' => 9],
        ],
        't5' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre 1', 'ordre' => 3],
            ['nom' => 'Chambre 2', 'ordre' => 4],
            ['nom' => 'Chambre 3', 'ordre' => 5],
            ['nom' => 'Chambre 4', 'ordre' => 6],
            ['nom' => 'Salle de bain 1', 'ordre' => 7],
            ['nom' => 'Salle de bain 2', 'ordre' => 8],
            ['nom' => 'WC', 'ordre' => 9],
        ],
        'maison_t3' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre 1', 'ordre' => 3],
            ['nom' => 'Chambre 2', 'ordre' => 4],
            ['nom' => 'Salle de bain', 'ordre' => 5],
            ['nom' => 'WC', 'ordre' => 6],
            ['nom' => 'Garage', 'ordre' => 7],
            ['nom' => 'Jardin/Extérieur', 'ordre' => 8],
        ],
        'maison_t4' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre 1', 'ordre' => 3],
            ['nom' => 'Chambre 2', 'ordre' => 4],
            ['nom' => 'Chambre 3', 'ordre' => 5],
            ['nom' => 'Salle de bain', 'ordre' => 6],
            ['nom' => 'WC', 'ordre' => 7],
            ['nom' => 'Garage', 'ordre' => 8],
            ['nom' => 'Jardin/Extérieur', 'ordre' => 9],
        ],
        'maison_t5' => [
            ['nom' => 'Entrée', 'ordre' => 0],
            ['nom' => 'Séjour', 'ordre' => 1],
            ['nom' => 'Cuisine', 'ordre' => 2],
            ['nom' => 'Chambre 1', 'ordre' => 3],
            ['nom' => 'Chambre 2', 'ordre' => 4],
            ['nom' => 'Chambre 3', 'ordre' => 5],
            ['nom' => 'Chambre 4', 'ordre' => 6],
            ['nom' => 'Salle de bain 1', 'ordre' => 7],
            ['nom' => 'Salle de bain 2', 'ordre' => 8],
            ['nom' => 'WC 1', 'ordre' => 9],
            ['nom' => 'WC 2', 'ordre' => 10],
            ['nom' => 'Garage', 'ordre' => 11],
            ['nom' => 'Jardin/Extérieur', 'ordre' => 12],
        ],
    ];

    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function getTypologies(): array
    {
        $result = [];

        foreach (self::TYPOLOGIES as $code => $pieces) {
            $result[] = [
                'code' => $code,
                'nom' => $this->getTypologieNom($code),
                'nbPieces' => count($pieces),
            ];
        }

        return $result;
    }

    public function getTypologieNom(string $code): string
    {
        $noms = [
            'studio' => 'Studio',
            'f1' => 'F1 / T1',
            't1' => 'F1 / T1',
            'f2' => 'F2 / T2',
            't2' => 'F2 / T2',
            'f3' => 'F3 / T3',
            't3' => 'F3 / T3',
            'f4' => 'F4 / T4',
            't4' => 'F4 / T4',
            'f5' => 'F5 / T5',
            't5' => 'F5 / T5',
            'maison_t3' => 'Maison T3',
            'maison_t4' => 'Maison T4',
            'maison_t5' => 'Maison T5+',
        ];

        return $noms[$code] ?? ucfirst($code);
    }

    public function genererPieces(EtatDesLieux $edl, string $typologie): array
    {
        $typologie = strtolower($typologie);

        if (!isset(self::TYPOLOGIES[$typologie])) {
            throw new \InvalidArgumentException("Typologie '$typologie' non reconnue");
        }

        $piecesConfig = self::TYPOLOGIES[$typologie];
        $piecesCreees = [];

        foreach ($piecesConfig as $config) {
            $piece = new Piece();
            $piece->setEtatDesLieux($edl);
            $piece->setNom($config['nom']);
            $piece->setOrdre($config['ordre']);

            $this->em->persist($piece);
            $edl->addPiece($piece);

            $piecesCreees[] = $piece;
        }

        $this->em->flush();

        return $piecesCreees;
    }

    public function getDegradationsParType(): array
    {
        return [
            'mur' => [
                'Trou(s)', 'Fissure(s)', 'Tache(s)', 'Éclats de peinture', 'Humidité',
                'Moisissures', 'Papier décollé', 'Griffures', 'Salissures', 'Impacts',
            ],
            'plafond' => [
                'Fissure(s)', 'Tache(s)', 'Humidité', 'Moisissures', 'Éclats de peinture',
                'Affaissement', 'Décollement',
            ],
            'sol' => [
                'Rayure(s)', 'Tache(s)', 'Usure', 'Décollement', 'Carreaux cassés',
                'Joints abîmés', 'Gondolement', 'Éclats', 'Brûlure(s)',
            ],
            'menuiserie' => [
                'Vitre fêlée', 'Vitre cassée', 'Joint défectueux', 'Poignée cassée',
                'Fermeture défectueuse', 'Bois abîmé', 'Peinture écaillée',
                'Volet bloqué', 'Gonds défectueux',
            ],
            'electricite' => [
                'Prise cassée', 'Cache manquant', 'Interrupteur cassé', 'Ampoule grillée',
                'Ne fonctionne pas', 'Fil apparent', 'Détecteur absent',
            ],
            'plomberie' => [
                'Fuite', 'Évacuation bouchée', 'Joint défectueux', 'Robinet fuit',
                'Calcaire', 'Tuyau abîmé', 'Siphon bouché', 'Chasse d\'eau défectueuse',
            ],
            'chauffage' => [
                'Ne chauffe pas', 'Fuite', 'Thermostat défectueux', 'Rouille',
                'Bruit anormal', 'Vanne bloquée', 'Purge nécessaire',
            ],
            'equipement' => [
                'Cassé', 'Manquant', 'Usé', 'Sale', 'Incomplet', 'Rayé', 'Fissuré',
            ],
            'mobilier' => [
                'Cassé', 'Rayé', 'Taché', 'Usé', 'Porte cassée', 'Tiroir cassé',
                'Charnière défectueuse',
            ],
            'electromenager' => [
                'Ne fonctionne pas', 'Bruit anormal', 'Fuite', 'Sale', 'Joint usé',
                'Vitre cassée', 'Bouton cassé',
            ],
            'autre' => [
                'Défectueux', 'Manquant', 'Cassé', 'Usé',
            ],
        ];
    }
}
