<?php

namespace App\Controller;

use App\Entity\Cle;
use App\Entity\Compteur;
use App\Entity\CoutReparation;
use App\Entity\Element;
use App\Entity\EtatDesLieux;
use App\Entity\Logement;
use App\Entity\Photo;
use App\Entity\Piece;
use App\Entity\User;
use App\Service\AIService;
use App\Service\EstimationService;
use App\Service\ImportValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AIController extends AbstractController
{
    public function __construct(
        private AIService $aiService,
        private EstimationService $estimationService,
        private ImportValidationService $validationService,
        private EntityManagerInterface $em,
        private string $photoDirectory
    ) {
    }

    /**
     * Vérifie si l'IA est configurée
     */
    #[Route('/api/ai/status', name: 'api_ai_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return new JsonResponse([
            'configured' => $this->aiService->isConfigured(),
            'features' => [
                'analyse_photo_piece' => $this->aiService->isConfigured(),
                'analyse_degradation' => $this->aiService->isConfigured(),
                'import_pdf' => $this->aiService->isConfigured(),
                'estimation_ia' => $this->aiService->isConfigured(),
                'ameliorer_observation' => $this->aiService->isConfigured(),
            ],
        ]);
    }

    /**
     * Analyse une photo de pièce et détecte les éléments
     */
    #[Route('/api/ai/analyser-piece', name: 'api_ai_analyser_piece', methods: ['POST'])]
    public function analyserPiece(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->aiService->isConfigured()) {
            return new JsonResponse(['error' => 'Service IA non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('photo');
        $imageUrl = $request->request->get('image_url');
        $nomPiece = $request->request->get('nom_piece');

        if (!$photo && !$imageUrl) {
            return new JsonResponse(['error' => 'Une photo ou une URL d\'image est requise'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $imageBase64 = $this->getImageBase64($photo, $imageUrl);
            $result = $this->aiService->analyserPhotoPiece($imageBase64, $nomPiece);

            return new JsonResponse([
                'success' => true,
                'analyse' => $result,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'analyse. Veuillez réessayer.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Analyse une photo de pièce et crée automatiquement les éléments dans un EDL
     */
    #[Route('/api/ai/edl/{edlId}/piece/{pieceId}/auto-remplir', name: 'api_ai_auto_remplir_piece', methods: ['POST'])]
    public function autoRemplirPiece(int $edlId, int $pieceId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->aiService->isConfigured()) {
            return new JsonResponse(['error' => 'Service IA non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $edl = $this->em->getRepository(EtatDesLieux::class)->find($edlId);
        if (!$edl || $edl->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $piece = $this->em->getRepository(Piece::class)->find($pieceId);
        if (!$piece || $piece->getEtatDesLieux()->getId() !== $edlId) {
            return new JsonResponse(['error' => 'Pièce non trouvée'], Response::HTTP_NOT_FOUND);
        }

        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('photo');
        $imageUrl = $request->request->get('image_url');

        if (!$photo && !$imageUrl) {
            return new JsonResponse(['error' => 'Une photo ou une URL d\'image est requise'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $imageBase64 = $this->getImageBase64($photo, $imageUrl);
            $result = $this->aiService->analyserPhotoPiece($imageBase64, $piece->getNom());

            // Créer les éléments détectés
            $elementsCreated = [];
            $ordre = $piece->getElements()->count();

            foreach ($result['elements'] ?? [] as $elementData) {
                $element = new Element();
                $element->setPiece($piece);
                $element->setNom($elementData['nom']);
                $element->setType($elementData['type'] ?? 'autre');
                $element->setEtat($elementData['etat'] ?? 'bon');
                $element->setObservations($elementData['observations'] ?? null);
                $element->setOrdre($ordre++);

                if (!empty($elementData['degradations'])) {
                    $element->setDegradations(['liste' => $elementData['degradations']]);
                }

                $this->em->persist($element);
                $piece->addElement($element);

                $elementsCreated[] = [
                    'id' => null,
                    'nom' => $element->getNom(),
                    'type' => $element->getType(),
                    'etat' => $element->getEtat(),
                    'observations' => $element->getObservations(),
                ];
            }

            $this->em->flush();

            // Mettre à jour les IDs
            $elements = $piece->getElements()->toArray();
            $elementsCreated = array_map(function ($el) {
                return [
                    'id' => $el->getId(),
                    'nom' => $el->getNom(),
                    'type' => $el->getType(),
                    'etat' => $el->getEtat(),
                    'observations' => $el->getObservations(),
                ];
            }, array_slice($elements, -count($elementsCreated)));

            return new JsonResponse([
                'success' => true,
                'message' => sprintf('%d éléments créés', count($elementsCreated)),
                'piece' => [
                    'id' => $piece->getId(),
                    'nom' => $piece->getNom(),
                ],
                'elements_crees' => $elementsCreated,
                'analyse_complete' => $result,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'analyse. Veuillez réessayer.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Analyse une photo d'élément pour détecter les dégradations
     */
    #[Route('/api/ai/analyser-degradation', name: 'api_ai_analyser_degradation', methods: ['POST'])]
    public function analyserDegradation(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->aiService->isConfigured()) {
            return new JsonResponse(['error' => 'Service IA non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('photo');
        $imageUrl = $request->request->get('image_url');
        $typeElement = $request->request->get('type_element', 'autre');
        $nomElement = $request->request->get('nom_element');

        if (!$photo && !$imageUrl) {
            return new JsonResponse(['error' => 'Une photo ou une URL d\'image est requise'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $imageBase64 = $this->getImageBase64($photo, $imageUrl);
            $result = $this->aiService->analyserPhotoDegradation($imageBase64, $typeElement, $nomElement);

            return new JsonResponse([
                'success' => true,
                'analyse' => $result,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'analyse. Veuillez réessayer.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sert une photo extraite lors de l'import PDF
     * ?thumb=1 → miniature JPEG (max 300px, qualité 60)
     */
    #[Route('/api/ai/import-photo/{importId}/{index}', name: 'api_ai_import_photo', methods: ['GET'])]
    public function importPhoto(string $importId, int $index, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        if (!preg_match('/^[a-f0-9]{32}$/', $importId)) {
            return new JsonResponse(['error' => 'ID d\'import invalide'], Response::HTTP_BAD_REQUEST);
        }

        $importDir = sys_get_temp_dir() . '/edlya_import_' . $importId;
        $photoPath = $importDir . '/photo_' . $index . '.png';

        if (!file_exists($photoPath)) {
            return new JsonResponse(['error' => 'Photo non trouvée ou expirée'], Response::HTTP_GONE);
        }

        $isThumb = $request->query->getBoolean('thumb', false);

        if ($isThumb) {
            $image = imagecreatefrompng($photoPath);
            if (!$image) {
                return new JsonResponse(['error' => 'Impossible de lire l\'image'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $origW = imagesx($image);
            $origH = imagesy($image);
            $maxSize = 300;

            if ($origW > $maxSize || $origH > $maxSize) {
                $ratio = min($maxSize / $origW, $maxSize / $origH);
                $newW = (int) round($origW * $ratio);
                $newH = (int) round($origH * $ratio);
                $thumb = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                imagedestroy($image);
                $image = $thumb;
            }

            ob_start();
            imagejpeg($image, null, 60);
            $jpegData = ob_get_clean();
            imagedestroy($image);

            $response = new Response($jpegData, 200, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'private, max-age=3600',
            ]);

            return $response;
        }

        $response = new BinaryFileResponse($photoPath);
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }

    /**
     * Import et analyse d'un PDF - Étape 1 : extraction + validation (preview)
     * Retourne les données extraites pour édition avant création
     */
    #[Route('/api/ai/import-pdf', name: 'api_ai_import_pdf', methods: ['POST'])]
    public function importPdf(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->aiService->isConfigured()) {
            return new JsonResponse(['error' => 'Service IA non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        /** @var UploadedFile|null $pdf */
        $pdf = $request->files->get('pdf');

        if (!$pdf) {
            return new JsonResponse(['error' => 'Un fichier PDF est requis'], Response::HTTP_BAD_REQUEST);
        }

        if ($pdf->getMimeType() !== 'application/pdf') {
            return new JsonResponse(['error' => 'Le fichier doit être un PDF'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->aiService->analyserPdfEdl($pdf->getPathname());

            // Séparer les photos extraites des données
            $extractedPhotos = $result['_extracted_photos'] ?? [];
            unset($result['_extracted_photos']);

            // Valider et auto-corriger les données
            $result = $this->validationService->validate($result, count($extractedPhotos));

            // Sauvegarder les photos dans un dossier temporaire persistant avec un ID
            $importId = bin2hex(random_bytes(16));
            $importDir = sys_get_temp_dir() . '/edlya_import_' . $importId;
            mkdir($importDir, 0755, true);

            // Copier les photos extraites dans le dossier d'import
            $photosMeta = [];
            foreach ($extractedPhotos as $index => $photo) {
                if (isset($photo['path']) && file_exists($photo['path'])) {
                    $destPath = $importDir . '/photo_' . ($index + 1) . '.png';
                    copy($photo['path'], $destPath);

                    $photosMeta[] = [
                        'index' => $index + 1,
                        'width' => $photo['width'],
                        'height' => $photo['height'],
                        'size' => filesize($photo['path']),
                    ];
                }
            }

            // Chercher un logement existant qui correspond
            $existingLogement = null;
            if (!empty($result['logement']['adresse'])) {
                $existingLogement = $this->em->getRepository(Logement::class)->findOneBy([
                    'user' => $user,
                    'adresse' => $result['logement']['adresse'],
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'donnees_extraites' => $result,
                'images' => $photosMeta,
                'import_id' => $importId,
                'photo_count' => count($extractedPhotos),
                'existing_logement' => $existingLogement ? [
                    'id' => $existingLogement->getId(),
                    'nom' => $existingLogement->getNom(),
                    'adresse' => $existingLogement->getAdresse(),
                ] : null,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'analyse du PDF. Veuillez réessayer.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Import un PDF et crée automatiquement un EDL pré-rempli
     * Étape 2 : création avec données (potentiellement modifiées par l'utilisateur)
     */
    #[Route('/api/ai/import-pdf/creer-edl', name: 'api_ai_import_pdf_creer_edl', methods: ['POST'])]
    public function importPdfCreerEdl(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->aiService->isConfigured()) {
            return new JsonResponse(['error' => 'Service IA non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // Accepter soit un PDF (ancien flow) soit des données JSON (nouveau flow preview)
        $contentType = $request->headers->get('Content-Type', '');
        $isJsonRequest = str_contains($contentType, 'application/json');

        if ($isJsonRequest) {
            // Nouveau flow : données validées depuis le preview
            $body = json_decode($request->getContent(), true);
            $result = $body['data'] ?? [];
            $logementId = $body['logement_id'] ?? null;

            // Récupérer les photos depuis le dossier temporaire via import_id
            $tempPhotoPaths = [];
            $importId = $body['import_id'] ?? null;
            if ($importId && preg_match('/^[a-f0-9]{32}$/', $importId)) {
                $importDir = sys_get_temp_dir() . '/edlya_import_' . $importId;
                if (is_dir($importDir)) {
                    $files = glob($importDir . '/photo_*.png');
                    sort($files);
                    $tempPhotoPaths = $files;
                } else {
                    return new JsonResponse(
                        ['error' => 'Les photos importées ont expiré. Veuillez ré-importer le PDF.'],
                        Response::HTTP_GONE
                    );
                }
            }
        } else {
            // Ancien flow : upload PDF direct
            /** @var UploadedFile|null $pdf */
            $pdf = $request->files->get('pdf');
            $logementId = $request->request->get('logement_id');

            if (!$pdf) {
                return new JsonResponse(['error' => 'Un fichier PDF ou des données sont requis'], Response::HTTP_BAD_REQUEST);
            }

            try {
                $result = $this->aiService->analyserPdfEdl($pdf->getPathname());
                $tempPhotoPaths = array_map(fn($p) => $p['path'], $result['_extracted_photos'] ?? []);
                unset($result['_extracted_photos']);

                // Valider les données
                $result = $this->validationService->validate($result, count($tempPhotoPaths));
            } catch (\Exception $e) {
                return new JsonResponse([
                    'error' => 'Erreur lors de l\'analyse. Veuillez réessayer.'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        try {
            // Trouver ou créer le logement
            $logement = null;
            if ($logementId) {
                $logement = $this->em->getRepository(Logement::class)->find($logementId);
                if ($logement && $logement->getUser()->getId() !== $user->getId()) {
                    $logement = null;
                }
            }

            if (!$logement) {
                // Créer un nouveau logement
                $logement = new Logement();
                $logement->setUser($user);
                $logement->setNom($result['logement']['nom'] ?? 'Logement importé');
                $logement->setAdresse($result['logement']['adresse'] ?? 'À compléter');
                $logement->setCodePostal($result['logement']['code_postal'] ?? '00000');
                $logement->setVille($result['logement']['ville'] ?? 'À compléter');
                $logement->setType($this->normalizeLogementType($result['logement']['type'] ?? $result['logement']['type_bien'] ?? null));
                $logement->setSurface($result['logement']['surface'] ?? null);
                // Utiliser le nombre réel de pièces extraites plutôt que la valeur IA
                $logement->setNbPieces(count($result['pieces'] ?? []));
                $this->em->persist($logement);
            }

            // Créer l'état des lieux
            $edl = new EtatDesLieux();
            $edl->setLogement($logement);
            $edl->setUser($user);
            $edl->setType($result['type'] ?? $result['type_edl'] ?? 'entree');
            $edl->setStatut('brouillon');

            // Date
            if (!empty($result['date_realisation'])) {
                try {
                    $edl->setDateRealisation(new \DateTime($result['date_realisation']));
                } catch (\Exception $e) {
                    $edl->setDateRealisation(new \DateTime());
                }
            } else {
                $edl->setDateRealisation(new \DateTime());
            }

            // Locataire
            $edl->setLocataireNom($result['locataire']['nom'] ?? 'À compléter');
            $edl->setLocataireEmail($result['locataire']['email'] ?? null);
            $edl->setLocataireTelephone($result['locataire']['telephone'] ?? null);

            // Observations
            $edl->setObservationsGenerales($result['observations_generales'] ?? null);

            $edl->setCreatedAt(new \DateTimeImmutable());
            $edl->setUpdatedAt(new \DateTimeImmutable());

            $this->em->persist($edl);
            $this->em->flush(); // Flush to get the EDL ID for photo storage

            // Préparer le répertoire photos
            $subDirectory = 'edl-' . $edl->getId();
            $targetDir = $this->photoDirectory . '/' . $subDirectory;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            // Créer les pièces et éléments avec mapping photos
            $photoIndex = 0;
            $savedPhotos = []; // index (1-based) => chemin sauvegardé

            // Sauvegarder toutes les photos temporaires
            foreach ($tempPhotoPaths as $idx => $tempPath) {
                if (is_string($tempPath) && file_exists($tempPath)) {
                    $filename = $this->aiService->saveExtractedPhoto($tempPath, $targetDir);
                    if ($filename) {
                        // Extraire l'index réel depuis le nom de fichier (photo_N.png)
                        // pour éviter les décalages causés par sort() alphabétique
                        $photoKey = $idx + 1; // fallback pour l'ancien flow
                        if (preg_match('/photo_(\d+)\.png$/', $tempPath, $matches)) {
                            $photoKey = (int) $matches[1];
                        }
                        $savedPhotos[$photoKey] = '/uploads/photos/' . $subDirectory . '/' . $filename;
                    }
                }
            }

            // Créer les compteurs EN PREMIER (pour consommer leurs photos avant les pièces)
            foreach ($result['compteurs'] ?? [] as $compteurData) {
                if (!is_array($compteurData) || empty($compteurData['type'])) {
                    continue;
                }
                $compteur = new Compteur();
                $compteur->setEtatDesLieux($edl);
                $compteur->setType($compteurData['type']);
                $compteur->setNumero($compteurData['numero'] ?? null);
                $compteur->setIndexValue($compteurData['index'] ?? null);
                $compteur->setCommentaire($compteurData['commentaire'] ?? null);

                // Mapper les photos du compteur
                $photoIndices = $compteurData['photo_indices'] ?? [];
                if (!empty($photoIndices) && !empty($savedPhotos)) {
                    $compteurPhotos = [];
                    foreach ($photoIndices as $photoIdx) {
                        if (isset($savedPhotos[$photoIdx])) {
                            $compteurPhotos[] = $savedPhotos[$photoIdx];
                            unset($savedPhotos[$photoIdx]);
                        }
                    }
                    if (!empty($compteurPhotos)) {
                        $compteur->setPhotos($compteurPhotos);
                    }
                }

                $this->em->persist($compteur);
            }

            // Créer les clés (avant les pièces pour consommer leurs photos)
            foreach ($result['cles'] ?? [] as $cleData) {
                $cle = new Cle();
                $cle->setEtatDesLieux($edl);
                $cle->setType($this->normalizeCleType($cleData['type'] ?? 'autre'));
                $cle->setNombre($cleData['nombre'] ?? 1);
                $cle->setCommentaire($cleData['commentaire'] ?? null);

                // Mapper la photo de la clé (une seule)
                $photoIndices = $cleData['photo_indices'] ?? [];
                if (!empty($photoIndices) && !empty($savedPhotos)) {
                    $photoIdx = $photoIndices[0];
                    if (isset($savedPhotos[$photoIdx])) {
                        $cle->setPhoto($savedPhotos[$photoIdx]);
                        unset($savedPhotos[$photoIdx]);
                    }
                }

                $this->em->persist($cle);
            }

            // Créer les pièces et éléments avec mapping photos
            $ordre = 0;
            foreach ($result['pieces'] ?? [] as $pieceData) {
                $piece = new Piece();
                $piece->setEtatDesLieux($edl);
                $piece->setNom($pieceData['nom']);
                $piece->setOrdre($ordre++);
                $this->em->persist($piece);
                $this->em->flush(); // Need piece ID for elements

                $elementOrdre = 0;
                foreach ($pieceData['elements'] ?? [] as $elementData) {
                    $element = new Element();
                    $element->setPiece($piece);
                    $element->setNom($elementData['nom']);
                    $element->setType($elementData['type'] ?? 'autre');
                    $element->setEtat($elementData['etat'] ?? 'bon');
                    $element->setObservations($elementData['observations'] ?? null);
                    $element->setOrdre($elementOrdre++);
                    $this->em->persist($element);
                    $this->em->flush(); // Need element ID for photos

                    // Mapper les photos aux éléments via photo_indices
                    if (!empty($elementData['photo_indices']) && !empty($savedPhotos)) {
                        $photoOrdre = 0;
                        foreach ($elementData['photo_indices'] as $photoIdx) {
                            if (isset($savedPhotos[$photoIdx])) {
                                $photo = new Photo();
                                $photo->setElement($element);
                                $photo->setChemin($savedPhotos[$photoIdx]);
                                $photo->setOrdre($photoOrdre++);
                                $this->em->persist($photo);
                                // Remove used photo so it's not duplicated
                                unset($savedPhotos[$photoIdx]);
                            }
                        }
                    }
                }

                // Mapper les photos de la pièce (vue générale) aux photos restantes
                if (!empty($pieceData['photo_indices']) && !empty($savedPhotos)) {
                    // Créer un élément "Vue générale" pour les photos de pièce
                    $vueElement = new Element();
                    $vueElement->setPiece($piece);
                    $vueElement->setNom('Vue générale');
                    $vueElement->setType('autre');
                    $vueElement->setEtat('bon');
                    $vueElement->setOrdre($elementOrdre++);
                    $this->em->persist($vueElement);
                    $this->em->flush();

                    $photoOrdre = 0;
                    foreach ($pieceData['photo_indices'] as $photoIdx) {
                        if (isset($savedPhotos[$photoIdx])) {
                            $photo = new Photo();
                            $photo->setElement($vueElement);
                            $photo->setChemin($savedPhotos[$photoIdx]);
                            $photo->setOrdre($photoOrdre++);
                            $this->em->persist($photo);
                            unset($savedPhotos[$photoIdx]);
                        }
                    }
                }
            }

            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'État des lieux créé à partir du PDF',
                'edl' => [
                    'id' => $edl->getId(),
                    'type' => $edl->getType(),
                    'statut' => $edl->getStatut(),
                    'locataireNom' => $edl->getLocataireNom(),
                    'nbPieces' => count($result['pieces'] ?? []),
                ],
                'logement' => [
                    'id' => $logement->getId(),
                    'nom' => $logement->getNom(),
                    'isNew' => !$logementId,
                ],
                'donnees_extraites' => $result,
                'photos_saved' => count($savedPhotos) === 0 ? count($tempPhotoPaths) : count($tempPhotoPaths) - count($savedPhotos),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'import. Veuillez réessayer.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Estimation IA des coûts de réparation (utilise GPT)
     */
    #[Route('/api/ai/logements/{id}/estimations', name: 'api_ai_estimations', methods: ['GET'])]
    public function estimationsIA(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->aiService->isConfigured()) {
            return new JsonResponse(['error' => 'Service IA non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $logement = $this->em->getRepository(Logement::class)->find($id);
        if (!$logement || $logement->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Logement non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $edlEntree = $this->em->getRepository(EtatDesLieux::class)->findOneBy(
            ['logement' => $logement, 'type' => 'entree', 'statut' => ['termine', 'signe']],
            ['dateRealisation' => 'DESC']
        );

        $edlSortie = $this->em->getRepository(EtatDesLieux::class)->findOneBy(
            ['logement' => $logement, 'type' => 'sortie', 'statut' => ['termine', 'signe']],
            ['dateRealisation' => 'DESC']
        );

        if (!$edlEntree || !$edlSortie) {
            return new JsonResponse([
                'error' => 'Un état des lieux d\'entrée ET de sortie (terminé/signé) est requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $degradations = $this->estimationService->collecterDegradations($edlEntree, $edlSortie);

        if (empty($degradations)) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Aucune dégradation détectée',
                'estimations' => [],
                'total' => 0,
            ]);
        }

        try {
            $result = $this->aiService->estimerReparations($degradations);

            return new JsonResponse([
                'success' => true,
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
                'estimations_ia' => $result,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'estimation. Veuillez réessayer.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calcule les estimations de retenues pour un EDL de sortie (grille tarifaire)
     */
    #[Route('/api/ai/estimations/{edlId}', name: 'api_ai_estimations_edl', methods: ['POST'])]
    public function estimationsEdl(int $edlId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $edlSortie = $this->em->getRepository(EtatDesLieux::class)->find($edlId);
        if (!$edlSortie || $edlSortie->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $depotGarantie = $data['depot_garantie'] ?? $edlSortie->getDepotGarantie() ?? 0;

        if ($edlSortie->getType() !== 'sortie') {
            return new JsonResponse([
                'error' => 'Les estimations ne peuvent être calculées que sur un état des lieux de sortie'
            ], Response::HTTP_BAD_REQUEST);
        }

        $logement = $edlSortie->getLogement();

        $edlEntree = $this->em->getRepository(EtatDesLieux::class)->findOneBy(
            ['logement' => $logement, 'type' => 'entree'],
            ['dateRealisation' => 'DESC']
        );

        $estimations = $this->estimationService->calculerEstimations($edlEntree, $edlSortie, $depotGarantie);

        return new JsonResponse(array_merge(['success' => true], $estimations));
    }

    /**
     * Analyse toutes les dégradations d'un EDL via IA et retourne des lignes de devis détaillées
     */
    #[Route('/api/ai/analyser-degradations/{edlId}', name: 'api_ai_analyser_degradations', methods: ['POST'])]
    public function analyserDegradations(int $edlId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->aiService->isConfigured()) {
            return new JsonResponse(['error' => 'Service IA non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $edlSortie = $this->em->getRepository(EtatDesLieux::class)->find($edlId);
        if (!$edlSortie || $edlSortie->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($edlSortie->getType() !== 'sortie') {
            return new JsonResponse(['error' => 'Seul un EDL de sortie peut être analysé'], Response::HTTP_BAD_REQUEST);
        }

        $logement = $edlSortie->getLogement();
        $edlEntree = $this->em->getRepository(EtatDesLieux::class)->findOneBy(
            ['logement' => $logement, 'type' => 'entree'],
            ['dateRealisation' => 'DESC']
        );

        // Collecter les dégradations
        $degradations = $this->estimationService->collecterDegradations(
            $edlEntree ?? $edlSortie,
            $edlSortie
        );

        if (empty($degradations)) {
            return new JsonResponse([
                'success' => true,
                'lignes' => [],
                'total_estime' => 0,
                'message' => 'Aucune dégradation détectée',
            ]);
        }

        // Charger les prix de référence groupés par type
        $coutsReparation = $this->em->getRepository(CoutReparation::class)->findBy(['actif' => true]);
        $prixParType = [];
        foreach ($coutsReparation as $cout) {
            $prixParType[$cout->getTypeElement()][] = [
                'nom' => $cout->getNom(),
                'unite' => $cout->getUnite(),
                'prix_unitaire' => $cout->getPrixUnitaire(),
            ];
        }

        $lignes = [];
        $totalEstime = 0;

        foreach ($degradations as $deg) {
            // Trouver la 1ère photo de l'élément (si disponible)
            $imagePath = null;
            if (!empty($deg['photos'])) {
                $chemin = $deg['photos'][0]['chemin'] ?? null;
                if ($chemin) {
                    $fullPath = $this->photoDirectory . '/' . ltrim($chemin, '/uploads/photos/');
                    if (file_exists($fullPath)) {
                        $imagePath = $fullPath;
                    }
                }
            }

            $prixRef = $prixParType[$deg['type']] ?? [];

            try {
                $analyse = $this->aiService->analyserDegradationPourDevis(
                    $imagePath,
                    $deg['element'],
                    $deg['type'],
                    $deg['etat_entree'] ?? 'bon',
                    $deg['etat_sortie'],
                    $deg['observations'] ?? '',
                    $prixRef
                );

                if ($analyse && !empty($analyse['degradations'])) {
                    foreach ($analyse['degradations'] as $item) {
                        $quantite = (float) ($item['quantite_estimee'] ?? 1);
                        $prixUnitaire = (float) ($item['prix_unitaire_estime'] ?? 0);
                        $total = round($quantite * $prixUnitaire, 2);

                        $lignes[] = [
                            'piece' => $deg['piece'],
                            'element' => $deg['element'],
                            'description' => $item['reparation'] ?? $item['description'] ?? 'Réparation',
                            'unite' => $item['unite'] ?? 'forfait',
                            'quantite' => $quantite,
                            'prix_unitaire' => $prixUnitaire,
                            'total' => $total,
                        ];

                        $totalEstime += $total;
                    }
                }
            } catch (\Exception $e) {
                // En cas d'échec IA pour un élément, créer une ligne basique
                $intervention = $this->estimationService->determinerIntervention($deg['etat_sortie']);
                $coutBrut = EstimationService::TARIFS_ELEMENTS[$deg['type']][$intervention] ?? 80;

                $lignes[] = [
                    'piece' => $deg['piece'],
                    'element' => $deg['element'],
                    'description' => ucfirst($intervention) . ' - ' . ($deg['observations'] ?? 'Dégradation constatée'),
                    'unite' => 'forfait',
                    'quantite' => 1,
                    'prix_unitaire' => (float) $coutBrut,
                    'total' => (float) $coutBrut,
                ];
                $totalEstime += $coutBrut;
            }
        }

        return new JsonResponse([
            'success' => true,
            'lignes' => $lignes,
            'total_estime' => round($totalEstime, 2),
        ]);
    }

    /**
     * Convertit une image uploadée ou une URL en base64
     */
    private function getImageBase64(?UploadedFile $photo, ?string $imageUrl): ?string
    {
        if ($photo) {
            $imageData = base64_encode(file_get_contents($photo->getPathname()));
            $mimeType = $photo->getMimeType() ?: 'image/jpeg';
            return "data:$mimeType;base64,$imageData";
        }

        return $imageUrl;
    }

    /**
     * Normaliser le type de clé depuis les labels français vers les codes snake_case
     */
    private function normalizeCleType(string $type): string
    {
        $mapping = [
            "Porte d'entrée" => 'porte_entree',
            "porte d'entrée" => 'porte_entree',
            "Porte d'entrée" => 'porte_entree',
            'Boîte aux lettres' => 'boite_lettres',
            'boîte aux lettres' => 'boite_lettres',
            'Cave' => 'cave',
            'cave' => 'cave',
            'Garage' => 'garage',
            'garage' => 'garage',
            'Parking' => 'parking',
            'parking' => 'parking',
            'Local vélo' => 'local_velo',
            'local vélo' => 'local_velo',
            'Portail' => 'portail',
            'portail' => 'portail',
            'Interphone' => 'interphone',
            'interphone' => 'interphone',
            'Badge' => 'badge',
            'badge' => 'badge',
            'Vigik' => 'vigik',
            'vigik' => 'vigik',
            'Digicode' => 'digicode',
            'digicode' => 'digicode',
            'Télécommande' => 'telecommande',
            'télécommande' => 'telecommande',
            'Parties communes' => 'parties_communes',
            'parties communes' => 'parties_communes',
        ];

        // Already in snake_case format
        $validTypes = ['porte_entree', 'boite_lettres', 'cave', 'garage', 'parking',
            'local_velo', 'portail', 'interphone', 'badge', 'vigik', 'digicode',
            'telecommande', 'parties_communes', 'autre'];

        if (in_array($type, $validTypes)) {
            return $type;
        }

        return $mapping[$type] ?? 'autre';
    }

    /**
     * Normaliser le type de logement depuis les valeurs IA vers les types valides
     * Convertit les typologies (f1, f2, t3...) en types de logement (appartement, maison...)
     */
    private function normalizeLogementType(?string $type): ?string
    {
        if (!$type) {
            return null;
        }

        $validTypes = ['appartement', 'maison', 'studio', 'loft', 'chambre', 'commerce', 'bureau', 'parking', 'autre'];

        $lower = strtolower(trim($type));

        // Déjà un type valide
        if (in_array($lower, $validTypes)) {
            return $lower;
        }

        // Typologies → type de logement
        if ($lower === 'studio') {
            return 'studio';
        }
        if (preg_match('/^(f|t)\d$/i', $lower)) {
            return 'appartement';
        }
        if (preg_match('/^maison/i', $lower)) {
            return 'maison';
        }

        return null;
    }
}
