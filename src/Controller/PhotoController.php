<?php

namespace App\Controller;

use App\Entity\Compteur;
use App\Entity\Element;
use App\Entity\Photo;
use App\Entity\Piece;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class PhotoController extends AbstractController
{
    public function __construct(
        private string $photoDirectory,
    ) {}

    /**
     * Convertit un fichier HEIC en JPEG si nécessaire
     */
    private function convertHeicToJpeg(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (!in_array($extension, ['heic', 'heif'])) {
            return $filePath;
        }

        $jpegPath = preg_replace('/\.(heic|heif)$/i', '.jpg', $filePath);

        // Utiliser ImageMagick pour la conversion
        $command = sprintf(
            'convert %s %s 2>&1',
            escapeshellarg($filePath),
            escapeshellarg($jpegPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($jpegPath)) {
            // Supprimer le fichier HEIC original
            unlink($filePath);
            return $jpegPath;
        }

        // Si la conversion échoue, garder le fichier original
        return $filePath;
    }

    #[Route('/api/upload/photo', name: 'api_photo_upload', methods: ['POST'])]
    public function upload(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $elementId = $request->request->get('element_id');
        $file = $request->files->get('photo');

        if (!$elementId || !$file) {
            return new JsonResponse(
                ['error' => 'element_id et photo sont requis'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Récupérer l'élément et vérifier l'accès
        $element = $em->getRepository(Element::class)->find($elementId);

        if (!$element) {
            return new JsonResponse(['error' => 'Élément non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est propriétaire de l'EDL
        $edl = $element->getPiece()->getEtatDesLieux();
        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        // Valider le type de fichier
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse(
                ['error' => 'Type de fichier non autorisé. Formats acceptés: JPEG, PNG, WebP, HEIC'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Valider la taille (max 10 Mo)
        if ($file->getSize() > 10 * 1024 * 1024) {
            return new JsonResponse(
                ['error' => 'Fichier trop volumineux (max 10 Mo)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Générer un nom de fichier unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Créer le sous-répertoire par EDL
        $subDirectory = 'edl-' . $edl->getId();
        $targetDirectory = $this->photoDirectory . '/' . $subDirectory;

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        try {
            $file->move($targetDirectory, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(
                ['error' => 'Erreur lors de l\'upload. Veuillez réessayer.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Convertir HEIC en JPEG si nécessaire
        $fullPath = $targetDirectory . '/' . $newFilename;
        $convertedPath = $this->convertHeicToJpeg($fullPath);
        $finalFilename = basename($convertedPath);

        // Créer l'entité Photo
        $photo = new Photo();
        $photo->setElement($element);
        $photo->setChemin('/uploads/photos/' . $subDirectory . '/' . $finalFilename);
        $photo->setLegende($request->request->get('legende'));
        $photo->setOrdre((int) $request->request->get('ordre', $element->getPhotos()->count()));

        // Coordonnées GPS optionnelles
        if ($request->request->has('latitude') && $request->request->has('longitude')) {
            $photo->setLatitude((float) $request->request->get('latitude'));
            $photo->setLongitude((float) $request->request->get('longitude'));
        }

        $em->persist($photo);
        $em->flush();

        // Ajouter la référence "(Photo N)" dans l'observation de l'élément
        $this->addPhotoReferenceToObservation($element, $em);

        return new JsonResponse([
            'message' => 'Photo uploadée avec succès',
            'photo' => [
                'id' => $photo->getId(),
                'chemin' => $photo->getChemin(),
                'legende' => $photo->getLegende(),
                'ordre' => $photo->getOrdre(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/upload/photo/{id<\d+>}', name: 'api_photo_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $photo = $em->getRepository(Photo::class)->find($id);

        if (!$photo) {
            return new JsonResponse(['error' => 'Photo non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier l'accès
        $edl = $photo->getElement()->getPiece()->getEtatDesLieux();
        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        // Garder une ref à l'élément avant suppression
        $element = $photo->getElement();

        // Supprimer le fichier physique
        $publicDir = dirname($this->photoDirectory, 2);
        $filePath = $publicDir . $photo->getChemin();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $em->remove($photo);
        $em->flush();

        // Renuméroter les "(Photo X)" dans l'observation après suppression
        $this->recalculatePhotoReferences($element, $em);

        return new JsonResponse(['message' => 'Photo supprimée'], Response::HTTP_OK);
    }

    /**
     * Upload une photo pour un compteur
     */
    #[Route('/api/upload/compteur-photo', name: 'api_compteur_photo_upload', methods: ['POST'])]
    public function uploadCompteurPhoto(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $compteurId = $request->request->get('compteur_id');
        $file = $request->files->get('photo');

        if (!$compteurId || !$file) {
            return new JsonResponse(
                ['error' => 'compteur_id et photo sont requis'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $compteur = $em->getRepository(Compteur::class)->find($compteurId);

        if (!$compteur) {
            return new JsonResponse(['error' => 'Compteur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est propriétaire de l'EDL
        $edl = $compteur->getEtatDesLieux();
        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        // Valider le type de fichier
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse(
                ['error' => 'Type de fichier non autorisé. Formats acceptés: JPEG, PNG, WebP, HEIC'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Valider la taille (max 10 Mo)
        if ($file->getSize() > 10 * 1024 * 1024) {
            return new JsonResponse(
                ['error' => 'Fichier trop volumineux (max 10 Mo)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Générer un nom de fichier unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Créer le sous-répertoire par EDL
        $subDirectory = 'edl-' . $edl->getId() . '/compteurs';
        $targetDirectory = $this->photoDirectory . '/' . $subDirectory;

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        try {
            $file->move($targetDirectory, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(
                ['error' => 'Erreur lors de l\'upload. Veuillez réessayer.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Convertir HEIC en JPEG si nécessaire
        $fullPath = $targetDirectory . '/' . $newFilename;
        $convertedPath = $this->convertHeicToJpeg($fullPath);
        $finalFilename = basename($convertedPath);

        // Ajouter le chemin au tableau de photos
        $photoPath = '/uploads/photos/' . $subDirectory . '/' . $finalFilename;
        $photos = $compteur->getPhotos() ?? [];
        $photos[] = [
            'chemin' => $photoPath,
            'legende' => $request->request->get('legende'),
            'uploadedAt' => (new \DateTimeImmutable())->format('c'),
        ];
        $compteur->setPhotos($photos);

        $em->flush();

        return new JsonResponse([
            'message' => 'Photo uploadée avec succès',
            'photo' => [
                'chemin' => $photoPath,
                'legende' => $request->request->get('legende'),
                'index' => count($photos) - 1,
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Supprime une photo d'un compteur
     */
    #[Route('/api/upload/compteur-photo/{compteurId}/{photoIndex}', name: 'api_compteur_photo_delete', methods: ['DELETE'])]
    public function deleteCompteurPhoto(
        int $compteurId,
        int $photoIndex,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $compteur = $em->getRepository(Compteur::class)->find($compteurId);

        if (!$compteur) {
            return new JsonResponse(['error' => 'Compteur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $edl = $compteur->getEtatDesLieux();
        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        $photos = $compteur->getPhotos() ?? [];

        if (!isset($photos[$photoIndex])) {
            return new JsonResponse(['error' => 'Photo non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Supprimer le fichier physique (photos stockées comme string ou {chemin: ...})
        $publicDir = dirname($this->photoDirectory, 2);
        $photoEntry = $photos[$photoIndex];
        $chemin = is_array($photoEntry) ? ($photoEntry['chemin'] ?? '') : (string) $photoEntry;
        if ($chemin) {
            $filePath = $publicDir . $chemin;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Retirer du tableau
        array_splice($photos, $photoIndex, 1);
        $compteur->setPhotos($photos);

        $em->flush();

        return new JsonResponse(['message' => 'Photo supprimée'], Response::HTTP_OK);
    }

    /**
     * Upload une photo pour une pièce
     */
    #[Route('/api/upload/piece-photo', name: 'api_piece_photo_upload', methods: ['POST'])]
    public function uploadPiecePhoto(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $pieceId = $request->request->get('piece_id');
        $file = $request->files->get('photo');

        if (!$pieceId || !$file) {
            return new JsonResponse(
                ['error' => 'piece_id et photo sont requis'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $piece = $em->getRepository(Piece::class)->find($pieceId);

        if (!$piece) {
            return new JsonResponse(['error' => 'Pièce non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est propriétaire de l'EDL
        $edl = $piece->getEtatDesLieux();
        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        // Valider le type de fichier
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse(
                ['error' => 'Type de fichier non autorisé. Formats acceptés: JPEG, PNG, WebP, HEIC'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Valider la taille (max 10 Mo)
        if ($file->getSize() > 10 * 1024 * 1024) {
            return new JsonResponse(
                ['error' => 'Fichier trop volumineux (max 10 Mo)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Générer un nom de fichier unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Créer le sous-répertoire par EDL
        $subDirectory = 'edl-' . $edl->getId() . '/pieces';
        $targetDirectory = $this->photoDirectory . '/' . $subDirectory;

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        try {
            $file->move($targetDirectory, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(
                ['error' => 'Erreur lors de l\'upload. Veuillez réessayer.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Convertir HEIC en JPEG si nécessaire
        $fullPath = $targetDirectory . '/' . $newFilename;
        $convertedPath = $this->convertHeicToJpeg($fullPath);
        $finalFilename = basename($convertedPath);

        // Ajouter le chemin au tableau de photos
        $photoPath = '/uploads/photos/' . $subDirectory . '/' . $finalFilename;
        $photos = $piece->getPhotos() ?? [];
        $photos[] = [
            'chemin' => $photoPath,
            'legende' => $request->request->get('legende'),
            'uploadedAt' => (new \DateTimeImmutable())->format('c'),
        ];
        $piece->setPhotos($photos);

        $em->flush();

        return new JsonResponse([
            'message' => 'Photo uploadée avec succès',
            'photo' => [
                'chemin' => $photoPath,
                'legende' => $request->request->get('legende'),
                'index' => count($photos) - 1,
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Supprime une photo d'une pièce
     */
    #[Route('/api/upload/piece-photo/{pieceId}/{photoIndex}', name: 'api_piece_photo_delete', methods: ['DELETE'])]
    public function deletePiecePhoto(
        int $pieceId,
        int $photoIndex,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $piece = $em->getRepository(Piece::class)->find($pieceId);

        if (!$piece) {
            return new JsonResponse(['error' => 'Pièce non trouvée'], Response::HTTP_NOT_FOUND);
        }

        $edl = $piece->getEtatDesLieux();
        if ($edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès non autorisé'], Response::HTTP_FORBIDDEN);
        }

        $photos = $piece->getPhotos() ?? [];

        if (!isset($photos[$photoIndex])) {
            return new JsonResponse(['error' => 'Photo non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Supprimer le fichier physique
        $publicDir = dirname($this->photoDirectory, 2);
        $filePath = $publicDir . $photos[$photoIndex]['chemin'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Retirer du tableau
        array_splice($photos, $photoIndex, 1);
        $piece->setPhotos($photos);

        $em->flush();

        return new JsonResponse(['message' => 'Photo supprimée'], Response::HTTP_OK);
    }

    /**
     * Ajoute "(Photo N)" à l'observation de l'élément lors de l'upload
     */
    private function addPhotoReferenceToObservation(Element $element, EntityManagerInterface $em): void
    {
        $em->refresh($element);
        $photoCount = $element->getPhotos()->count();
        $observation = $element->getObservations() ?? '';

        // Ne pas ajouter si la ref existe déjà
        $ref = "(Photo {$photoCount})";
        if (str_contains($observation, $ref)) {
            return;
        }

        $newObservation = trim($observation . ' ' . $ref);
        $element->setObservations($newObservation);
        $em->flush();
    }

    /**
     * Renuméroter les "(Photo X)" dans l'observation après suppression
     */
    private function recalculatePhotoReferences(Element $element, EntityManagerInterface $em): void
    {
        $observation = $element->getObservations();
        if (!$observation) {
            return;
        }

        // Supprimer toutes les anciennes références "(Photo X)"
        $cleaned = preg_replace('/\s*\(Photo \d+\)/', '', $observation);
        $cleaned = trim($cleaned);

        // Renuméroter les photos restantes
        $em->refresh($element);
        $photoCount = $element->getPhotos()->count();
        $refs = [];
        for ($i = 1; $i <= $photoCount; $i++) {
            $refs[] = "(Photo {$i})";
        }

        if (!empty($refs)) {
            $cleaned = trim($cleaned . ' ' . implode(' ', $refs));
        }

        $element->setObservations($cleaned ?: null);
        $em->flush();
    }
}
