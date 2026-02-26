# Documentation API Edlya

> API de gestion d'etats des lieux immobiliers

**Framework** : Symfony 7.4 + API Platform
**Formats** : GraphQL
**Authentification** : JWT (Bearer Token)
**Endpoint GraphQL** : `POST /api/graphql`
**Interface GraphiQL** : `/api/graphql` (mode developpement)

---

## Table des matieres

1. [Authentification](#1-authentification)
2. [Logements](#2-logements)
3. [Etats des lieux](#3-etats-des-lieux)
4. [Pieces](#4-pieces)
5. [Elements](#5-elements)
6. [Photos](#6-photos)
7. [Compteurs](#7-compteurs)
8. [Cles](#8-cles)
9. [Signatures](#9-signatures)
10. [Partage](#10-partage)
11. [Generation PDF](#11-generation-pdf)
12. [Comparatif](#12-comparatif)
13. [Estimations](#13-estimations)
14. [Typologies et degradations](#14-typologies-et-degradations)
15. [Intelligence artificielle](#15-intelligence-artificielle)
16. [Upload de fichiers](#16-upload-de-fichiers)
17. [Modele de donnees](#17-modele-de-donnees)
18. [Pagination et filtres](#18-pagination-et-filtres)
19. [Codes d'erreur](#19-codes-derreur)

---

## Convention GraphQL

Toutes les requetes GraphQL sont envoyees en `POST` sur `/api/graphql` avec le header :

```
Authorization: Bearer <token_jwt>
Content-Type: application/json
```

Corps de la requete :
```json
{
  "query": "query { ... }",
  "variables": { ... }
}
```

Les **relations** utilisent le format IRI : `/api/logements/1`, `/api/etat_des_lieuxes/3`, etc.

Les **collections** suivent le pattern Relay (edges/node) avec pagination par curseur.

---

## 1. Authentification

L'authentification se fait via des endpoints REST classiques. Le token JWT obtenu est ensuite utilise pour toutes les requetes GraphQL.

**Duree de vie du token** : 30 jours

### POST /api/register

Inscription d'un nouvel utilisateur.

**Acces** : Public

**Corps de la requete** :
```json
{
  "email": "jean@exemple.fr",
  "password": "motdepasse123",
  "name": "Jean Dupont",
  "telephone": "+33612345678"
}
```

| Champ     | Type   | Requis | Description               |
|-----------|--------|--------|---------------------------|
| email     | string | oui    | Adresse email unique      |
| password  | string | oui    | Mot de passe (min 6 car.) |
| name      | string | oui    | Nom complet               |
| telephone | string | non    | Numero de telephone       |

**Reponse 201** :
```json
{
  "message": "Utilisateur cree avec succes",
  "user": {
    "id": 1,
    "email": "jean@exemple.fr",
    "name": "Jean Dupont"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJS..."
}
```

---

### POST /api/login

Connexion et obtention du token JWT.

**Acces** : Public

**Corps de la requete** :
```json
{
  "email": "jean@exemple.fr",
  "password": "motdepasse123"
}
```

**Reponse 200** :
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJS..."
}
```

**Reponse 401** :
```json
{
  "code": 401,
  "message": "Invalid credentials."
}
```

---

### GET /api/me

Retourne les informations de l'utilisateur connecte.

**Acces** : Authentifie

**Reponse 200** :
```json
{
  "id": 1,
  "email": "jean@exemple.fr",
  "name": "Jean Dupont",
  "telephone": "+33612345678",
  "role": "agent",
  "entreprise": null,
  "createdAt": "2025-01-15T10:30:00+00:00"
}
```

---

### PUT /api/profile

Met a jour le profil de l'utilisateur connecte.

**Acces** : Authentifie

**Corps de la requete** :
```json
{
  "name": "Jean Dupont-Martin",
  "telephone": "+33698765432"
}
```

**Reponse 200** :
```json
{
  "message": "Profil mis a jour",
  "user": {
    "id": 1,
    "email": "jean@exemple.fr",
    "name": "Jean Dupont-Martin",
    "telephone": "+33698765432"
  }
}
```

---

### POST /api/auth/forgot-password

Demande de reinitialisation du mot de passe.

**Acces** : Public

**Corps** : `{ "email": "jean@exemple.fr" }`

**Reponse 200** : `{ "message": "Email de reinitialisation envoye" }`

---

### POST /api/auth/reset-password

Reinitialise le mot de passe avec le token recu par email.

**Acces** : Public

**Corps** : `{ "token": "abc123def456", "password": "nouveaumotdepasse" }`

**Reponse 200** : `{ "message": "Mot de passe reinitialise avec succes" }`

---

## 2. Logements

Gestion des biens immobiliers. Chaque logement est automatiquement associe a l'utilisateur connecte via `UserAssignProcessor`.

### Query : lister les logements

```graphql
query GetLogements($first: Int, $after: String) {
  logements(first: $first, after: $after) {
    edges {
      node {
        id
        nom
        adresse
        codePostal
        ville
        type
        surface
        nbPieces
        photoPrincipale
        createdAt
      }
      cursor
    }
    pageInfo {
      endCursor
      hasNextPage
    }
    totalCount
  }
}
```

**Variables** :
```json
{
  "first": 20,
  "after": null
}
```

---

### Query : recuperer un logement

```graphql
query GetLogement($id: ID!) {
  logement(id: $id) {
    id
    nom
    adresse
    codePostal
    ville
    type
    surface
    nbPieces
    description
    photoPrincipale
    createdAt
    updatedAt
    etatDesLieux {
      totalCount
      edges {
        node {
          id
          type
          statut
          dateRealisation
          locataireNom
          locataireEmail
          locataireTelephone
        }
      }
    }
  }
}
```

**Variables** :
```json
{
  "id": "/api/logements/1"
}
```

---

### Mutation : creer un logement

```graphql
mutation CreateLogement($input: createLogementInput!) {
  createLogement(input: $input) {
    logement {
      id
      nom
      adresse
      codePostal
      ville
      type
      surface
      nbPieces
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "nom": "Appartement Rivoli",
    "adresse": "15 rue de Rivoli",
    "codePostal": "75001",
    "ville": "Paris",
    "type": "f3",
    "surface": 65.5,
    "nbPieces": 3,
    "description": "Bel appartement lumineux",
    "photoPrincipale": "/uploads/logements/photo1.jpg"
  }
}
```

**Champs en ecriture** :

| Champ           | Type   | Requis | Description                     |
|-----------------|--------|--------|---------------------------------|
| nom             | String | oui    | Nom du logement                 |
| adresse         | String | oui    | Adresse postale                 |
| codePostal      | String | oui    | Code postal                     |
| ville           | String | oui    | Ville                           |
| type            | String | non    | Typologie (studio, f1...f5)     |
| surface         | Float  | non    | Surface en m2                   |
| nbPieces        | Int    | oui    | Nombre de pieces                |
| description     | String | non    | Description libre               |
| photoPrincipale | String | non    | Chemin vers la photo principale |

---

### Mutation : modifier un logement

```graphql
mutation UpdateLogement($input: updateLogementInput!) {
  updateLogement(input: $input) {
    logement {
      id
      nom
      adresse
      codePostal
      ville
      type
      surface
      nbPieces
      description
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "id": "/api/logements/1",
    "nom": "Appartement Rivoli - Renove"
  }
}
```

---

### Mutation : supprimer un logement

```graphql
mutation DeleteLogement($input: deleteLogementInput!) {
  deleteLogement(input: $input) {
    logement {
      id
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "id": "/api/logements/1"
  }
}
```

---

## 3. Etats des lieux

### Query : lister les etats des lieux

```graphql
query GetEtatsDesLieux($first: Int, $after: String) {
  etatDesLieuxes(first: $first, after: $after) {
    edges {
      node {
        id
        type
        dateRealisation
        locataireNom
        statut
        createdAt
        logement {
          id
          nom
          adresse
          ville
        }
        pieces {
          totalCount
        }
      }
      cursor
    }
    pageInfo {
      endCursor
      hasNextPage
    }
    totalCount
  }
}
```

**Filtres disponibles** :

| Filtre | Type   | Valeurs                                      |
|--------|--------|----------------------------------------------|
| type   | String | `entree`, `sortie`                           |
| statut | String | `brouillon`, `en_cours`, `termine`, `signe`  |

Exemple avec filtre :
```graphql
query {
  etatDesLieuxes(type: "entree", statut: "brouillon") {
    edges {
      node {
        id
        locataireNom
      }
    }
  }
}
```

---

### Query : recuperer un EDL complet

```graphql
query GetEtatDesLieux($id: ID!) {
  etatDesLieux(id: $id) {
    id
    type
    dateRealisation
    locataireNom
    locataireEmail
    locataireTelephone
    autresLocataires
    observationsGenerales
    statut
    depotGarantie
    signatureBailleur
    signatureLocataire
    dateSignatureBailleur
    dateSignatureLocataire
    createdAt
    updatedAt
    logement {
      id
      nom
      adresse
      codePostal
      ville
    }
    pieces {
      edges {
        node {
          id
          nom
          ordre
          observations
          photos
          elements {
            edges {
              node {
                id
                type
                nom
                etat
                observations
                degradations
                ordre
                photos {
                  edges {
                    node {
                      id
                      chemin
                      legende
                      latitude
                      longitude
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
          commentaire
          photos
        }
      }
    }
    cles {
      edges {
        node {
          id
          type
          nombre
          commentaire
          photo
        }
      }
    }
  }
}
```

**Variables** :
```json
{
  "id": "/api/etat_des_lieuxes/1"
}
```

---

### Query : statistiques utilisateur (dashboard)

```graphql
query GetUserStats {
  logements {
    totalCount
  }
  etatDesLieuxes {
    totalCount
  }
  enAttente: etatDesLieuxes(statut_list: ["brouillon", "en_cours"]) {
    totalCount
  }
  signes: etatDesLieuxes(statut: "signe") {
    totalCount
  }
  entrees: etatDesLieuxes(type: "entree") {
    totalCount
  }
  sorties: etatDesLieuxes(type: "sortie") {
    totalCount
  }
}
```

---

### Mutation : creer un etat des lieux

```graphql
mutation CreateEtatDesLieux($input: createEtatDesLieuxInput!) {
  createEtatDesLieux(input: $input) {
    etatDesLieux {
      id
      type
      dateRealisation
      locataireNom
      statut
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "logement": "/api/logements/1",
    "type": "entree",
    "dateRealisation": "2025-01-20",
    "locataireNom": "Marie Martin",
    "locataireEmail": "marie@email.com",
    "locataireTelephone": "+33612345678",
    "observationsGenerales": "",
    "statut": "brouillon",
    "depotGarantie": 1200
  }
}
```

**Champs en ecriture** :

| Champ                 | Type   | Requis | Description                                          |
|-----------------------|--------|--------|------------------------------------------------------|
| logement              | IRI    | oui    | IRI du logement (`/api/logements/{id}`)              |
| type                  | String | oui    | `entree` ou `sortie`                                 |
| dateRealisation       | String | non    | Date au format ISO 8601 (YYYY-MM-DD)                |
| locataireNom          | String | non    | Nom du locataire                                     |
| locataireEmail        | String | non    | Email du locataire                                   |
| locataireTelephone    | String | non    | Telephone du locataire                               |
| autresLocataires      | JSON   | non    | Autres locataires                                    |
| observationsGenerales | String | non    | Observations generales                               |
| statut                | String | non    | `brouillon`, `en_cours`, `termine`, `signe`          |
| depotGarantie         | Float  | non    | Montant du depot de garantie en euros                |

---

### Mutation : modifier un etat des lieux

```graphql
mutation UpdateEtatDesLieux($input: updateEtatDesLieuxInput!) {
  updateEtatDesLieux(input: $input) {
    etatDesLieux {
      id
      type
      dateRealisation
      locataireNom
      locataireEmail
      locataireTelephone
      autresLocataires
      observationsGenerales
      statut
      depotGarantie
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "id": "/api/etat_des_lieuxes/1",
    "statut": "en_cours",
    "observationsGenerales": "Locataire present, RAS"
  }
}
```

---

### Mutation : supprimer un etat des lieux

```graphql
mutation DeleteEtatDesLieux($input: deleteEtatDesLieuxInput!) {
  deleteEtatDesLieux(input: $input) {
    etatDesLieux {
      id
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "id": "/api/etat_des_lieuxes/1"
  }
}
```

---

## 4. Pieces

### Mutation : creer une piece

```graphql
mutation CreatePiece($input: createPieceInput!) {
  createPiece(input: $input) {
    piece {
      id
      nom
      ordre
      observations
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "etatDesLieux": "/api/etat_des_lieuxes/1",
    "nom": "Salon",
    "ordre": 1,
    "observations": "Piece en bon etat general"
  }
}
```

**Champs en ecriture** :

| Champ        | Type   | Requis | Description                |
|--------------|--------|--------|----------------------------|
| etatDesLieux | IRI    | oui    | IRI de l'EDL parent        |
| nom          | String | oui    | Nom de la piece            |
| ordre        | Int    | oui    | Ordre d'affichage          |
| observations | String | non    | Observations sur la piece  |
| photos       | JSON   | non    | Tableau de photos (JSON)   |

---

### Mutation : modifier une piece

```graphql
mutation UpdatePiece($input: updatePieceInput!) {
  updatePiece(input: $input) {
    piece {
      id
      nom
      ordre
      observations
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "id": "/api/pieces/1",
    "observations": "Traces d'humidite sur le mur nord"
  }
}
```

---

### Mutation : supprimer une piece

```graphql
mutation DeletePiece($input: deletePieceInput!) {
  deletePiece(input: $input) {
    piece {
      id
    }
  }
}
```

---

## 5. Elements

### Mutation : creer un element

```graphql
mutation CreateElement($input: createElementInput!) {
  createElement(input: $input) {
    element {
      id
      type
      nom
      etat
      observations
      degradations
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "piece": "/api/pieces/1",
    "type": "sol",
    "nom": "Parquet chene",
    "etat": "bon",
    "observations": "Quelques rayures superficielles",
    "degradations": [
      {
        "type": "rayure",
        "description": "Rayures legeres pres de l'entree",
        "estimation": 150
      }
    ],
    "ordre": 1
  }
}
```

**Champs en ecriture** :

| Champ        | Type   | Requis | Description                                   |
|--------------|--------|--------|-----------------------------------------------|
| piece        | IRI    | oui    | IRI de la piece parent                        |
| type         | String | oui    | Type d'element (voir valeurs ci-dessous)      |
| nom          | String | oui    | Nom de l'element                              |
| etat         | String | oui    | Etat de l'element (voir valeurs ci-dessous)   |
| observations | String | non    | Observations libres                           |
| degradations | JSON   | non    | Tableau de degradations                       |
| ordre        | Int    | non    | Ordre d'affichage                             |

**Types d'element** :
`sol` | `mur` | `plafond` | `menuiserie` | `electricite` | `plomberie` | `chauffage` | `equipement` | `mobilier` | `electromenager` | `autre`

**Etats possibles** :
`neuf` | `tres_bon` | `bon` | `usage` | `mauvais` | `hors_service`

---

### Mutation : modifier un element

```graphql
mutation UpdateElement($input: updateElementInput!) {
  updateElement(input: $input) {
    element {
      id
      type
      nom
      etat
      observations
      degradations
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "id": "/api/elements/5",
    "etat": "usage",
    "observations": "Traces d'usure visibles"
  }
}
```

---

### Mutation : supprimer un element

```graphql
mutation DeleteElement($input: deleteElementInput!) {
  deleteElement(input: $input) {
    element {
      id
    }
  }
}
```

---

## 6. Photos

Les photos sont gerees via GraphQL pour les metadonnees, et via des endpoints REST pour l'upload de fichiers (GraphQL ne gere pas nativement le multipart/form-data).

### Champs disponibles (lecture)

| Champ     | Type     | Description           |
|-----------|----------|-----------------------|
| id        | Int      | Identifiant unique    |
| element   | Element  | Element parent        |
| chemin    | String   | Chemin du fichier     |
| legende   | String   | Legende               |
| latitude  | Float    | Latitude GPS          |
| longitude | Float    | Longitude GPS         |
| ordre     | Int      | Ordre d'affichage     |
| createdAt | DateTime | Date de creation      |

### Query : photos d'un element

Les photos sont accessibles via la requete d'un element ou d'un EDL complet :

```graphql
query {
  element(id: "/api/elements/1") {
    id
    nom
    photos {
      edges {
        node {
          id
          chemin
          legende
          latitude
          longitude
          ordre
        }
      }
    }
  }
}
```

### Upload de photos (REST)

Voir la section [16. Upload de fichiers](#16-upload-de-fichiers) pour les endpoints d'upload.

---

## 7. Compteurs

### Mutation : creer un compteur

```graphql
mutation CreateCompteur($input: createCompteurInput!) {
  createCompteur(input: $input) {
    compteur {
      id
      type
      numero
      indexValue
      commentaire
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "etatDesLieux": "/api/etat_des_lieuxes/1",
    "type": "electricite",
    "numero": "054 789 123",
    "indexValue": "12345.67",
    "commentaire": "Compteur situe dans le couloir"
  }
}
```

**Champs en ecriture** :

| Champ        | Type   | Requis | Description                                          |
|--------------|--------|--------|------------------------------------------------------|
| etatDesLieux | IRI    | oui    | IRI de l'EDL parent                                  |
| type         | String | oui    | `electricite`, `eau_froide`, `eau_chaude`, `gaz`     |
| numero       | String | non    | Numero du compteur                                   |
| indexValue   | String | non    | Valeur de l'index                                    |
| commentaire  | String | non    | Commentaire libre                                    |
| photos       | JSON   | non    | Tableau de photos (JSON)                             |

---

### Mutation : modifier un compteur

```graphql
mutation UpdateCompteur($input: updateCompteurInput!) {
  updateCompteur(input: $input) {
    compteur {
      id
      type
      numero
      indexValue
      commentaire
    }
  }
}
```

---

### Mutation : supprimer un compteur

```graphql
mutation DeleteCompteur($input: deleteCompteurInput!) {
  deleteCompteur(input: $input) {
    compteur {
      id
    }
  }
}
```

---

## 8. Cles

### Mutation : creer une cle

```graphql
mutation CreateCle($input: createCleInput!) {
  createCle(input: $input) {
    cle {
      id
      type
      nombre
      commentaire
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "etatDesLieux": "/api/etat_des_lieuxes/1",
    "type": "porte_entree",
    "nombre": 3,
    "commentaire": "3 cles identiques"
  }
}
```

**Champs en ecriture** :

| Champ        | Type   | Requis | Description                         |
|--------------|--------|--------|-------------------------------------|
| etatDesLieux | IRI    | oui    | IRI de l'EDL parent                 |
| type         | String | oui    | Type de cle (voir valeurs)          |
| nombre       | Int    | oui    | Nombre de cles                      |
| commentaire  | String | non    | Commentaire libre                   |
| photo        | String | non    | URL de la photo                     |

**Types de cle** :
`porte_entree` | `parties_communes` | `boite_lettres` | `cave` | `garage` | `parking` | `local_velo` | `portail` | `interphone` | `badge` | `telecommande` | `vigik` | `digicode` | `autre`

---

### Mutation : modifier une cle

```graphql
mutation UpdateCle($input: updateCleInput!) {
  updateCle(input: $input) {
    cle {
      id
      type
      nombre
      commentaire
    }
  }
}
```

---

### Mutation : supprimer une cle

```graphql
mutation DeleteCle($input: deleteCleInput!) {
  deleteCle(input: $input) {
    cle {
      id
    }
  }
}
```

---

## 9. Signatures

Processus de signature electronique en face a face. Le bailleur signe en premier, puis le locataire.

Les endpoints de signature sont en REST car ils impliquent une logique metier specifique (envoi d'email, validation d'ordre, horodatage IP).

### GET /api/edl/{id}/signature

Recupere le statut des signatures d'un EDL.

**Acces** : Authentifie

**Reponse 200** :
```json
{
  "edlId": 1,
  "statut": "termine",
  "signatureBailleur": {
    "signee": true,
    "date": "2025-01-20T15:00:00+00:00",
    "signature": "data:image/svg+xml;base64,..."
  },
  "signatureLocataire": {
    "signee": false,
    "date": null,
    "signature": null
  }
}
```

---

### POST /api/edl/{id}/signature/bailleur

Signe l'EDL en tant que bailleur.

**Acces** : Authentifie (proprietaire de l'EDL)

**Corps de la requete** :
```json
{
  "signature": "data:image/svg+xml;base64,PHN2ZyB4bWxucz0i..."
}
```

**Reponse 200** :
```json
{
  "message": "Signature du bailleur enregistree",
  "statut": "termine"
}
```

**Informations enregistrees automatiquement** : date, adresse IP, User-Agent.

---

### POST /api/edl/{id}/signature/locataire

Signe l'EDL en tant que locataire. **Necessite que le bailleur ait deja signe.**

**Acces** : Authentifie

**Corps de la requete** :
```json
{
  "signature": "data:image/svg+xml;base64,PHN2ZyB4bWxucz0i..."
}
```

**Reponse 200** :
```json
{
  "message": "Signature du locataire enregistree",
  "statut": "signe"
}
```

**Effets** :
- Le statut de l'EDL passe a `signe`
- Un email de confirmation est envoye au locataire

**Reponse 400** (si le bailleur n'a pas signe) :
```json
{
  "error": "Le bailleur doit signer en premier"
}
```

---

## 10. Partage

Partage d'un EDL via lien public ou par email. Les partages peuvent aussi etre geres via GraphQL.

### Mutation GraphQL : creer un partage

```graphql
mutation CreatePartage($input: createPartageInput!) {
  createPartage(input: $input) {
    partage {
      id
      token
      type
      email
      expireAt
      createdAt
    }
  }
}
```

**Variables** :
```json
{
  "input": {
    "etatDesLieux": "/api/etat_des_lieuxes/1",
    "type": "email",
    "email": "locataire@email.com"
  }
}
```

**Champs en ecriture** :

| Champ        | Type   | Requis | Description                                 |
|--------------|--------|--------|---------------------------------------------|
| etatDesLieux | IRI    | oui    | IRI de l'EDL a partager                     |
| type         | String | oui    | `lien` (lien simple) ou `email` (envoi)     |
| email        | String | non    | Email du destinataire (requis si type=email) |

---

### Mutation GraphQL : supprimer un partage

```graphql
mutation DeletePartage($input: deletePartageInput!) {
  deletePartage(input: $input) {
    partage {
      id
    }
  }
}
```

---

### GET /api/partage/{token}

Accede a un EDL partage en lecture seule.

**Acces** : Public (token valide requis)

**Reponse 200** : L'EDL complet en lecture seule.

**Reponse 404** : Token invalide ou expire.

---

### GET /api/partage/{token}/pdf

Telecharge le PDF d'un EDL partage.

**Acces** : Public (token valide requis)

**Reponse 200** : Fichier PDF (`Content-Type: application/pdf`).

---

## 11. Generation PDF

### GET /api/edl/{id}/pdf

Telecharge le rapport PDF de l'etat des lieux.

**Acces** : Authentifie (proprietaire de l'EDL)

**Reponse 200** : Fichier PDF en telechargement (`Content-Disposition: attachment`).

---

### GET /api/edl/{id}/pdf/preview

Affiche le PDF dans le navigateur.

**Acces** : Authentifie

**Reponse 200** : Fichier PDF inline (`Content-Disposition: inline`).

---

### GET /api/edl/{id}/comparatif/pdf

Telecharge le PDF du comparatif entree/sortie.

**Acces** : Authentifie

**Reponse 200** : Fichier PDF.

---

### POST /api/edl/{id}/estimations/pdf

Genere et telecharge le PDF des estimations de retenues.

**Acces** : Authentifie

**Reponse 200** : Fichier PDF.

---

## 12. Comparatif

Compare les EDL d'entree et de sortie pour un meme logement.

### GET /api/edl/{id}/comparatif

Compare l'EDL specifie avec l'autre EDL (entree/sortie) du meme logement.

**Acces** : Authentifie

**Reponse 200** :
```json
{
  "logement": {
    "id": 1,
    "nom": "Appartement Rivoli"
  },
  "entree": {
    "id": 1,
    "dateRealisation": "2024-01-15",
    "locataireNom": "Marie Martin"
  },
  "sortie": {
    "id": 2,
    "dateRealisation": "2025-01-20",
    "locataireNom": "Marie Martin"
  },
  "comparatif": {
    "pieces": {
      "Salon": {
        "elements": [
          {
            "nom": "Parquet chene",
            "type": "sol",
            "etatEntree": "bon",
            "etatSortie": "usage",
            "evolution": "degrade"
          }
        ]
      }
    },
    "compteurs": [
      {
        "type": "electricite",
        "indexEntree": "12345",
        "indexSortie": "15678",
        "consommation": 3333
      }
    ],
    "cles": [
      {
        "type": "porte_entree",
        "nombreEntree": 3,
        "nombreSortie": 2,
        "difference": -1
      }
    ],
    "degradations": [
      {
        "piece": "Salon",
        "element": "Parquet chene",
        "description": "Rayures profondes"
      }
    ],
    "statistiques": {
      "totalElements": 24,
      "elementsAmeliores": 2,
      "elementsDegrades": 5,
      "elementsIdentiques": 17
    }
  }
}
```

---

### GET /api/logements/{id}/comparatif

Compare les derniers EDL d'entree et de sortie (termines ou signes) du logement.

**Acces** : Authentifie

**Reponse 200** : Meme format que ci-dessus.

---

## 13. Estimations

Calcul des retenues sur le depot de garantie.

### GET /api/logements/{id}/estimations

Calcule les retenues basees sur les degradations entre les EDL d'entree et de sortie.

**Acces** : Authentifie

**Reponse 200** :
```json
{
  "logement": {
    "id": 1,
    "nom": "Appartement Rivoli"
  },
  "edlEntree": { "id": 1 },
  "edlSortie": { "id": 2 },
  "estimations": {
    "degradations": [
      {
        "piece": "Salon",
        "element": "Parquet chene",
        "type": "sol",
        "etatEntree": "bon",
        "etatSortie": "mauvais",
        "description": "Rayures profondes et taches",
        "coutEstime": 350.00
      }
    ],
    "clesManquantes": [
      {
        "type": "porte_entree",
        "nombre": 1,
        "coutUnitaire": 25,
        "coutTotal": 25.00
      }
    ],
    "sousTotal": {
      "degradations": 350.00,
      "cles": 25.00
    },
    "total": 375.00,
    "grilleUtilisee": { "...": "..." },
    "coutCleUnitaire": 25
  }
}
```

---

### GET /api/couts-reparation

Retourne la grille tarifaire des couts de reparation.

**Acces** : Authentifie

### Query GraphQL : couts de reparation

Les couts sont aussi accessibles via GraphQL (lecture seule) :

```graphql
query {
  coutReparations {
    edges {
      node {
        id
        typeElement
        nom
        description
        unite
        prixUnitaire
        actif
      }
    }
  }
}
```

---

## 14. Typologies et degradations

### GET /api/typologies

Liste les typologies de logement avec les pieces predefinies.

**Acces** : Public

**Reponse 200** :
```json
{
  "typologies": {
    "studio": {
      "label": "Studio",
      "pieces": ["Piece principale", "Coin cuisine", "Salle de bain", "WC", "Entree"]
    },
    "f1": {
      "label": "F1",
      "pieces": ["Salon", "Cuisine", "Salle de bain", "WC", "Entree"]
    },
    "f2": {
      "label": "F2",
      "pieces": ["Salon", "Chambre", "Cuisine", "Salle de bain", "WC", "Entree", "Degagement"]
    },
    "f3": {
      "label": "F3",
      "pieces": ["Salon", "Chambre 1", "Chambre 2", "Cuisine", "Salle de bain", "WC", "Entree", "Degagement"]
    }
  }
}
```

---

### GET /api/degradations

Liste les degradations possibles par type d'element.

**Acces** : Public

**Reponse 200** :
```json
{
  "sol": ["Rayures", "Taches", "Cassure", "Usure prematuree", "Decollement"],
  "mur": ["Fissures", "Trous", "Traces", "Moisissures", "Decollement papier peint"],
  "plafond": ["Fissures", "Traces d'humidite", "Peinture ecaillee"],
  "plomberie": ["Fuite", "Calcaire", "Joint defectueux"],
  "electricite": ["Prise defectueuse", "Interrupteur casse", "Eclairage HS"]
}
```

---

### POST /api/edl/{id}/generer-pieces

Genere automatiquement les pieces d'un EDL selon la typologie du logement.

**Acces** : Authentifie

**Corps de la requete** :
```json
{
  "typologie": "f2"
}
```

**Reponse 201** :
```json
{
  "message": "Pieces generees avec succes",
  "pieces": [
    { "id": 10, "nom": "Salon", "ordre": 1 },
    { "id": 11, "nom": "Chambre", "ordre": 2 },
    { "id": 12, "nom": "Cuisine", "ordre": 3 },
    { "id": 13, "nom": "Salle de bain", "ordre": 4 },
    { "id": 14, "nom": "WC", "ordre": 5 },
    { "id": 15, "nom": "Entree", "ordre": 6 },
    { "id": 16, "nom": "Degagement", "ordre": 7 }
  ]
}
```

---

## 15. Intelligence artificielle

Fonctionnalites basees sur OpenAI GPT-4 Vision.
**Prerequis** : Variable d'environnement `OPENAI_API_KEY` configuree.

### GET /api/ai/status

Verifie si le service IA est disponible.

**Acces** : Public

**Reponse 200** :
```json
{
  "available": true,
  "provider": "openai"
}
```

---

### POST /api/ai/analyser-piece

Analyse une photo de piece et detecte les elements presents.

**Acces** : Authentifie
**Content-Type** : `multipart/form-data`

| Champ     | Type   | Requis | Description                            |
|-----------|--------|--------|----------------------------------------|
| photo     | file   | oui*   | Photo de la piece (*ou image_url)      |
| image_url | string | oui*   | URL de l'image (*ou photo)             |
| nom_piece | string | non    | Nom de la piece pour affiner l'analyse |

**Reponse 200** :
```json
{
  "success": true,
  "analyse": {
    "elements": [
      {
        "nom": "Parquet stratifie",
        "type": "sol",
        "etat": "bon",
        "observations": "Quelques traces d'usure normales",
        "degradations": []
      },
      {
        "nom": "Radiateur mural",
        "type": "chauffage",
        "etat": "bon",
        "observations": "Fonctionnel, legere trace de rouille",
        "degradations": ["Trace de rouille"]
      }
    ]
  }
}
```

---

### POST /api/ai/edl/{edlId}/piece/{pieceId}/auto-remplir

Analyse une photo et cree automatiquement les elements detectes dans la piece.

**Acces** : Authentifie
**Content-Type** : `multipart/form-data`

| Champ     | Type   | Requis | Description                         |
|-----------|--------|--------|-------------------------------------|
| photo     | file   | oui*   | Photo de la piece (*ou image_url)   |
| image_url | string | oui*   | URL de l'image (*ou photo)          |

**Reponse 201** :
```json
{
  "success": true,
  "message": "5 elements crees dans la piece",
  "elements": [
    {
      "id": 42,
      "nom": "Parquet stratifie",
      "type": "sol",
      "etat": "bon"
    }
  ]
}
```

---

### POST /api/ai/analyser-degradation

Analyse une photo d'element pour detecter et qualifier les degradations.

**Acces** : Authentifie
**Content-Type** : `multipart/form-data`

| Champ        | Type   | Requis | Description                      |
|--------------|--------|--------|----------------------------------|
| photo        | file   | oui    | Photo de l'element               |
| type_element | string | oui    | Type d'element (sol, mur, etc.)  |
| nom_element  | string | non    | Nom de l'element                 |

**Reponse 200** :
```json
{
  "success": true,
  "analyse": {
    "etat": "mauvais",
    "degradations": [
      {
        "type": "Fissure",
        "description": "Fissure importante de 30cm sur le mur gauche",
        "severite": "importante"
      }
    ],
    "estimationReparation": {
      "description": "Rebouchage et peinture du mur",
      "coutEstime": 250
    }
  }
}
```

---

### POST /api/ai/import-pdf

Parse un PDF d'etat des lieux existant et extrait les donnees structurees.

**Acces** : Authentifie
**Content-Type** : `multipart/form-data`

| Champ | Type | Requis | Description            |
|-------|------|--------|------------------------|
| pdf   | file | oui    | Fichier PDF a analyser |

**Reponse 200** :
```json
{
  "success": true,
  "donnees": {
    "type": "entree",
    "dateRealisation": "2024-06-15",
    "locataireNom": "Jean Dupont",
    "logement": {
      "adresse": "10 rue de la Paix",
      "ville": "Paris",
      "type": "f3"
    },
    "pieces": [
      {
        "nom": "Salon",
        "elements": [
          {
            "nom": "Parquet",
            "type": "sol",
            "etat": "bon",
            "observations": "RAS"
          }
        ]
      }
    ],
    "compteurs": [],
    "cles": []
  }
}
```

---

### POST /api/ai/import-pdf/creer-edl

Importe un PDF et cree automatiquement l'EDL avec toutes ses donnees.

**Acces** : Authentifie
**Content-Type** : `multipart/form-data`

| Champ       | Type | Requis | Description                             |
|-------------|------|--------|-----------------------------------------|
| pdf         | file | oui    | Fichier PDF a importer                  |
| logement_id | int  | non    | ID du logement cible (ou creation auto) |

**Reponse 201** :
```json
{
  "success": true,
  "message": "EDL cree avec succes depuis le PDF",
  "edl": {
    "id": 15,
    "type": "entree",
    "locataireNom": "Jean Dupont",
    "nbPieces": 6,
    "nbElements": 28
  }
}
```

---

### GET /api/ai/logements/{id}/estimations

Estimation des couts de reparation par IA (plus precise que la grille tarifaire).

**Acces** : Authentifie

**Reponse 200** : Estimations detaillees avec justifications de l'IA.

---

## 16. Upload de fichiers

L'upload de fichiers se fait via des endpoints REST en `multipart/form-data` car GraphQL ne gere pas nativement les fichiers binaires.

**Formats acceptes** : JPEG, PNG, WebP, HEIC
**Taille maximale** : 10 Mo
**Conversion automatique** : HEIC vers JPEG
**Stockage** : `/uploads/photos/edl-{id}/`

### POST /api/upload/photo

Upload une photo pour un element.

**Content-Type** : `multipart/form-data`

| Champ      | Type   | Requis | Description             |
|------------|--------|--------|-------------------------|
| element_id | int    | oui    | ID de l'element         |
| photo      | file   | oui    | Fichier image           |
| legende    | string | non    | Legende de la photo     |
| ordre      | int    | non    | Ordre d'affichage       |
| latitude   | float  | non    | Latitude GPS            |
| longitude  | float  | non    | Longitude GPS           |

**Reponse 201** :
```json
{
  "message": "Photo uploadee avec succes",
  "photo": {
    "id": 1,
    "chemin": "/uploads/photos/edl-1/photo-abc123.jpg",
    "legende": "Vue generale du sol",
    "latitude": 48.8566,
    "longitude": 2.3522,
    "ordre": 0
  }
}
```

---

### DELETE /api/upload/photo/{id}

Supprime une photo d'element.

**Reponse 200** : `{ "message": "Photo supprimee avec succes" }`

---

### POST /api/upload/compteur-photo

Upload une photo pour un compteur.

**Content-Type** : `multipart/form-data`

| Champ       | Type   | Requis | Description         |
|-------------|--------|--------|---------------------|
| compteur_id | int    | oui    | ID du compteur      |
| photo       | file   | oui    | Fichier image       |
| legende     | string | non    | Legende de la photo |

---

### DELETE /api/upload/compteur-photo/{compteurId}/{photoIndex}

Supprime une photo de compteur par son index dans le tableau JSON.

---

### POST /api/upload/piece-photo

Upload une photo pour une piece.

**Content-Type** : `multipart/form-data`

| Champ    | Type   | Requis | Description         |
|----------|--------|--------|---------------------|
| piece_id | int    | oui    | ID de la piece      |
| photo    | file   | oui    | Fichier image       |
| legende  | string | non    | Legende de la photo |

---

### DELETE /api/upload/piece-photo/{pieceId}/{photoIndex}

Supprime une photo de piece par son index dans le tableau JSON.

---

## 17. Modele de donnees

### Schema relationnel

```
User
 |
 +-- 1:N -- Logement
 |              |
 |              +-- 1:N -- EtatDesLieux
 |                            |
 |                            +-- 1:N -- Piece
 |                            |            |
 |                            |            +-- 1:N -- Element
 |                            |                         |
 |                            |                         +-- 1:N -- Photo
 |                            |
 |                            +-- 1:N -- Compteur
 |                            |
 |                            +-- 1:N -- Cle
 |                            |
 |                            +-- 1:N -- Partage
```

### Resume des entites

| Entite         | GraphQL Query   | GraphQL Mutations           | Description                       |
|----------------|-----------------|-----------------------------|-----------------------------------|
| User           | oui (read)      | update                      | Utilisateur (proprietaire/agent)  |
| Logement       | oui             | create, update, delete      | Bien immobilier                   |
| EtatDesLieux   | oui (+ filtres) | create, update, delete      | Etat des lieux                    |
| Piece          | oui             | create, update, delete      | Piece d'un EDL                    |
| Element        | oui             | create, update, delete      | Element d'une piece               |
| Photo          | oui             | create, update, delete      | Photo d'un element                |
| Compteur       | oui             | create, update, delete      | Compteur (eau, gaz, elec.)        |
| Cle            | oui             | create, update, delete      | Cle / badge / telecommande        |
| Partage        | oui             | create, delete              | Lien de partage d'un EDL          |
| CoutReparation | oui             | aucune (lecture seule)       | Grille tarifaire des reparations  |

### Groupes de serialisation

| Groupe         | Description                                                  |
|----------------|--------------------------------------------------------------|
| user:read      | id, email, name, telephone, role, entreprise, timestamps     |
| user:write     | email, name, telephone, role, entreprise                     |
| logement:read  | tous les champs + collection etatDesLieux                    |
| logement:write | nom, adresse, codePostal, ville, type, surface, nbPieces... |
| edl:read       | tous les champs + pieces, compteurs, cles, partages          |
| edl:write      | logement, type, date, locataire*, observations, statut...    |
| piece:read     | tous les champs + collection elements                        |
| piece:write    | etatDesLieux, nom, ordre, observations, photos               |
| element:read   | tous les champs + collection photos                          |
| element:write  | piece, type, nom, etat, observations, degradations, ordre    |
| photo:read     | tous les champs                                              |
| photo:write    | element, chemin, legende, latitude, longitude, ordre         |
| compteur:read  | tous les champs                                              |
| compteur:write | etatDesLieux, type, numero, indexValue, commentaire, photos  |
| cle:read       | tous les champs                                              |
| cle:write      | etatDesLieux, type, nombre, commentaire, photo               |
| partage:read   | tous les champs                                              |
| partage:write  | etatDesLieux, email, type                                    |
| cout:read      | tous les champs (lecture seule)                              |

---

## 18. Pagination et filtres

### Pagination (Relay cursor-based)

Toutes les collections GraphQL utilisent la pagination par curseur :

```graphql
query {
  logements(first: 20, after: "cursor_abc") {
    edges {
      node { id, nom }
      cursor
    }
    pageInfo {
      endCursor
      hasNextPage
    }
    totalCount
  }
}
```

| Parametre | Type   | Description                                   |
|-----------|--------|-----------------------------------------------|
| first     | Int    | Nombre d'elements a retourner                 |
| after     | String | Curseur apres lequel commencer                |
| last      | Int    | Nombre d'elements depuis la fin               |
| before    | String | Curseur avant lequel commencer                |

### Filtres sur EtatDesLieux

```graphql
# Filtrer par type
etatDesLieuxes(type: "entree") { ... }

# Filtrer par statut
etatDesLieuxes(statut: "brouillon") { ... }

# Combiner les filtres
etatDesLieuxes(type: "sortie", statut: "signe") { ... }

# Filtrer par liste de statuts
etatDesLieuxes(statut_list: ["brouillon", "en_cours"]) { ... }
```

---

## 19. Codes d'erreur

| Code | Signification        | Description                                     |
|------|----------------------|-------------------------------------------------|
| 200  | OK                   | Requete traitee avec succes                     |
| 201  | Created              | Ressource creee avec succes                     |
| 204  | No Content           | Suppression reussie                             |
| 400  | Bad Request          | Parametres invalides ou manquants               |
| 401  | Unauthorized         | Token JWT manquant ou invalide                  |
| 403  | Forbidden            | Acces refuse (pas proprietaire de la ressource) |
| 404  | Not Found            | Ressource introuvable                           |
| 413  | Payload Too Large    | Fichier trop volumineux (max 10 Mo)             |
| 422  | Unprocessable Entity | Erreur de validation des donnees                |
| 500  | Internal Server Error| Erreur serveur interne                          |

### Format des erreurs GraphQL

```json
{
  "errors": [
    {
      "message": "Access Denied.",
      "extensions": {
        "category": "user"
      },
      "locations": [{ "line": 2, "column": 3 }],
      "path": ["logement"]
    }
  ],
  "data": null
}
```

### Format des erreurs REST

```json
{
  "error": "Message d'erreur lisible",
  "code": 400
}
```

---

## Annexes

### Variables d'environnement

| Variable          | Description                    | Exemple                                           |
|-------------------|--------------------------------|---------------------------------------------------|
| DATABASE_URL      | Connexion MySQL                | `mysql://user:pass@127.0.0.1:3306/edlya`          |
| JWT_SECRET_KEY    | Chemin cle privee JWT          | `%kernel.project_dir%/config/jwt/private.pem`      |
| JWT_PUBLIC_KEY    | Chemin cle publique JWT        | `%kernel.project_dir%/config/jwt/public.pem`       |
| JWT_PASSPHRASE    | Phrase secrete JWT             | `votre-passphrase`                                 |
| OPENAI_API_KEY    | Cle API OpenAI (fonctions IA)  | `sk-...`                                           |
| CORS_ALLOW_ORIGIN | Origines CORS autorisees       | `'^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$'` |
| MAILER_DSN        | Configuration du mailer        | `smtp://localhost:1025`                            |
| MAILER_FROM_EMAIL | Email expediteur               | `noreply@edlya.fr`                                 |
| MAILER_FROM_NAME  | Nom expediteur                 | `Edlya`                                            |

### Securite

- **CurrentUserExtension** : filtre automatiquement toutes les requetes par utilisateur connecte (impossible d'acceder aux donnees d'un autre utilisateur)
- **UserAssignProcessor** : assigne automatiquement l'utilisateur connecte aux nouvelles ressources (Logement, EtatDesLieux)
- **CORS** : configure via `nelmio/cors-bundle` (methodes GET, POST, PUT, PATCH, DELETE, OPTIONS)
- **Roles** : `admin`, `agent`, `bailleur`

### Endpoints publics (sans authentification)

- `POST /api/register`
- `POST /api/login`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/typologies`
- `GET /api/degradations`
- `GET /api/ai/status`
- `GET /api/partage/{token}`
- `GET /api/partage/{token}/pdf`
