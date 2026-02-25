# CLAUDE.md - Edlya API

## 📋 Description du Projet

**Edlya** est une application de gestion d'états des lieux immobiliers. Ce repository contient l'API backend développée avec Symfony 7 et API Platform, exposant une API GraphQL.

Une application mobile React Native consommera cette API (à développer).

---

## 🏗️ Stack Technique

- **Framework** : Symfony 7.4
- **API** : API Platform avec GraphQL
- **Base de données** : MySQL
- **Authentification** : JWT (lexik/jwt-authentication-bundle)
- **PHP** : 8.2+

---

## 📁 Structure du Projet
```
edlya-api/
├── config/
│   ├── packages/
│   │   ├── api_platform.yaml    # Config API Platform + GraphQL
│   │   ├── security.yaml        # Config sécurité + JWT
│   │   └── lexik_jwt_authentication.yaml
│   ├── routes/
│   │   └── security.yaml        # Route /api/login
│   └── services.yaml
├── src/
│   ├── Controller/
│   │   └── AuthController.php   # /api/register, /api/me
│   ├── Doctrine/
│   │   └── CurrentUserExtension.php  # Filtre par utilisateur connecté
│   ├── Entity/
│   │   ├── User.php
│   │   ├── Logement.php
│   │   ├── EtatDesLieux.php
│   │   ├── Piece.php
│   │   ├── Element.php
│   │   ├── Photo.php
│   │   ├── Compteur.php
│   │   └── Cle.php
│   ├── Repository/
│   └── State/
│       └── UserAssignProcessor.php  # Assigne user auto à la création
└── migrations/
```

---

## 🗄️ Modèle de Données
```
User
├── Logement (1-N)
│   └── EtatDesLieux (1-N)
│       ├── Piece (1-N)
│       │   └── Element (1-N)
│       │       └── Photo (1-N)
│       ├── Compteur (1-N)
│       └── Cle (1-N)
```

### Types et Statuts

**EtatDesLieux.type** : `entree`, `sortie`

**EtatDesLieux.statut** : `brouillon`, `en_cours`, `termine`, `signe`

**Element.type** : `sol`, `mur`, `plafond`, `menuiserie`, `electricite`, `plomberie`, `chauffage`, `equipement`, `mobilier`, `electromenager`, `autre`

**Element.etat** : `neuf`, `tres_bon`, `bon`, `usage`, `mauvais`, `hors_service`

**Compteur.type** : `electricite`, `eau_froide`, `eau_chaude`, `gaz`

**Cle.type** : `porte_entree`, `boite_lettres`, `cave`, `garage`, `parking`, `local_velo`, `portail`, `interphone`, `badge`, `telecommande`, `autre`

---

## 🔐 Authentification

### Endpoints REST

- `POST /api/register` - Inscription (public)
- `POST /api/login` - Connexion, retourne JWT (public)
- `GET /api/me` - Infos utilisateur connecté (authentifié)

### Headers
```
Authorization: Bearer <token_jwt>
```

### Comptes de test (DataFixtures)
| Email | Mot de passe |
|-------|--------------|
| l.zerri@gmail.com | password |
| marie@edlya.fr | password |

### Upload de photos
**Photos d'éléments:**
- `POST /api/upload/photo` - Upload une photo pour un élément (multipart/form-data)
  - `element_id` (int) : ID de l'élément
  - `photo` (file) : Fichier image (JPEG, PNG, WebP, HEIC, max 10 Mo)
  - `legende` (string, optionnel) : Légende de la photo
  - `ordre` (int, optionnel) : Ordre d'affichage
  - `latitude` / `longitude` (float, optionnel) : Coordonnées GPS
- `DELETE /api/upload/photo/{id}` - Supprime une photo d'élément

**Photos de compteurs:**
- `POST /api/upload/compteur-photo` - Upload une photo pour un compteur (multipart/form-data)
  - `compteur_id` (int) : ID du compteur
  - `photo` (file) : Fichier image (JPEG, PNG, WebP, HEIC, max 10 Mo)
  - `legende` (string, optionnel) : Légende de la photo
- `DELETE /api/upload/compteur-photo/{compteurId}/{photoIndex}` - Supprime une photo de compteur

**Photos de pièces:**
- `POST /api/upload/piece-photo` - Upload une photo pour une pièce (multipart/form-data)
  - `piece_id` (int) : ID de la pièce
  - `photo` (file) : Fichier image (JPEG, PNG, WebP, HEIC, max 10 Mo)
  - `legende` (string, optionnel) : Légende de la photo
- `DELETE /api/upload/piece-photo/{pieceId}/{photoIndex}` - Supprime une photo de pièce

