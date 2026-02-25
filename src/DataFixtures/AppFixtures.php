<?php

namespace App\DataFixtures;

use App\Entity\Cle;
use App\Entity\Compteur;
use App\Entity\Element;
use App\Entity\EtatDesLieux;
use App\Entity\Logement;
use App\Entity\Photo;
use App\Entity\Piece;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // =====================================================
        // CRÉATION DES UTILISATEURS
        // =====================================================

        // Utilisateur de test principal
        $user1 = new User();
        $user1->setEmail('l.zerri@gmail.com');
        $user1->setName('Jean Dupont');
        $user1->setTelephone('0612345678');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password'));
        $user1->setCreatedAt(new \DateTimeImmutable());
        $user1->setUpdatedAt(new \DateTimeImmutable());
        $manager->persist($user1);

        // Deuxième utilisateur
        $user2 = new User();
        $user2->setEmail('marie@edlya.fr');
        $user2->setName('Marie Martin');
        $user2->setTelephone('0698765432');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password'));
        $user2->setCreatedAt(new \DateTimeImmutable());
        $user2->setUpdatedAt(new \DateTimeImmutable());
        $manager->persist($user2);

        $manager->flush();

        // =====================================================
        // CRÉATION DES 5 LOGEMENTS AVEC EDL ENTRÉE/SORTIE
        // =====================================================

        $this->createLogement1($manager, $user1);
        $this->createLogement2($manager, $user1);
        $this->createLogement3($manager, $user1);
        $this->createLogement4($manager, $user1);
        $this->createLogement5($manager, $user1);

        // Logements avec EDL variés
        $this->createLogement6($manager, $user1);
        $this->createLogement7($manager, $user1);
        $this->createLogement8($manager, $user1);

        // EDL brouillon complets
        $this->createLogement9Brouillon($manager, $user1);
        $this->createLogement10Brouillon($manager, $user1);

        // Paire entrée/sortie signées (comparatif complet)
        $this->createLogement11Comparatif($manager, $user1);

        $manager->flush();
    }

    /**
     * Logement 1: Studio Paris - Location courte (6 mois), peu de dégradations
     */
    private function createLogement1(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'Studio Bastille',
            'adresse' => '15 rue de la Roquette',
            'codePostal' => '75011',
            'ville' => 'Paris',
            'type' => 'studio',
            'surface' => 25.5,
            'nbPieces' => 1,
        ]);

        $dateEntree = new \DateTime('-6 months');
        $dateSortie = new \DateTime('-2 weeks');

        $edlEntree = $this->createEdl($manager, $logement, $user, 'entree', $dateEntree, [
            'locataireNom' => 'Sophie Durand',
            'locataireEmail' => 'sophie.durand@email.fr',
            'locataireTelephone' => '0601020304',
            'depotGarantie' => 550.00,
            'statut' => 'signe',
        ]);

        $edlSortie = $this->createEdl($manager, $logement, $user, 'sortie', $dateSortie, [
            'locataireNom' => 'Sophie Durand',
            'locataireEmail' => 'sophie.durand@email.fr',
            'locataireTelephone' => '0601020304',
            'depotGarantie' => 550.00,
            'statut' => 'termine',
        ]);

        // Pièces et éléments
        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Pièce principale', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => 'Légères traces'],
            ['nom' => 'Plafond', 'type' => 'plafond', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Radiateur', 'type' => 'chauffage', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Kitchenette', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Évier', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Plaque cuisson', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Réfrigérateur', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'usage'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Salle d\'eau', [
            ['nom' => 'Carrelage sol', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Douche', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Lavabo', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'WC', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        // Compteurs
        $this->createCompteurs($manager, $edlEntree, $edlSortie, [
            ['type' => 'electricite', 'numero' => 'EDF-001', 'indexE' => '12500', 'indexS' => '14200', 'commentaire' => 'Compteur Linky, sous le tableau électrique'],
        ]);

        // Clés
        $this->createCles($manager, $edlEntree, $edlSortie, [
            ['type' => 'porte_entree', 'nbE' => 2, 'nbS' => 2],
            ['type' => 'boite_lettres', 'nbE' => 1, 'nbS' => 1],
        ]);
    }

    /**
     * Logement 2: F3 Lyon - Location moyenne (2 ans), dégradations modérées
     */
    private function createLogement2(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'Appartement Croix-Rousse',
            'adresse' => '42 montée de la Grande Côte',
            'codePostal' => '69001',
            'ville' => 'Lyon',
            'type' => 'f3',
            'surface' => 68.0,
            'nbPieces' => 3,
        ]);

        $dateEntree = new \DateTime('-2 years');
        $dateSortie = new \DateTime('-3 weeks');

        $edlEntree = $this->createEdl($manager, $logement, $user, 'entree', $dateEntree, [
            'locataireNom' => 'Marc Leblanc',
            'locataireEmail' => 'marc.leblanc@email.fr',
            'locataireTelephone' => '0611223344',
            'depotGarantie' => 900.00,
            'statut' => 'signe',
        ]);

        $edlSortie = $this->createEdl($manager, $logement, $user, 'sortie', $dateSortie, [
            'locataireNom' => 'Marc Leblanc',
            'locataireEmail' => 'marc.leblanc@email.fr',
            'locataireTelephone' => '0611223344',
            'depotGarantie' => 900.00,
            'statut' => 'termine',
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Entrée', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Porte entrée', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Salon', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etatE' => 'tres_bon', 'etatS' => 'usage', 'obs' => 'Rayures légères'],
            ['nom' => 'Mur Nord', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Trous non rebouchés', 'deg' => ['Trou(s)', 'Impacts']],
            ['nom' => 'Mur Sud', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Plafond', 'type' => 'plafond', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Radiateur', 'type' => 'chauffage', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Cuisine', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Faïence', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => 'Joints à nettoyer'],
            ['nom' => 'Évier', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Robinet', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Fuite légère', 'deg' => ['Fuite', 'Calcaire']],
            ['nom' => 'Four', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Hotte', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => 'À nettoyer'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Chambre 1', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Placard', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Chambre 2', [
            ['nom' => 'Moquette', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Taches incrustées', 'deg' => ['Tache(s)', 'Usure']],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Salle de bain', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Baignoire', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Lavabo', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'WC', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createCompteurs($manager, $edlEntree, $edlSortie, [
            ['type' => 'electricite', 'numero' => 'EDF-002', 'indexE' => '35000', 'indexS' => '48500', 'commentaire' => 'Compteur dans le hall, 2e étage'],
            ['type' => 'gaz', 'numero' => 'GDF-002', 'indexE' => '1500', 'indexS' => '2800', 'commentaire' => 'Compteur en cave, local technique'],
            ['type' => 'eau_froide', 'numero' => 'EAU-002', 'indexE' => '250', 'indexS' => '420'],
        ]);

        $this->createCles($manager, $edlEntree, $edlSortie, [
            ['type' => 'porte_entree', 'nbE' => 3, 'nbS' => 2], // 1 manquante
            ['type' => 'boite_lettres', 'nbE' => 2, 'nbS' => 2],
            ['type' => 'cave', 'nbE' => 1, 'nbS' => 1],
        ]);
    }

    /**
     * Logement 3: Maison Bordeaux - Location longue (5 ans), nombreuses dégradations
     */
    private function createLogement3(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'Maison Chartrons',
            'adresse' => '8 rue Notre-Dame',
            'codePostal' => '33000',
            'ville' => 'Bordeaux',
            'type' => 'maison',
            'surface' => 120.0,
            'nbPieces' => 5,
        ]);

        $dateEntree = new \DateTime('-5 years');
        $dateSortie = new \DateTime('-1 month');

        $edlEntree = $this->createEdl($manager, $logement, $user, 'entree', $dateEntree, [
            'locataireNom' => 'Famille Moreau',
            'locataireEmail' => 'moreau.famille@email.fr',
            'locataireTelephone' => '0556789012',
            'depotGarantie' => 1800.00,
            'statut' => 'signe',
            'autresLocataires' => ['Pierre Moreau', 'Lucie Moreau'],
        ]);

        $edlSortie = $this->createEdl($manager, $logement, $user, 'sortie', $dateSortie, [
            'locataireNom' => 'Famille Moreau',
            'locataireEmail' => 'moreau.famille@email.fr',
            'locataireTelephone' => '0556789012',
            'depotGarantie' => 1800.00,
            'statut' => 'termine',
            'observations' => 'Nombreuses dégradations après 5 ans d\'occupation avec enfants.',
            'autresLocataires' => ['Pierre Moreau', 'Lucie Moreau'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Entrée', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Nombreuses traces, griffures', 'deg' => ['Griffures', 'Salissures']],
            ['nom' => 'Porte entrée', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'usage'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Salon', [
            ['nom' => 'Parquet massif', 'type' => 'sol', 'etatE' => 'tres_bon', 'etatS' => 'mauvais', 'obs' => 'Rayures profondes, taches', 'deg' => ['Rayure(s)', 'Tache(s)', 'Éclats']],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Trous, dessins d\'enfants', 'deg' => ['Trou(s)', 'Salissures', 'Griffures']],
            ['nom' => 'Plafond', 'type' => 'plafond', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Cheminée', 'type' => 'chauffage', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Vitre fissurée', 'deg' => ['Rouille']],
            ['nom' => 'Baie vitrée', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'usage'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Cuisine', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Plan de travail', 'type' => 'mobilier', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Brûlures, découpes', 'deg' => ['Rayé', 'Taché']],
            ['nom' => 'Évier', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Four', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'hors_service', 'obs' => 'Ne chauffe plus', 'deg' => ['Ne fonctionne pas']],
            ['nom' => 'Lave-vaisselle', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Fuit', 'deg' => ['Fuite']],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Chambre parentale', [
            ['nom' => 'Moquette', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Taches nombreuses', 'deg' => ['Tache(s)', 'Usure']],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Placard', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Porte cassée', 'deg' => ['Bois abîmé', 'Gonds défectueux']],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Chambre enfant 1', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Stickers, dessins', 'deg' => ['Salissures', 'Éclats de peinture']],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Chambre enfant 2', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Papier peint déchiré', 'deg' => ['Papier décollé']],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'usage'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Salle de bain', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Baignoire', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Émail écaillé', 'deg' => ['Calcaire', 'Joint défectueux']],
            ['nom' => 'Meuble vasque', 'type' => 'mobilier', 'etatE' => 'bon', 'etatS' => 'usage'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Jardin', [
            ['nom' => 'Terrasse', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Portail', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Rouillé, grince', 'deg' => ['Gonds défectueux', 'Peinture écaillée']],
            ['nom' => 'Clôture', 'type' => 'autre', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Planches cassées', 'deg' => ['Cassé']],
        ]);

        $this->createCompteurs($manager, $edlEntree, $edlSortie, [
            ['type' => 'electricite', 'numero' => 'EDF-003', 'indexE' => '45000', 'indexS' => '98000', 'commentaire' => 'Compteur Linky, entrée garage'],
            ['type' => 'gaz', 'numero' => 'GDF-003', 'indexE' => '3000', 'indexS' => '8500', 'commentaire' => 'Compteur extérieur, façade nord'],
            ['type' => 'eau_froide', 'numero' => 'EAU-003', 'indexE' => '500', 'indexS' => '1850', 'commentaire' => 'Regard dans le jardin'],
        ]);

        $this->createCles($manager, $edlEntree, $edlSortie, [
            ['type' => 'porte_entree', 'nbE' => 4, 'nbS' => 3], // 1 manquante
            ['type' => 'garage', 'nbE' => 2, 'nbS' => 1], // 1 manquante
            ['type' => 'portail', 'nbE' => 2, 'nbS' => 2],
            ['type' => 'boite_lettres', 'nbE' => 1, 'nbS' => 0], // 1 manquante
        ]);
    }

    /**
     * Logement 4: F2 Marseille - Location 1 an, état correct
     */
    private function createLogement4(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'Appartement Vieux-Port',
            'adresse' => '25 quai du Port',
            'codePostal' => '13002',
            'ville' => 'Marseille',
            'type' => 'f2',
            'surface' => 45.0,
            'nbPieces' => 2,
        ]);

        $dateEntree = new \DateTime('-1 year');
        $dateSortie = new \DateTime('-2 weeks');

        $edlEntree = $this->createEdl($manager, $logement, $user, 'entree', $dateEntree, [
            'locataireNom' => 'Claire Petit',
            'locataireEmail' => 'claire.petit@email.fr',
            'locataireTelephone' => '0491234567',
            'depotGarantie' => 750.00,
            'statut' => 'signe',
        ]);

        $edlSortie = $this->createEdl($manager, $logement, $user, 'sortie', $dateSortie, [
            'locataireNom' => 'Claire Petit',
            'locataireEmail' => 'claire.petit@email.fr',
            'locataireTelephone' => '0491234567',
            'depotGarantie' => 750.00,
            'statut' => 'termine',
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Séjour', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Plafond', 'type' => 'plafond', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Climatisation', 'type' => 'chauffage', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => 'Filtre à changer'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Cuisine américaine', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Plan de travail', 'type' => 'mobilier', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Évier', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Plaque induction', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Chambre', [
            ['nom' => 'Parquet flottant', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Placard', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Salle d\'eau', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Douche italienne', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Vasque', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'WC', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createCompteurs($manager, $edlEntree, $edlSortie, [
            ['type' => 'electricite', 'numero' => 'EDF-004', 'indexE' => '22000', 'indexS' => '28500', 'commentaire' => 'Compteur dans placard entrée'],
            ['type' => 'eau_froide', 'numero' => 'EAU-004', 'indexE' => '180', 'indexS' => '245'],
        ]);

        $this->createCles($manager, $edlEntree, $edlSortie, [
            ['type' => 'porte_entree', 'nbE' => 2, 'nbS' => 2],
            ['type' => 'boite_lettres', 'nbE' => 1, 'nbS' => 1],
            ['type' => 'badge', 'nbE' => 1, 'nbS' => 1],
        ]);
    }

    /**
     * Logement 5: Loft Nantes - Location 3 ans, dégradations variées
     */
    private function createLogement5(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'Loft Île de Nantes',
            'adresse' => '12 boulevard de la Prairie au Duc',
            'codePostal' => '44200',
            'ville' => 'Nantes',
            'type' => 'loft',
            'surface' => 95.0,
            'nbPieces' => 3,
        ]);

        $dateEntree = new \DateTime('-3 years');
        $dateSortie = new \DateTime('-1 week');

        $edlEntree = $this->createEdl($manager, $logement, $user, 'entree', $dateEntree, [
            'locataireNom' => 'Thomas Roux',
            'locataireEmail' => 'thomas.roux@email.fr',
            'locataireTelephone' => '0240567890',
            'depotGarantie' => 1200.00,
            'statut' => 'signe',
            'autresLocataires' => ['Julie Roux'],
        ]);

        $edlSortie = $this->createEdl($manager, $logement, $user, 'sortie', $dateSortie, [
            'locataireNom' => 'Thomas Roux',
            'locataireEmail' => 'thomas.roux@email.fr',
            'locataireTelephone' => '0240567890',
            'depotGarantie' => 1200.00,
            'statut' => 'termine',
            'autresLocataires' => ['Julie Roux'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Espace de vie', [
            ['nom' => 'Béton ciré', 'type' => 'sol', 'etatE' => 'tres_bon', 'etatS' => 'usage', 'obs' => 'Micro-fissures'],
            ['nom' => 'Murs briques', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Verrière', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Carreau fêlé', 'deg' => ['Vitre fêlée']],
            ['nom' => 'Radiateur design', 'type' => 'chauffage', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Spots encastrés', 'type' => 'electricite', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => '2 ampoules HS'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Cuisine ouverte', [
            ['nom' => 'Béton ciré', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Îlot central', 'type' => 'mobilier', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Évier inox', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Four vapeur', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Réfrigérateur américain', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Distributeur glaçons HS', 'deg' => ['Ne fonctionne pas']],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Mezzanine chambre', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Garde-corps', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Velux', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => 'Joint à remplacer'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Salle de bain', [
            ['nom' => 'Carrelage grand format', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Douche à l\'italienne', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => 'Joints noircis'],
            ['nom' => 'Double vasque', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Sèche-serviettes', 'type' => 'chauffage', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Miroir éclairant', 'type' => 'electricite', 'etatE' => 'bon', 'etatS' => 'hors_service', 'obs' => 'Éclairage ne fonctionne plus', 'deg' => ['Ne fonctionne pas']],
        ]);

        $this->createCompteurs($manager, $edlEntree, $edlSortie, [
            ['type' => 'electricite', 'numero' => 'EDF-005', 'indexE' => '55000', 'indexS' => '78000', 'commentaire' => 'Compteur Linky, tableau principal'],
            ['type' => 'eau_froide', 'numero' => 'EAU-005', 'indexE' => '350', 'indexS' => '620', 'commentaire' => 'Sous-sol, local technique'],
            ['type' => 'eau_chaude', 'numero' => 'EAU-005B', 'indexE' => '200', 'indexS' => '380'],
        ]);

        $this->createCles($manager, $edlEntree, $edlSortie, [
            ['type' => 'porte_entree', 'nbE' => 3, 'nbS' => 3],
            ['type' => 'badge', 'nbE' => 2, 'nbS' => 1], // 1 manquant
            ['type' => 'telecommande', 'nbE' => 1, 'nbS' => 1],
            ['type' => 'local_velo', 'nbE' => 1, 'nbS' => 1],
        ]);
    }

    // =====================================================
    // LOGEMENTS SANS EDL (dashboard)
    // =====================================================

    /**
     * Logement 6: Studio Toulouse - EDL entrée en cours
     */
    private function createLogement6(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'Studio Capitole',
            'adresse' => '3 place du Capitole',
            'codePostal' => '31000',
            'ville' => 'Toulouse',
            'type' => 'studio',
            'surface' => 22.0,
            'nbPieces' => 1,
        ]);

        $edl = $this->createEdlSingle($manager, $logement, $user, 'entree', new \DateTime('-2 weeks'), [
            'locataireNom' => 'Lucas Berger',
            'locataireEmail' => 'lucas.berger@email.fr',
            'locataireTelephone' => '0561234567',
            'depotGarantie' => 450.00,
            'statut' => 'en_cours',
        ]);

        $this->createPieceSingle($manager, $edl, 'Pièce principale', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etat' => 'bon'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Kitchenette', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'usage', 'obs' => 'Joints noircis', 'deg' => ['Joints abîmés']],
            ['nom' => 'Évier', 'type' => 'plomberie', 'etat' => 'mauvais', 'obs' => 'Fuite sous le siphon', 'deg' => ['Fuite', 'Calcaire']],
            ['nom' => 'Plaque cuisson', 'type' => 'electromenager', 'etat' => 'bon'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Salle d\'eau', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Douche', 'type' => 'plomberie', 'etat' => 'usage', 'obs' => 'Joints à refaire', 'deg' => ['Joint défectueux']],
            ['nom' => 'Lavabo', 'type' => 'plomberie', 'etat' => 'bon'],
        ]);

        $this->createCompteursSingle($manager, $edl, [
            ['type' => 'electricite', 'numero' => 'EDF-006', 'index' => '5400'],
        ]);

        $this->createClesSingle($manager, $edl, [
            ['type' => 'porte_entree', 'nb' => 2],
            ['type' => 'boite_lettres', 'nb' => 1],
        ]);
    }

    /**
     * Logement 7: F2 Strasbourg - EDL entrée signé (locataire en place)
     */
    private function createLogement7(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'F2 Petite France',
            'adresse' => '18 rue du Bain aux Plantes',
            'codePostal' => '67000',
            'ville' => 'Strasbourg',
            'type' => 'f2',
            'surface' => 42.0,
            'nbPieces' => 2,
        ]);

        $edl = $this->createEdlSingle($manager, $logement, $user, 'entree', new \DateTime('-8 months'), [
            'locataireNom' => 'Anna Weber',
            'locataireEmail' => 'anna.weber@email.fr',
            'locataireTelephone' => '0388654321',
            'depotGarantie' => 620.00,
            'statut' => 'signe',
        ]);

        $this->createPieceSingle($manager, $edl, 'Séjour', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
            ['nom' => 'Plafond', 'type' => 'plafond', 'etat' => 'bon'],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etat' => 'bon'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Chambre', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
            ['nom' => 'Placard', 'type' => 'menuiserie', 'etat' => 'usage'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Cuisine', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Évier', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'Réfrigérateur', 'type' => 'electromenager', 'etat' => 'bon'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Salle de bain', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Baignoire', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'Lavabo', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'WC', 'type' => 'plomberie', 'etat' => 'bon'],
        ]);

        $this->createCompteursSingle($manager, $edl, [
            ['type' => 'electricite', 'numero' => 'EDF-007', 'index' => '15200'],
            ['type' => 'eau_froide', 'numero' => 'EAU-007', 'index' => '98'],
        ]);

        $this->createClesSingle($manager, $edl, [
            ['type' => 'porte_entree', 'nb' => 2],
            ['type' => 'boite_lettres', 'nb' => 1],
            ['type' => 'interphone', 'nb' => 1],
        ]);
    }

    /**
     * Logement 8: F3 Nice - EDL entrée terminé (prêt pour signature)
     */
    private function createLogement8(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'Appartement Promenade',
            'adresse' => '55 promenade des Anglais',
            'codePostal' => '06000',
            'ville' => 'Nice',
            'type' => 'f3',
            'surface' => 72.0,
            'nbPieces' => 3,
        ]);

        $edl = $this->createEdlSingle($manager, $logement, $user, 'entree', new \DateTime('-3 days'), [
            'locataireNom' => 'Pierre Rossi',
            'locataireEmail' => 'pierre.rossi@email.fr',
            'locataireTelephone' => '0493112233',
            'depotGarantie' => 950.00,
            'statut' => 'termine',
        ]);

        $this->createPieceSingle($manager, $edl, 'Séjour', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'tres_bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
            ['nom' => 'Plafond', 'type' => 'plafond', 'etat' => 'bon'],
            ['nom' => 'Baie vitrée', 'type' => 'menuiserie', 'etat' => 'bon'],
            ['nom' => 'Climatisation', 'type' => 'chauffage', 'etat' => 'bon'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Cuisine', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Plan de travail', 'type' => 'mobilier', 'etat' => 'bon'],
            ['nom' => 'Évier', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'Four', 'type' => 'electromenager', 'etat' => 'bon'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Chambre 1', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
            ['nom' => 'Placard', 'type' => 'menuiserie', 'etat' => 'bon'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Chambre 2', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Salle de bain', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Douche italienne', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'Vasque', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'WC', 'type' => 'plomberie', 'etat' => 'bon'],
        ]);

        $this->createCompteursSingle($manager, $edl, [
            ['type' => 'electricite', 'numero' => 'EDF-008', 'index' => '31000'],
            ['type' => 'eau_froide', 'numero' => 'EAU-008', 'index' => '210'],
            ['type' => 'eau_chaude', 'numero' => 'EAU-008B', 'index' => '125'],
        ]);

        $this->createClesSingle($manager, $edl, [
            ['type' => 'porte_entree', 'nb' => 3],
            ['type' => 'boite_lettres', 'nb' => 1],
            ['type' => 'badge', 'nb' => 2],
        ]);
    }

    // =====================================================
    // EDL BROUILLON COMPLETS
    // =====================================================

    /**
     * Logement 9: F2 Montpellier - EDL entrée brouillon complet
     */
    private function createLogement9Brouillon(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'Appartement Antigone',
            'adresse' => '10 place du Nombre d\'Or',
            'codePostal' => '34000',
            'ville' => 'Montpellier',
            'type' => 'f2',
            'surface' => 48.0,
            'nbPieces' => 2,
        ]);

        $edl = $this->createEdlSingle($manager, $logement, $user, 'entree', new \DateTime(), [
            'locataireNom' => 'Julien Fabre',
            'locataireEmail' => 'julien.fabre@email.fr',
            'locataireTelephone' => '0467123456',
            'depotGarantie' => 680.00,
            'statut' => 'brouillon',
        ]);

        $this->createPieceSingle($manager, $edl, 'Séjour', [
            ['nom' => 'Parquet stratifié', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
            ['nom' => 'Plafond', 'type' => 'plafond', 'etat' => 'bon'],
            ['nom' => 'Fenêtre double vitrage', 'type' => 'menuiserie', 'etat' => 'bon'],
            ['nom' => 'Radiateur', 'type' => 'chauffage', 'etat' => 'bon'],
            ['nom' => 'Prises électriques', 'type' => 'electricite', 'etat' => 'bon'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Chambre', [
            ['nom' => 'Moquette', 'type' => 'sol', 'etat' => 'usage', 'obs' => 'Légère usure normale'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
            ['nom' => 'Placard coulissant', 'type' => 'menuiserie', 'etat' => 'bon'],
            ['nom' => 'Volets roulants', 'type' => 'menuiserie', 'etat' => 'bon'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Cuisine', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Faïence murale', 'type' => 'mur', 'etat' => 'bon'],
            ['nom' => 'Évier inox', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'Plaque vitrocéramique', 'type' => 'electromenager', 'etat' => 'bon'],
            ['nom' => 'Hotte aspirante', 'type' => 'electromenager', 'etat' => 'usage'],
        ]);

        $this->createPieceSingle($manager, $edl, 'Salle de bain', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Douche', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'Lavabo', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'WC', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'Miroir', 'type' => 'equipement', 'etat' => 'bon'],
        ]);

        $this->createCompteursSingle($manager, $edl, [
            ['type' => 'electricite', 'numero' => 'EDF-009', 'index' => '8200'],
            ['type' => 'eau_froide', 'numero' => 'EAU-009', 'index' => '120'],
        ]);

        $this->createClesSingle($manager, $edl, [
            ['type' => 'porte_entree', 'nb' => 3],
            ['type' => 'boite_lettres', 'nb' => 1],
            ['type' => 'cave', 'nb' => 1],
        ]);
    }

    /**
     * Logement 10: F4 Lille - EDL sortie brouillon complet avec dégradations
     */
    private function createLogement10Brouillon(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'Maison Vieux-Lille',
            'adresse' => '7 rue de la Monnaie',
            'codePostal' => '59000',
            'ville' => 'Lille',
            'type' => 'f4',
            'surface' => 95.0,
            'nbPieces' => 4,
        ]);

        // EDL entrée signé (référence)
        $edlEntree = $this->createEdlSingle($manager, $logement, $user, 'entree', new \DateTime('-3 years'), [
            'locataireNom' => 'Famille Lemaire',
            'locataireEmail' => 'lemaire@email.fr',
            'locataireTelephone' => '0320456789',
            'depotGarantie' => 1400.00,
            'statut' => 'signe',
            'autresLocataires' => ['Nicolas Lemaire'],
        ]);

        $this->createPieceSingle($manager, $edlEntree, 'Entrée', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
        ]);
        $this->createPieceSingle($manager, $edlEntree, 'Salon', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'tres_bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
            ['nom' => 'Cheminée', 'type' => 'chauffage', 'etat' => 'bon'],
        ]);
        $this->createPieceSingle($manager, $edlEntree, 'Cuisine équipée', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Plan de travail', 'type' => 'mobilier', 'etat' => 'bon'],
            ['nom' => 'Four', 'type' => 'electromenager', 'etat' => 'bon'],
            ['nom' => 'Lave-vaisselle', 'type' => 'electromenager', 'etat' => 'bon'],
        ]);
        $this->createPieceSingle($manager, $edlEntree, 'Chambre 1', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
            ['nom' => 'Placard', 'type' => 'menuiserie', 'etat' => 'bon'],
        ]);
        $this->createPieceSingle($manager, $edlEntree, 'Chambre 2', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'bon'],
        ]);
        $this->createPieceSingle($manager, $edlEntree, 'Salle de bain', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Baignoire', 'type' => 'plomberie', 'etat' => 'bon'],
            ['nom' => 'Lavabo', 'type' => 'plomberie', 'etat' => 'bon'],
        ]);

        $this->createCompteursSingle($manager, $edlEntree, [
            ['type' => 'electricite', 'numero' => 'EDF-010', 'index' => '42000'],
            ['type' => 'gaz', 'numero' => 'GDF-010', 'index' => '2100'],
            ['type' => 'eau_froide', 'numero' => 'EAU-010', 'index' => '310'],
        ]);
        $this->createClesSingle($manager, $edlEntree, [
            ['type' => 'porte_entree', 'nb' => 3],
            ['type' => 'boite_lettres', 'nb' => 2],
            ['type' => 'cave', 'nb' => 1],
            ['type' => 'garage', 'nb' => 1],
        ]);

        // EDL sortie brouillon avec dégradations
        $edlSortie = $this->createEdlSingle($manager, $logement, $user, 'sortie', new \DateTime(), [
            'locataireNom' => 'Famille Lemaire',
            'locataireEmail' => 'lemaire@email.fr',
            'locataireTelephone' => '0320456789',
            'depotGarantie' => 1400.00,
            'statut' => 'brouillon',
            'observations' => 'Logement rendu en état moyen après 3 ans.',
            'autresLocataires' => ['Nicolas Lemaire'],
        ]);

        $this->createPieceSingle($manager, $edlSortie, 'Entrée', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'usage'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'mauvais', 'obs' => 'Traces de chocs, peinture écaillée', 'deg' => ['Impacts', 'Éclats de peinture']],
        ]);
        $this->createPieceSingle($manager, $edlSortie, 'Salon', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'usage', 'obs' => 'Rayures visibles'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'mauvais', 'obs' => 'Trous de chevilles non rebouchés', 'deg' => ['Trou(s)']],
            ['nom' => 'Cheminée', 'type' => 'chauffage', 'etat' => 'usage'],
        ]);
        $this->createPieceSingle($manager, $edlSortie, 'Cuisine équipée', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Plan de travail', 'type' => 'mobilier', 'etat' => 'mauvais', 'obs' => 'Brûlure de casserole', 'deg' => ['Taché', 'Rayé']],
            ['nom' => 'Four', 'type' => 'electromenager', 'etat' => 'usage', 'obs' => 'Sale, porte difficile'],
            ['nom' => 'Lave-vaisselle', 'type' => 'electromenager', 'etat' => 'hors_service', 'obs' => 'Ne démarre plus', 'deg' => ['Ne fonctionne pas']],
        ]);
        $this->createPieceSingle($manager, $edlSortie, 'Chambre 1', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'usage'],
            ['nom' => 'Placard', 'type' => 'menuiserie', 'etat' => 'mauvais', 'obs' => 'Rail cassé', 'deg' => ['Fermeture défectueuse']],
        ]);
        $this->createPieceSingle($manager, $edlSortie, 'Chambre 2', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etat' => 'usage'],
            ['nom' => 'Murs', 'type' => 'mur', 'etat' => 'mauvais', 'obs' => 'Papier peint déchiré', 'deg' => ['Papier décollé']],
        ]);
        $this->createPieceSingle($manager, $edlSortie, 'Salle de bain', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etat' => 'bon'],
            ['nom' => 'Baignoire', 'type' => 'plomberie', 'etat' => 'mauvais', 'obs' => 'Émail écaillé, joints noirs', 'deg' => ['Joint défectueux', 'Calcaire']],
            ['nom' => 'Lavabo', 'type' => 'plomberie', 'etat' => 'usage'],
        ]);

        $this->createCompteursSingle($manager, $edlSortie, [
            ['type' => 'electricite', 'numero' => 'EDF-010', 'index' => '61500'],
            ['type' => 'gaz', 'numero' => 'GDF-010', 'index' => '4800'],
            ['type' => 'eau_froide', 'numero' => 'EAU-010', 'index' => '580'],
        ]);
        $this->createClesSingle($manager, $edlSortie, [
            ['type' => 'porte_entree', 'nb' => 2, 'commentaire' => '1 clé perdue'],
            ['type' => 'boite_lettres', 'nb' => 2],
            ['type' => 'cave', 'nb' => 1],
            ['type' => 'garage', 'nb' => 0, 'commentaire' => 'Clé non restituée'],
        ]);
    }

    // =====================================================
    // PAIRE ENTRÉE/SORTIE SIGNÉES (comparatif complet)
    // =====================================================

    /**
     * Logement 11: F3 Rennes - Entrée signée + Sortie signée (comparatif prêt)
     */
    private function createLogement11Comparatif(ObjectManager $manager, User $user): void
    {
        $logement = $this->createLogementEntity($manager, $user, [
            'nom' => 'Appartement Place de Bretagne',
            'adresse' => '22 place de Bretagne',
            'codePostal' => '35000',
            'ville' => 'Rennes',
            'type' => 'f3',
            'surface' => 65.0,
            'nbPieces' => 3,
        ]);

        $dateEntree = new \DateTime('-18 months');
        $dateSortie = new \DateTime('-1 week');

        $edlEntree = $this->createEdl($manager, $logement, $user, 'entree', $dateEntree, [
            'locataireNom' => 'Émilie Garnier',
            'locataireEmail' => 'emilie.garnier@email.fr',
            'locataireTelephone' => '0299112233',
            'depotGarantie' => 850.00,
            'statut' => 'signe',
        ]);

        $edlSortie = $this->createEdl($manager, $logement, $user, 'sortie', $dateSortie, [
            'locataireNom' => 'Émilie Garnier',
            'locataireEmail' => 'emilie.garnier@email.fr',
            'locataireTelephone' => '0299112233',
            'depotGarantie' => 850.00,
            'statut' => 'signe',
        ]);

        // Pièces avec dégradations variées
        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Séjour', [
            ['nom' => 'Parquet chêne', 'type' => 'sol', 'etatE' => 'tres_bon', 'etatS' => 'usage', 'obs' => 'Rayures légères sous les meubles'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => 'Traces d\'accrochage'],
            ['nom' => 'Plafond', 'type' => 'plafond', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Baie vitrée', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Radiateur', 'type' => 'chauffage', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Cuisine', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Crédence', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => 'Traces de graisse'],
            ['nom' => 'Évier', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Plaque induction', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Four', 'type' => 'electromenager', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => 'Nécessite nettoyage'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Chambre 1', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'mauvais', 'obs' => 'Trou dans le placo', 'deg' => ['Trou(s)', 'Impacts']],
            ['nom' => 'Placard', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Chambre 2', [
            ['nom' => 'Parquet', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'usage'],
            ['nom' => 'Murs', 'type' => 'mur', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Fenêtre', 'type' => 'menuiserie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createPieceWithElements($manager, $edlEntree, $edlSortie, 'Salle de bain', [
            ['nom' => 'Carrelage', 'type' => 'sol', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'Douche', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'usage', 'obs' => 'Joints à refaire'],
            ['nom' => 'Lavabo', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
            ['nom' => 'WC', 'type' => 'plomberie', 'etatE' => 'bon', 'etatS' => 'bon'],
        ]);

        $this->createCompteurs($manager, $edlEntree, $edlSortie, [
            ['type' => 'electricite', 'numero' => 'EDF-011', 'indexE' => '18000', 'indexS' => '26400', 'commentaire' => 'Compteur Linky'],
            ['type' => 'eau_froide', 'numero' => 'EAU-011', 'indexE' => '145', 'indexS' => '232'],
            ['type' => 'eau_chaude', 'numero' => 'EAU-011B', 'indexE' => '90', 'indexS' => '155'],
        ]);

        $this->createCles($manager, $edlEntree, $edlSortie, [
            ['type' => 'porte_entree', 'nbE' => 3, 'nbS' => 3],
            ['type' => 'boite_lettres', 'nbE' => 1, 'nbS' => 1],
            ['type' => 'badge', 'nbE' => 2, 'nbS' => 1], // 1 manquant
            ['type' => 'cave', 'nbE' => 1, 'nbS' => 1],
        ]);
    }

    // =====================================================
    // MÉTHODES UTILITAIRES
    // =====================================================

    private function createLogementEntity(ObjectManager $manager, User $user, array $data): Logement
    {
        $logement = new Logement();
        $logement->setUser($user);
        $logement->setNom($data['nom']);
        $logement->setAdresse($data['adresse']);
        $logement->setCodePostal($data['codePostal']);
        $logement->setVille($data['ville']);
        $logement->setType($data['type']);
        $logement->setSurface($data['surface']);
        $logement->setNbPieces($data['nbPieces']);
        $logement->setCreatedAt(new \DateTimeImmutable());
        $logement->setUpdatedAt(new \DateTimeImmutable());
        $manager->persist($logement);
        return $logement;
    }

    private function createEdl(ObjectManager $manager, Logement $logement, User $user, string $type, \DateTime $date, array $data): EtatDesLieux
    {
        $edl = new EtatDesLieux();
        $edl->setLogement($logement);
        $edl->setUser($user);
        $edl->setType($type);
        $edl->setDateRealisation($date);
        $edl->setLocataireNom($data['locataireNom']);
        $edl->setLocataireEmail($data['locataireEmail']);
        $edl->setLocataireTelephone($data['locataireTelephone']);
        $edl->setDepotGarantie($data['depotGarantie']);
        $edl->setStatut($data['statut']);

        if (isset($data['observations'])) {
            $edl->setObservationsGenerales($data['observations']);
        }

        if (isset($data['autresLocataires'])) {
            $edl->setAutresLocataires($data['autresLocataires']);
        }

        if ($data['statut'] === 'signe') {
            $edl->setSignatureBailleur('data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=');
            $edl->setSignatureLocataire('data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=');
            $edl->setDateSignatureBailleur($date);
            $edl->setDateSignatureLocataire($date);
        }

        $edl->setCreatedAt(\DateTimeImmutable::createFromMutable($date));
        $edl->setUpdatedAt(\DateTimeImmutable::createFromMutable($date));
        $manager->persist($edl);
        return $edl;
    }

    private function createPieceWithElements(ObjectManager $manager, EtatDesLieux $edlEntree, EtatDesLieux $edlSortie, string $nomPiece, array $elements): void
    {
        static $ordre = 0;

        // Pièce entrée
        $pieceEntree = new Piece();
        $pieceEntree->setEtatDesLieux($edlEntree);
        $pieceEntree->setNom($nomPiece);
        $pieceEntree->setOrdre($ordre);
        $pieceEntree->setCreatedAt(new \DateTimeImmutable());
        $pieceEntree->setUpdatedAt(new \DateTimeImmutable());
        $manager->persist($pieceEntree);

        // Pièce sortie
        $pieceSortie = new Piece();
        $pieceSortie->setEtatDesLieux($edlSortie);
        $pieceSortie->setNom($nomPiece);
        $pieceSortie->setOrdre($ordre);
        $pieceSortie->setCreatedAt(new \DateTimeImmutable());
        $pieceSortie->setUpdatedAt(new \DateTimeImmutable());
        $manager->persist($pieceSortie);

        $ordreEl = 0;
        foreach ($elements as $el) {
            // Élément entrée
            $elementEntree = new Element();
            $elementEntree->setPiece($pieceEntree);
            $elementEntree->setNom($el['nom']);
            $elementEntree->setType($el['type']);
            $elementEntree->setEtat($el['etatE']);
            $elementEntree->setOrdre($ordreEl);
            $elementEntree->setCreatedAt(new \DateTimeImmutable());
            $elementEntree->setUpdatedAt(new \DateTimeImmutable());
            $manager->persist($elementEntree);

            // Élément sortie
            $elementSortie = new Element();
            $elementSortie->setPiece($pieceSortie);
            $elementSortie->setNom($el['nom']);
            $elementSortie->setType($el['type']);
            $elementSortie->setEtat($el['etatS']);
            $elementSortie->setOrdre($ordreEl);
            if (isset($el['obs'])) {
                $elementSortie->setObservations($el['obs']);
            }
            if (isset($el['deg'])) {
                $elementSortie->setDegradations($el['deg']);
            }
            $elementSortie->setCreatedAt(new \DateTimeImmutable());
            $elementSortie->setUpdatedAt(new \DateTimeImmutable());
            $manager->persist($elementSortie);

            $ordreEl++;
        }

        $ordre++;
    }

    private function createCompteurs(ObjectManager $manager, EtatDesLieux $edlEntree, EtatDesLieux $edlSortie, array $compteurs): void
    {
        foreach ($compteurs as $c) {
            $commentaire = $c['commentaire'] ?? null;

            // Compteur entrée
            $compteurEntree = new Compteur();
            $compteurEntree->setEtatDesLieux($edlEntree);
            $compteurEntree->setType($c['type']);
            $compteurEntree->setNumero($c['numero']);
            $compteurEntree->setIndexValue($c['indexE']);
            $compteurEntree->setCommentaire($commentaire);
            $compteurEntree->setCreatedAt(new \DateTimeImmutable());
            $compteurEntree->setUpdatedAt(new \DateTimeImmutable());
            $manager->persist($compteurEntree);

            // Compteur sortie
            $compteurSortie = new Compteur();
            $compteurSortie->setEtatDesLieux($edlSortie);
            $compteurSortie->setType($c['type']);
            $compteurSortie->setNumero($c['numero']);
            $compteurSortie->setIndexValue($c['indexS']);
            $compteurSortie->setCommentaire($commentaire);
            $compteurSortie->setCreatedAt(new \DateTimeImmutable());
            $compteurSortie->setUpdatedAt(new \DateTimeImmutable());
            $manager->persist($compteurSortie);
        }
    }

    private function createEdlSingle(ObjectManager $manager, Logement $logement, User $user, string $type, \DateTime $date, array $data): EtatDesLieux
    {
        return $this->createEdl($manager, $logement, $user, $type, $date, $data);
    }

    private function createPieceSingle(ObjectManager $manager, EtatDesLieux $edl, string $nomPiece, array $elements): void
    {
        static $ordreSingle = 100;

        $piece = new Piece();
        $piece->setEtatDesLieux($edl);
        $piece->setNom($nomPiece);
        $piece->setOrdre($ordreSingle);
        $piece->setCreatedAt(new \DateTimeImmutable());
        $piece->setUpdatedAt(new \DateTimeImmutable());
        $manager->persist($piece);

        $ordreEl = 0;
        foreach ($elements as $el) {
            $element = new Element();
            $element->setPiece($piece);
            $element->setNom($el['nom']);
            $element->setType($el['type']);
            $element->setEtat($el['etat']);
            $element->setOrdre($ordreEl);
            if (isset($el['obs'])) {
                $element->setObservations($el['obs']);
            }
            if (isset($el['deg'])) {
                $element->setDegradations($el['deg']);
            }
            $element->setCreatedAt(new \DateTimeImmutable());
            $element->setUpdatedAt(new \DateTimeImmutable());
            $manager->persist($element);
            $ordreEl++;
        }

        $ordreSingle++;
    }

    private function createCompteursSingle(ObjectManager $manager, EtatDesLieux $edl, array $compteurs): void
    {
        foreach ($compteurs as $c) {
            $compteur = new Compteur();
            $compteur->setEtatDesLieux($edl);
            $compteur->setType($c['type']);
            $compteur->setNumero($c['numero']);
            $compteur->setIndexValue($c['index']);
            $compteur->setCreatedAt(new \DateTimeImmutable());
            $compteur->setUpdatedAt(new \DateTimeImmutable());
            $manager->persist($compteur);
        }
    }

    private function createClesSingle(ObjectManager $manager, EtatDesLieux $edl, array $cles): void
    {
        foreach ($cles as $c) {
            $cle = new Cle();
            $cle->setEtatDesLieux($edl);
            $cle->setType($c['type']);
            $cle->setNombre($c['nb']);
            if (isset($c['commentaire'])) {
                $cle->setCommentaire($c['commentaire']);
            }
            $cle->setCreatedAt(new \DateTimeImmutable());
            $cle->setUpdatedAt(new \DateTimeImmutable());
            $manager->persist($cle);
        }
    }

    private function createCles(ObjectManager $manager, EtatDesLieux $edlEntree, EtatDesLieux $edlSortie, array $cles): void
    {
        foreach ($cles as $c) {
            // Clé entrée
            $cleEntree = new Cle();
            $cleEntree->setEtatDesLieux($edlEntree);
            $cleEntree->setType($c['type']);
            $cleEntree->setNombre($c['nbE']);
            $cleEntree->setCreatedAt(new \DateTimeImmutable());
            $cleEntree->setUpdatedAt(new \DateTimeImmutable());
            $manager->persist($cleEntree);

            // Clé sortie
            $cleSortie = new Cle();
            $cleSortie->setEtatDesLieux($edlSortie);
            $cleSortie->setType($c['type']);
            $cleSortie->setNombre($c['nbS']);
            if ($c['nbS'] < $c['nbE']) {
                $cleSortie->setCommentaire('Clé(s) non restituée(s)');
            }
            $cleSortie->setCreatedAt(new \DateTimeImmutable());
            $cleSortie->setUpdatedAt(new \DateTimeImmutable());
            $manager->persist($cleSortie);
        }
    }
}
