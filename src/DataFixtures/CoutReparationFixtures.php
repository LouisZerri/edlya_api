<?php

namespace App\DataFixtures;

use App\Entity\CoutReparation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CoutReparationFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $tarifs = [
            // SOLS (6)
            ['type_element' => 'sol', 'nom' => 'Ponçage et vitrification parquet', 'description' => 'Ponçage, vitrification ou huilage du parquet', 'unite' => 'm2', 'prix_unitaire' => 35.00],
            ['type_element' => 'sol', 'nom' => 'Remplacement lame parquet', 'description' => 'Remplacement d\'une ou plusieurs lames de parquet', 'unite' => 'unite', 'prix_unitaire' => 45.00],
            ['type_element' => 'sol', 'nom' => 'Remplacement moquette', 'description' => 'Dépose et pose de moquette neuve', 'unite' => 'm2', 'prix_unitaire' => 25.00],
            ['type_element' => 'sol', 'nom' => 'Nettoyage moquette professionnel', 'description' => 'Nettoyage professionnel de moquette (shampouineuse)', 'unite' => 'm2', 'prix_unitaire' => 8.00],
            ['type_element' => 'sol', 'nom' => 'Remplacement carreau carrelage', 'description' => 'Remplacement d\'un ou plusieurs carreaux cassés', 'unite' => 'unite', 'prix_unitaire' => 55.00],
            ['type_element' => 'sol', 'nom' => 'Réfection joints carrelage', 'description' => 'Réfection des joints de carrelage', 'unite' => 'm2', 'prix_unitaire' => 15.00],

            // MURS (5)
            ['type_element' => 'mur', 'nom' => 'Peinture mur', 'description' => 'Préparation et peinture murale (2 couches)', 'unite' => 'm2', 'prix_unitaire' => 18.00],
            ['type_element' => 'mur', 'nom' => 'Rebouchage trou (petit)', 'description' => 'Rebouchage petit trou (cheville, clou)', 'unite' => 'unite', 'prix_unitaire' => 8.00],
            ['type_element' => 'mur', 'nom' => 'Rebouchage trou (gros)', 'description' => 'Rebouchage gros trou avec enduit et ponçage', 'unite' => 'unite', 'prix_unitaire' => 25.00],
            ['type_element' => 'mur', 'nom' => 'Remplacement papier peint', 'description' => 'Dépose ancien papier peint et pose neuf', 'unite' => 'm2', 'prix_unitaire' => 22.00],
            ['type_element' => 'mur', 'nom' => 'Traitement humidité', 'description' => 'Diagnostic et traitement des traces d\'humidité', 'unite' => 'forfait', 'prix_unitaire' => 150.00],

            // PLAFONDS (2)
            ['type_element' => 'plafond', 'nom' => 'Peinture plafond', 'description' => 'Préparation et peinture plafond (2 couches)', 'unite' => 'm2', 'prix_unitaire' => 22.00],
            ['type_element' => 'plafond', 'nom' => 'Réparation fissure plafond', 'description' => 'Rebouchage et peinture fissure plafond', 'unite' => 'ml', 'prix_unitaire' => 15.00],

            // MENUISERIES (5)
            ['type_element' => 'menuiserie', 'nom' => 'Remplacement poignée fenêtre', 'description' => 'Fourniture et pose poignée de fenêtre', 'unite' => 'unite', 'prix_unitaire' => 35.00],
            ['type_element' => 'menuiserie', 'nom' => 'Remplacement joint fenêtre', 'description' => 'Remplacement joint d\'étanchéité fenêtre', 'unite' => 'ml', 'prix_unitaire' => 12.00],
            ['type_element' => 'menuiserie', 'nom' => 'Réglage/ajustement porte', 'description' => 'Réglage porte (gonds, fermeture)', 'unite' => 'unite', 'prix_unitaire' => 45.00],
            ['type_element' => 'menuiserie', 'nom' => 'Remplacement porte intérieure', 'description' => 'Fourniture et pose porte intérieure standard', 'unite' => 'unite', 'prix_unitaire' => 280.00],
            ['type_element' => 'menuiserie', 'nom' => 'Réparation volet roulant', 'description' => 'Réparation mécanisme volet roulant', 'unite' => 'unite', 'prix_unitaire' => 120.00],

            // ÉLECTRICITÉ (3)
            ['type_element' => 'electricite', 'nom' => 'Remplacement prise électrique', 'description' => 'Remplacement prise électrique (fourniture et pose)', 'unite' => 'unite', 'prix_unitaire' => 45.00],
            ['type_element' => 'electricite', 'nom' => 'Remplacement interrupteur', 'description' => 'Remplacement interrupteur (fourniture et pose)', 'unite' => 'unite', 'prix_unitaire' => 40.00],
            ['type_element' => 'electricite', 'nom' => 'Remplacement cache prise/interrupteur', 'description' => 'Remplacement cache ou plaque de finition', 'unite' => 'unite', 'prix_unitaire' => 12.00],

            // PLOMBERIE (5)
            ['type_element' => 'plomberie', 'nom' => 'Détartrage robinetterie', 'description' => 'Détartrage et nettoyage robinet/mitigeur', 'unite' => 'unite', 'prix_unitaire' => 35.00],
            ['type_element' => 'plomberie', 'nom' => 'Remplacement mitigeur', 'description' => 'Fourniture et pose mitigeur standard', 'unite' => 'unite', 'prix_unitaire' => 95.00],
            ['type_element' => 'plomberie', 'nom' => 'Remplacement flexible douche', 'description' => 'Fourniture et pose flexible de douche', 'unite' => 'unite', 'prix_unitaire' => 25.00],
            ['type_element' => 'plomberie', 'nom' => 'Remplacement abattant WC', 'description' => 'Fourniture et pose abattant WC standard', 'unite' => 'unite', 'prix_unitaire' => 45.00],
            ['type_element' => 'plomberie', 'nom' => 'Débouchage canalisation', 'description' => 'Débouchage canalisation par professionnel', 'unite' => 'forfait', 'prix_unitaire' => 85.00],

            // CHAUFFAGE (2)
            ['type_element' => 'chauffage', 'nom' => 'Purge radiateur', 'description' => 'Purge et vérification radiateur', 'unite' => 'unite', 'prix_unitaire' => 25.00],
            ['type_element' => 'chauffage', 'nom' => 'Remplacement robinet radiateur', 'description' => 'Fourniture et pose robinet/vanne radiateur', 'unite' => 'unite', 'prix_unitaire' => 65.00],

            // ÉQUIPEMENTS (6)
            ['type_element' => 'equipement', 'nom' => 'Réparation placard (rail/porte)', 'description' => 'Réparation rail coulissant ou porte placard', 'unite' => 'unite', 'prix_unitaire' => 55.00],
            ['type_element' => 'equipement', 'nom' => 'Remplacement étagère', 'description' => 'Fourniture et pose étagère', 'unite' => 'unite', 'prix_unitaire' => 35.00],
            ['type_element' => 'equipement', 'nom' => 'Nettoyage hotte aspirante', 'description' => 'Nettoyage professionnel hotte et filtres', 'unite' => 'forfait', 'prix_unitaire' => 45.00],
            ['type_element' => 'equipement', 'nom' => 'Remplacement hotte aspirante', 'description' => 'Fourniture et pose hotte aspirante standard', 'unite' => 'unite', 'prix_unitaire' => 250.00],
            ['type_element' => 'equipement', 'nom' => 'Remplacement plaque cuisson', 'description' => 'Fourniture et pose plaque de cuisson', 'unite' => 'unite', 'prix_unitaire' => 350.00],
            ['type_element' => 'equipement', 'nom' => 'Réparation plan de travail', 'description' => 'Réparation ou remplacement partiel plan de travail', 'unite' => 'ml', 'prix_unitaire' => 40.00],

            // NETTOYAGE GÉNÉRAL (2)
            ['type_element' => 'autre', 'nom' => 'Nettoyage fin de bail', 'description' => 'Nettoyage complet du logement', 'unite' => 'm2', 'prix_unitaire' => 5.00],
            ['type_element' => 'autre', 'nom' => 'Évacuation encombrants', 'description' => 'Évacuation objets et encombrants laissés', 'unite' => 'forfait', 'prix_unitaire' => 150.00],
        ];

        $now = new \DateTimeImmutable();

        foreach ($tarifs as $data) {
            $cout = new CoutReparation();
            $cout->setTypeElement($data['type_element']);
            $cout->setNom($data['nom']);
            $cout->setDescription($data['description']);
            $cout->setUnite($data['unite']);
            $cout->setPrixUnitaire($data['prix_unitaire']);
            $cout->setActif(true);
            $cout->setCreatedAt($now);
            $cout->setUpdatedAt($now);

            $manager->persist($cout);
        }

        $manager->flush();
    }
}