### Génération PDF
- `GET /api/edl/{id}/pdf` - Télécharge le PDF de l'état des lieux
- `GET /api/edl/{id}/pdf/preview` - Affiche le PDF dans le navigateur

### Comparatif
- `GET /api/logements/{id}/comparatif` - Compare le dernier EDL d'entrée et de sortie (terminé/signé) d'un logement. Retourne les évolutions par pièce/élément, consommations des compteurs, différence de clés, et statistiques globales.

### Estimations / Retenues
- `GET /api/logements/{id}/estimations` - Calcule les retenues sur caution basées sur les dégradations constatées entre l'EDL d'entrée et de sortie. Utilise une grille tarifaire indicative (modifiable) et les estimations personnalisées si renseignées.
- `POST /api/ai/estimations/{edl_id}` - Calcule les estimations de retenues pour un EDL de sortie spécifique
  - Body: `{ "depot_garantie": 1200 }` (optionnel)
  - Retourne: dégradations, clés manquantes, grille vétusté, total retenues, montant à restituer
- `POST /api/ai/estimations/{edl_id}/refresh` - Recalcule les estimations (même endpoint, pour forcer un refresh)

### Typologies et Dégradations
- `GET /api/typologies` - Liste des typologies de logements (studio, F1-F5, maisons) avec pièces associées
- `GET /api/degradations` - Liste des dégradations par type d'élément (mur, sol, plomberie...)
- `POST /api/edl/{id}/generer-pieces` - Génère automatiquement les pièces selon la typologie
  - Body: `{ "typologie": "f2" }`

### Signature Électronique (en face à face)
- `GET /api/edl/{id}/signature` - Statut des signatures
- `POST /api/edl/{id}/signature/bailleur` - Signer en tant que bailleur
  - Body: `{ "signature": "data:image/svg+xml;base64,..." }`
- `POST /api/edl/{id}/signature/locataire` - Signer en tant que locataire (même téléphone)
  - Body: `{ "signature": "data:image/svg+xml;base64,..." }`
  - Requiert que le bailleur ait déjà signé
  - Passe le statut à `signe` et envoie l'email de confirmation

### Partage EDL
- `GET /api/partage/{token}` - Accès public à un EDL partagé (lecture seule)

### 🤖 Intelligence Artificielle (OpenAI GPT-4 Vision)

**Configuration requise** : Variable `OPENAI_API_KEY` dans `.env.local`

- `GET /api/ai/status` - Vérifie si l'IA est configurée

**Analyse de photos :**
- `POST /api/ai/analyser-piece` - Analyse une photo de pièce et détecte les éléments
  - multipart/form-data: `photo` (file) ou `image_url` (string), `nom_piece` (optionnel)
  - Retourne: liste d'éléments détectés avec type, état, dégradations

- `POST /api/ai/edl/{edlId}/piece/{pieceId}/auto-remplir` - Analyse photo + crée automatiquement les éléments
  - multipart/form-data: `photo` (file) ou `image_url` (string)
  - Retourne: éléments créés dans la pièce

- `POST /api/ai/analyser-degradation` - Analyse une photo d'élément pour détecter les dégradations
  - multipart/form-data: `photo` (file), `type_element`, `nom_element` (optionnel)
  - Retourne: état global, dégradations détectées, estimation réparation

**Import PDF :**
- `POST /api/ai/import-pdf` - Parse un PDF d'état des lieux et extrait les données
  - multipart/form-data: `pdf` (file)
  - Retourne: données structurées (logement, pièces, éléments, compteurs, clés)

- `POST /api/ai/import-pdf/creer-edl` - Import PDF + création automatique de l'EDL
  - multipart/form-data: `pdf` (file), `logement_id`
  - Retourne: EDL créé avec toutes les pièces/éléments pré-remplis

**Estimations IA :**
- `GET /api/ai/logements/{id}/estimations` - Estimation des coûts de réparation par IA
  - Utilise GPT-4 pour des estimations plus précises que la grille tarifaire
  - Retourne: estimations détaillées avec justifications

---

## 🚀 Commandes Utiles
```bash
# Démarrer le serveur
symfony serve

# Vider le cache
php bin/console cache:clear

# Migrations
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Créer une entité
php bin/console make:entity

# Debug routes
php bin/console debug:router

# Debug GraphQL
# Aller sur http://127.0.0.1:8000/api/graphql
```

---

## ✅ Ce qui a été fait

### Backend API (Phase 1) - COMPLET
- [x] Setup Symfony 7.4 + API Platform
- [x] Configuration GraphQL (GraphiQL activé)
- [x] Configuration JWT (lexik/jwt-authentication-bundle)
- [x] Entités créées : User, Logement, EtatDesLieux, Piece, Element, Photo, Compteur, Cle, Partage
- [x] Relations entre entités configurées
- [x] Annotations API Platform avec Groups de sérialisation
- [x] AuthController (register, me)
- [x] Route login JWT
- [x] CurrentUserExtension (filtre les données par utilisateur)
- [x] UserAssignProcessor (assigne l'utilisateur automatiquement à la création)
- [x] Configuration CORS
- [x] Gestion upload photos (géolocalisées, horodatées)
- [x] Endpoint génération PDF
- [x] Endpoint comparatif entrée/sortie
- [x] Endpoint estimations/retenues (grille tarifaire)
- [x] Typologies de logements (pré-remplissage pièces)
- [x] Liste dégradations par type d'élément
- [x] Signature électronique (bailleur + locataire)
- [x] Signature en face à face (bailleur + locataire sur même téléphone)
- [x] Partage d'EDL (lien public lecture seule)
- [x] DataFixtures pour données de test

### Fonctionnalités IA (OpenAI GPT-4 Vision)
- [x] Analyse photo de pièce (détection éléments)
- [x] Auto-remplissage EDL depuis photo
- [x] Détection dégradations sur photo d'élément
- [x] Import PDF d'EDL existant
- [x] Création EDL depuis PDF importé
- [x] Estimations réparations par IA

---

## 📝 Ce qui reste à faire

### Backend API
- [ ] Tests unitaires et fonctionnels

### Application Mobile React Native (Phase 2)
- [ ] Setup projet React Native / Expo
- [ ] Configuration Apollo Client (GraphQL)
- [ ] Écran Login / Register
- [ ] Écran Accueil (Dashboard)
- [ ] Écran Liste Logements
- [ ] Écran Détail Logement
- [ ] Écran Liste États des Lieux
- [ ] Écran Détail EDL
- [ ] Écran Création/Édition EDL
- [ ] Gestion Pièces et Éléments
- [ ] Gestion Compteurs
- [ ] Gestion Clés
- [ ] Capture photos avec caméra
- [ ] Signature tactile
- [ ] Écran Comparatif
- [ ] Écran Estimations
- [ ] Génération/téléchargement PDF
- [ ] Mode hors-ligne (cache local)
- [ ] Push notifications

---

## 🧪 Exemples GraphQL

### Créer un logement
```graphql
mutation {
  createLogement(input: {
    nom: "Appartement Test"
    adresse: "10 rue de Paris"
    codePostal: "75001"
    ville: "Paris"
    type: "f2"
    surface: 45.5
    nbPieces: 2
  }) {
    logement {
      id
      nom
    }
  }
}
```

### Lister les logements
```graphql
query {
  logements {
    edges {
      node {
        id
        nom
        adresse
        ville
      }
    }
  }
}
```

### Créer un état des lieux
```graphql
mutation {
  createEtatDesLieux(input: {
    logement: "/api/logements/1"
    type: "entree"
    dateRealisation: "2025-01-20"
    locataireNom: "Jean Dupont"
    locataireEmail: "jean@email.com"
    statut: "brouillon"
  }) {
    etatDesLieux {
      id
      type
      locataireNom
    }
  }
}
```

### Récupérer un EDL complet avec pièces et éléments
```graphql
query {
  etatDesLieux(id: "/api/etat_des_lieuxes/1") {
    id
    type
    locataireNom
    statut
    pieces {
      edges {
        node {
          id
          nom
          elements {
            edges {
              node {
                id
                nom
                type
                etat
                photos {
                  edges {
                    node {
                      id
                      chemin
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    compteurs {
      edges {
        node {
          id
          type
          numero
          indexValue
        }
      }
    }
    cles {
      edges {
        node {
          id
          type
          nombre
        }
      }
    }
  }
}
```

---

## 📂 Fichiers de référence

- `/mnt/user-data/uploads/EDLYA_MOBILE_CONTEXT.md` - Contexte complet du projet
- `/mnt/user-data/uploads/edlya-mobile-mockup.jsx` - Maquette React des écrans mobile
- `/mnt/user-data/uploads/edlya.zip` - Code source Laravel de l'app web existante

---

## ⚠️ Points d'attention

1. **Sérialisation** : Utiliser les groups (`edl:read`, `edl:write`, etc.) pour contrôler les données exposées
2. **Sécurité** : CurrentUserExtension filtre automatiquement par utilisateur connecté
3. **Relations GraphQL** : Utiliser les IRI (`/api/logements/1`) pour les relations
4. **Dates** : Format ISO 8601 (`2025-01-20`)