<?php

namespace App\Service;

use GuzzleHttp\Client as GuzzleClient;
use OpenAI;
use Psr\Log\LoggerInterface;

class AIService
{
    private $client;
    private string $model = 'gpt-4o';

    // Constantes pour l'extraction de photos
    private const MAX_PAGES = 20;

    public function __construct(
        private string $openaiApiKey,
        private LoggerInterface $logger
    ) {
        if (!empty($this->openaiApiKey)) {
            $this->client = OpenAI::factory()
                ->withApiKey($this->openaiApiKey)
                ->withHttpClient(new GuzzleClient(['timeout' => 180]))
                ->make();
        }
    }

    /**
     * Analyse une photo de pièce et détecte les éléments présents
     */
    public function analyserPhotoPiece(string $imageBase64OrUrl, ?string $nomPiece = null): array
    {
        if (!$this->client) {
            throw new \RuntimeException('OpenAI API key not configured');
        }

        $prompt = $this->buildPromptAnalysePhoto($nomPiece);
        $imageContent = $this->buildImageContent($imageBase64OrUrl);

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un expert en états des lieux immobiliers. Tu analyses des photos de pièces et identifies les éléments présents avec leur état. Réponds uniquement en JSON valide.',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        $imageContent,
                    ],
                ],
            ],
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object'],
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Analyse une photo d'élément et détecte les dégradations
     */
    public function analyserPhotoDegradation(string $imageBase64OrUrl, string $typeElement, ?string $nomElement = null): array
    {
        if (!$this->client) {
            throw new \RuntimeException('OpenAI API key not configured');
        }

        $prompt = $this->buildPromptDegradation($typeElement, $nomElement);
        $imageContent = $this->buildImageContent($imageBase64OrUrl);

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un expert en états des lieux immobiliers. Tu analyses des photos d\'éléments et identifies les dégradations. Réponds uniquement en JSON valide.',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        $imageContent,
                    ],
                ],
            ],
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object'],
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Parse un PDF d'état des lieux : convertit en images, extrait les photos, analyse avec IA
     * Pipeline robuste aligné sur la version web
     */
    public function analyserPdfEdl(string $pdfPath): array
    {
        if (!$this->client) {
            throw new \RuntimeException('OpenAI API key not configured');
        }

        $t0 = microtime(true);

        // 1. Extraction texte (instantané)
        $pdfText = $this->extractTextFromPdf($pdfPath);
        $useTextMode = mb_strlen($pdfText) > 200;

        $t1 = microtime(true);
        $this->logger->info('Import PDF - Mode', [
            'mode' => $useTextMode ? 'texte' : 'images',
            'text_length' => mb_strlen($pdfText),
            'duree_ms' => round(($t1 - $t0) * 1000),
        ]);

        // 2. Compter les photos candidates via pdfimages -list (instantané, ~0s)
        $photoCandidateCount = $this->countPhotoCandidates($pdfPath);

        // 3. Lancer pdfimages -png en arrière-plan (si candidats)
        //    Ça tourne pendant que l'API travaille → on gagne ~30s
        $photoProcess = null;
        $photoTempDir = null;
        if ($photoCandidateCount > 0) {
            $photoTempDir = sys_get_temp_dir() . '/edlya_photos_' . uniqid();
            mkdir($photoTempDir, 0755, true);
            $cmd = sprintf(
                'pdfimages -png %s %s/photo',
                escapeshellarg($pdfPath),
                escapeshellarg($photoTempDir)
            );
            $photoProcess = proc_open($cmd, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);
            // Fermer les pipes stdout/stderr (on n'en a pas besoin)
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->logger->info('Import PDF - pdfimages lancé en arrière-plan', [
                'candidates' => $photoCandidateCount,
            ]);
        } else {
            $this->logger->info('Import PDF - Aucune photo candidate, skip pdfimages');
        }

        // 4. Détecter le logiciel source
        $sourceFormat = $this->detectSourceFormat($pdfPath);

        // 5. Construire les messages et appeler l'API (parallèle avec pdfimages)
        $systemMessage = $this->buildSystemMessageForPdfImport($photoCandidateCount);
        $pageImages = [];

        if ($useTextMode) {
            $userContent = $this->buildUserContentForTextImport($pdfText, $photoCandidateCount, $sourceFormat);
            $model = $this->model;
        } else {
            $pageImages = $this->convertPdfToImages($pdfPath);
            if (empty($pageImages)) {
                throw new \RuntimeException('Impossible de convertir le PDF en images.');
            }
            $userContent = $this->buildUserContentForPdfImport($pageImages, $photoCandidateCount, $sourceFormat);
            $model = $this->model;
        }

        $t3 = microtime(true);
        $this->logger->info('Import PDF - Appel API', ['model' => $model]);
        $data = $this->callApiWithRetry($systemMessage, $userContent, $model);

        $t4 = microtime(true);
        $this->logger->info('Import PDF - Réponse reçue', [
            'duree_api_ms' => round(($t4 - $t3) * 1000),
        ]);

        // 6. Récupérer les photos extraites (pdfimages a eu le temps de finir pendant l'API)
        $extractedPhotos = [];
        if ($photoProcess !== null) {
            // Attendre la fin de pdfimages (normalement déjà fini car API prend plus longtemps)
            $exitCode = proc_close($photoProcess);
            $t5 = microtime(true);
            $this->logger->info('Import PDF - pdfimages terminé', [
                'exit_code' => $exitCode,
                'duree_totale_ms' => round(($t5 - $t1) * 1000),
                'temps_attente_apres_api_ms' => round(($t5 - $t4) * 1000),
            ]);

            if ($exitCode === 0 && $photoTempDir) {
                $extractedPhotos = $this->filterExtractedPhotos($photoTempDir);
            }
        }

        $this->logger->info('Import PDF - Terminé', [
            'photos' => count($extractedPhotos),
            'duree_totale_ms' => round((microtime(true) - $t0) * 1000),
        ]);

        // 7. Nettoyer les images de pages temporaires
        foreach ($pageImages as $imagePath) {
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }

        // 8. Ajouter les photos extraites au résultat
        $data['_extracted_photos'] = $extractedPhotos;

        return $data;
    }

    /**
     * Extraire le texte du PDF via pdftotext
     */
    private function extractTextFromPdf(string $pdfPath): string
    {
        $command = sprintf('pdftotext -layout %s - 2>/dev/null', escapeshellarg($pdfPath));
        $output = @shell_exec($command);

        return trim($output ?? '');
    }

    /**
     * Construire le contenu user pour le mode texte (beaucoup plus rapide)
     */
    private function buildUserContentForTextImport(string $pdfText, int $photoCount, ?string $sourceFormat): array
    {
        $instruction = "Analyse ce texte extrait d'un PDF d'état des lieux et extrais toutes les données au format JSON selon le schéma défini.";

        if ($sourceFormat) {
            $instruction .= "\nCe document provient du logiciel {$sourceFormat}.";
        }

        if ($photoCount > 0) {
            $instruction .= "\n{$photoCount} photos ont été extraites du PDF (numérotées de 1 à {$photoCount}). Associe chaque photo au bon élément via photo_indices en te basant sur le contexte.";
        }

        // Limiter le texte à ~30000 caractères pour éviter de dépasser les limites de tokens
        $text = mb_strlen($pdfText) > 30000 ? mb_substr($pdfText, 0, 30000) . "\n[... texte tronqué]" : $pdfText;

        return [
            [
                'type' => 'text',
                'text' => $instruction . "\n\n--- CONTENU DU PDF ---\n\n" . $text,
            ],
        ];
    }

    /**
     * Améliore une observation pour la rendre professionnelle (max 100 chars)
     */
    public function ameliorerObservation(string $element, string $etat, ?string $observation, array $degradations = []): ?string
    {
        if (!$this->client) {
            return null;
        }

        $etatLabels = [
            'neuf' => 'Neuf',
            'tres_bon' => 'Très bon',
            'bon' => 'Bon',
            'usage' => 'Usagé',
            'mauvais' => 'Mauvais',
            'hors_service' => 'Hors service',
        ];

        $prompt = "Tu es un expert en états des lieux immobiliers. Rédige une observation professionnelle et concise pour un élément d'état des lieux.

            Élément : {$element}
            État : " . ($etatLabels[$etat] ?? $etat) . "
            " . (!empty($degradations) ? "Dégradations constatées : " . implode(', ', $degradations) : "") . "
            " . (!empty($observation) ? "Observation actuelle (à améliorer) : {$observation}" : "") . "

            Règles :
            - Maximum 100 caractères
            - Style professionnel et factuel
            - Ne pas utiliser de formules de politesse
            - Décrire l'état de manière objective
            - Si des dégradations sont mentionnées, les intégrer naturellement

            Réponds uniquement avec l'observation améliorée, sans guillemets ni explication.";

        try {
            $response = $this->client->chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 150,
            ]);

            return trim($response->choices[0]->message->content ?? '');
        } catch (\Exception $e) {
            $this->logger->error('Erreur ameliorerObservation', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Estime le coût de réparation basé sur les dégradations détectées
     */
    public function estimerReparations(array $degradations): array
    {
        if (!$this->client) {
            throw new \RuntimeException('OpenAI API key not configured');
        }

        $prompt = $this->buildPromptEstimation($degradations);

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un expert en estimation de travaux immobiliers en France. Tu fournis des estimations de coûts de réparation réalistes basées sur les prix du marché 2024. Réponds uniquement en JSON valide.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object'],
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Analyse une dégradation (avec photo optionnelle) pour générer une ligne de devis détaillée
     */
    public function analyserDegradationPourDevis(
        ?string $imagePath,
        string $elementNom,
        string $elementType,
        string $etatEntree,
        string $etatSortie,
        ?string $observations,
        array $prixReference = []
    ): ?array {
        if (!$this->client) {
            return null;
        }

        $prixContext = '';
        if (!empty($prixReference)) {
            $prixContext = "\n\nPrix de référence pour le type \"{$elementType}\" :\n";
            foreach ($prixReference as $ref) {
                $prixContext .= "- {$ref['nom']} : {$ref['prix_unitaire']}€/{$ref['unite']}\n";
            }
        }

        $prompt = <<<PROMPT
Tu es un expert en estimation de travaux immobiliers pour états des lieux.

Analyse cette dégradation et propose des lignes de devis détaillées.

Élément : {$elementNom} (type: {$elementType})
État entrée : {$etatEntree}
État sortie : {$etatSortie}
Observations : {$observations}
{$prixContext}

Réponds en JSON avec cette structure exacte :
{
  "degradations": [
    {
      "description": "Description précise de la réparation à effectuer",
      "gravite": "legere|moyenne|importante|critique",
      "reparation": "Type d'intervention détaillé",
      "unite": "m2|unite|ml|forfait",
      "quantite_estimee": 1,
      "prix_unitaire_estime": 0.00
    }
  ],
  "commentaire_general": "Résumé de l'état et des travaux nécessaires"
}

Règles :
- Utilise les prix de référence fournis quand disponibles
- Estime des quantités réalistes (surface murale ~10-15m² par pièce, etc.)
- Sois précis dans les descriptions
- Si l'état est juste "usage", propose des interventions légères (nettoyage, retouches)
- Si "mauvais" ou "hors_service", propose des interventions lourdes (remplacement, réfection)
PROMPT;

        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'Tu es un expert en estimation de travaux immobiliers en France. Tu produis des devis détaillés et réalistes. Réponds uniquement en JSON valide.',
                ],
            ];

            $userContent = [];
            $userContent[] = ['type' => 'text', 'text' => $prompt];

            if ($imagePath && file_exists($imagePath)) {
                $imageData = base64_encode(file_get_contents($imagePath));
                $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
                $userContent[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => "data:{$mimeType};base64,{$imageData}"],
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $userContent];

            // Use vision model only if we have an image, otherwise text model is faster
            $model = $imagePath ? $this->model : $this->model;

            $response = $this->client->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 1500,
                'response_format' => ['type' => 'json_object'],
            ]);

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Erreur analyserDegradationPourDevis', ['message' => $e->getMessage()]);
            return null;
        }
    }

    // ========================================================================
    //  Pipeline d'import PDF (méthodes privées)
    // ========================================================================

    /**
     * Convertir les pages du PDF en images via pdftoppm
     */
    private function convertPdfToImages(string $pdfPath): array
    {
        $tempDir = sys_get_temp_dir() . '/edlya_pdf_' . uniqid();

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $outputPrefix = $tempDir . '/page';
        $command = sprintf(
            'pdftoppm -jpeg -jpegopt quality=60 -r 150 %s %s 2>&1',
            escapeshellarg($pdfPath),
            escapeshellarg($outputPrefix)
        );

        exec($command, $output, $returnCode);

        // Fallback : ImageMagick convert
        if ($returnCode !== 0) {
            $command = sprintf(
                'convert -density 150 -quality 60 %s %s/page-%%03d.jpg 2>&1',
                escapeshellarg($pdfPath),
                escapeshellarg($tempDir)
            );
            exec($command, $output, $returnCode);
        }

        $files = glob($tempDir . '/*.{png,jpg}', GLOB_BRACE);
        sort($files);

        return array_slice($files, 0, self::MAX_PAGES);
    }

    /**
     * Compter les photos candidates via pdfimages -list (instantané, ~0s)
     */
    private function countPhotoCandidates(string $pdfPath): int
    {
        $listCommand = sprintf('pdfimages -list %s 2>/dev/null', escapeshellarg($pdfPath));
        $listOutput = @shell_exec($listCommand);

        $count = 0;
        if ($listOutput) {
            $lines = explode("\n", trim($listOutput));
            foreach (array_slice($lines, 2) as $line) {
                $cols = preg_split('/\s+/', trim($line));
                if (count($cols) < 7) continue;

                $width = (int) $cols[3];
                $height = (int) $cols[4];

                if ($width >= 200 && $height >= 200 && $width < 3000 && $height < 3000) {
                    $ratio = $height > 0 ? $width / $height : 0;
                    if ($ratio >= 0.5 && $ratio <= 2.0 && abs($ratio - 1.0) > 0.05) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Filtrer les photos extraites par pdfimages dans un répertoire
     */
    private function filterExtractedPhotos(string $tempDir): array
    {
        $files = glob($tempDir . '/*.png');
        sort($files);

        $photos = [];
        foreach ($files as $file) {
            $imageInfo = @getimagesize($file);
            $fileSize = filesize($file);

            if (!$imageInfo) {
                @unlink($file);
                continue;
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $pixels = $width * $height;
            $ratio = $height > 0 ? $width / $height : 0;
            $bytesPerPixel = $pixels > 0 ? $fileSize / $pixels : 0;

            $isValidSize = $width >= 200 && $height >= 200;
            $isNotTooLarge = $width < 3000 && $height < 3000;
            $isValidRatio = $ratio >= 0.5 && $ratio <= 2.0;
            $isNotTooSmallFile = $fileSize > 10000;
            $isRealPhoto = $bytesPerPixel > 0.15;
            $isNotSquare = abs($ratio - 1.0) > 0.05;

            if ($isValidSize && $isNotTooLarge && $isValidRatio && $isNotTooSmallFile && $isRealPhoto && $isNotSquare) {
                $photos[] = [
                    'path' => $file,
                    'width' => $width,
                    'height' => $height,
                ];
            } else {
                @unlink($file);
            }
        }

        return $photos;
    }

    /**
     * Détecter le logiciel source du PDF via pdftotext
     */
    private function detectSourceFormat(string $pdfPath): ?string
    {
        $command = sprintf('pdftotext -l 2 %s - 2>/dev/null', escapeshellarg($pdfPath));
        $output = @shell_exec($command);

        if (!$output) {
            return null;
        }

        $output = mb_strtolower($output);

        $formats = [
            'homepad' => 'Homepad',
            'immopad' => 'Immopad',
            'startloc' => 'Startloc',
            'edlsoft' => 'EDLSoft',
            'chapps' => 'Chapps',
            'clic & go' => 'Clic & Go',
            'onedl' => 'OneDL',
            'check & visit' => 'Check & Visit',
            'igloo' => 'Igloo',
        ];

        foreach ($formats as $keyword => $name) {
            if (str_contains($output, $keyword)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * System message complet pour l'import PDF (persona + schéma + règles + few-shot)
     */
    private function buildSystemMessageForPdfImport(int $photoCount = 0): string
    {
        return <<<'SYSTEM'
Tu es un expert en extraction de données depuis des PDF d'états des lieux immobiliers français.
Tu dois analyser chaque page du document et produire un JSON structuré complet.

═══════════════════════════════════════════
SCHÉMA JSON À PRODUIRE
═══════════════════════════════════════════

Retourne UNIQUEMENT un objet JSON valide (sans ```json, sans commentaires) :

{
    "type_edl": "entree" ou "sortie",
    "date_realisation": "YYYY-MM-DD",
    "logement": {
        "nom": "Type du bien (Appartement T3, Studio meublé, Maison, etc.)",
        "adresse": "Numéro et rue UNIQUEMENT - SANS code postal ni ville",
        "code_postal": "Code postal (ex: 33480)",
        "ville": "Nom de la ville",
        "type": "studio|f1|f2|f3|f4|f5|maison|autre",
        "surface": nombre en m² ou null,
        "etage": null ou nombre
    },
    "locataire": {
        "nom": "Prénom et Nom du locataire",
        "email": "email ou null",
        "telephone": "numéro ou null"
    },
    "bailleur": {
        "nom": "Nom du bailleur/propriétaire/agence"
    },
    "pieces": [
        {
            "nom": "Nom exact de la pièce",
            "observations": "Observations générales ou null",
            "photo_indices": [],
            "elements": [
                {
                    "nom": "Nom exact de l'élément",
                    "type": "sol|mur|plafond|menuiserie|electricite|plomberie|chauffage|equipement|mobilier|electromenager|autre",
                    "etat": "neuf|tres_bon|bon|usage|mauvais|hors_service",
                    "observations": "COPIER MOT POUR MOT",
                    "photo_indices": []
                }
            ]
        }
    ],
    "compteurs": [
        {
            "type": "electricite|eau_froide|eau_chaude|gaz",
            "numero": "numéro si mentionné ou null",
            "index": "valeur de l'index ou null",
            "commentaire": "commentaire ou null",
            "photo_indices": []
        }
    ],
    "cles": [
        {"type": "porte_entree|parties_communes|boite_lettres|cave|garage|parking|local_velo|portail|interphone|badge|telecommande|vigik|digicode|autre", "nombre": 2, "commentaire": null, "photo_indices": []}
    ],
    "observations_generales": "Observations générales ou null"
}

═══════════════════════════════════════════
ÉTATS : 6 NIVEAUX (très important)
═══════════════════════════════════════════

Utilise EXACTEMENT ces 6 valeurs pour le champ "etat" :
- "neuf" → Neuf, N, Excellent, Parfait état, état neuf
- "tres_bon" → Très bon état, TB, TBE, Très bon, Bon état d'entretien
- "bon" → Bon état, Bon, B, BE, Correct, Normal, RAS, Satisfaisant, État d'usage normal
- "usage" → Usagé, U, Usure, Usage normal, Traces d'usure, État moyen, Passable, État d'usage
- "mauvais" → Mauvais, M, ME, Mauvais état, Dégradé, Abîmé, Détérioré, Vétuste
- "hors_service" → Hors service, HS, À remplacer, Hors d'usage, Non fonctionnel, Cassé

Formats spéciaux courants dans les logiciels EDL :
- Cases à cocher ☑/☐ avec colonnes N/B/U/M → N=neuf, B=bon, U=usage, M=mauvais
- Échelle 1-4 : 1=neuf, 2=bon, 3=usage, 4=mauvais
- Échelle 1-6 : 1=neuf, 2=tres_bon, 3=bon, 4=usage, 5=mauvais, 6=hors_service
- Si aucun état n'est indiqué → "bon"

═══════════════════════════════════════════
TYPES D'ÉLÉMENTS
═══════════════════════════════════════════

- sol : parquet, carrelage, moquette, lino, vinyl, tomette, stratifié
- mur : murs, peinture, papier peint, crépi, faïence murale, lambris
- plafond : plafond, faux plafond, corniche
- menuiserie : fenêtre, porte, volet, placard, porte-fenêtre, store, vitrage, serrure, poignée
- electricite : prises, interrupteurs, luminaires, tableau électrique, spots, appliques, détecteur de fumée
- plomberie : lavabo, douche, baignoire, WC, robinet, évier, siphon, tuyauterie, chasse d'eau, mitigeur
- chauffage : radiateur, chaudière, convecteur, thermostat, climatisation, VMC, sèche-serviettes
- equipement : plan de travail, hotte, plaques, four, crédence, étagères, miroir, barre de seuil
- mobilier : lit, table, chaise, canapé, armoire, commode, bureau, bibliothèque
- electromenager : réfrigérateur, lave-linge, lave-vaisselle, micro-ondes, sèche-linge, congélateur
- autre : tout ce qui ne rentre pas dans les catégories ci-dessus

═══════════════════════════════════════════
RÈGLES D'EXTRACTION
═══════════════════════════════════════════

1. Lis CHAQUE PAGE du document attentivement
2. Extrais TOUTES les observations mot pour mot - ne résume JAMAIS
3. Si un élément a une observation ("RAS", "OK", "Bon état", etc.), elle DOIT apparaître
4. Cherche les observations dans les colonnes : Observations, Remarques, Commentaires, Description, État
5. ADRESSE : sépare toujours numéro+rue / code postal / ville
6. COMPTEURS :
   - Numéro = N°, matricule, PDL, PCE, référence
   - Index = relevé, consommation. Si "non relevé" → null
   - "EAU" sans précision = eau_froide
   - Index composite → format texte : "HP : 7548 kWh, HC : 9808 kWh"
7. CLÉS : section "REMISE/RESTITUTION DES CLÉS" → type + nombre + commentaire
8. PHOTOS : associe les "Photo X" mentionnées dans le texte aux bons éléments via photo_indices
9. Si un tableau est coupé entre 2 pages, FUSIONNER les données dans la même pièce
10. DATE : toujours en format YYYY-MM-DD

═══════════════════════════════════════════
FORMATS DE LOGICIELS CONNUS
═══════════════════════════════════════════

Homepad/Immopad : Tableaux avec colonnes Désignation | Nature/Type | État | Observations. Photos légendées en bas de page.
Startloc : Cases à cocher ☑ pour l'état (N/B/U/M). Observations dans colonne séparée.
EDLSoft : Format texte structuré avec états entre parenthèses. Compteurs en fin de document.
Chapps/OneDL : Format mixte tableau + texte libre.

═══════════════════════════════════════════
EXEMPLES (few-shot)
═══════════════════════════════════════════

--- Exemple 1 : Format tableau classique ---
Entrée (extrait de tableau) :
| Désignation | Nature | État | Observations |
|-------------|--------|------|-------------|
| Sol | Carrelage | Bon état | RAS |
| Murs | Peinture blanche | Traces d'usure | Traces au-dessus radiateur |
| Plafond | Peinture | Bon | - |
| Porte | Bois | Bon état | Poignée légèrement rayée |

Sortie attendue (extrait) :
{"nom": "Sol", "type": "sol", "etat": "bon", "observations": "RAS", "photo_indices": []},
{"nom": "Murs", "type": "mur", "etat": "usage", "observations": "Traces au-dessus radiateur", "photo_indices": []},
{"nom": "Plafond", "type": "plafond", "etat": "bon", "observations": null, "photo_indices": []},
{"nom": "Porte", "type": "menuiserie", "etat": "bon", "observations": "Poignée légèrement rayée", "photo_indices": []}

--- Exemple 2 : Format cases à cocher ---
Entrée (extrait) :
Élément          | N | B | U | M | Observations
Parquet          |   | ☑ |   |   | Quelques rayures superficielles
Peinture murs    |   |   | ☑ |   | Traces de fixation, trous de chevilles
Fenêtre PVC      | ☑ |   |   |   |
Prises électriques|   | ☑ |   |   | 4 prises dont 1 sans cache

Sortie attendue (extrait) :
{"nom": "Parquet", "type": "sol", "etat": "bon", "observations": "Quelques rayures superficielles", "photo_indices": []},
{"nom": "Peinture murs", "type": "mur", "etat": "usage", "observations": "Traces de fixation, trous de chevilles", "photo_indices": []},
{"nom": "Fenêtre PVC", "type": "menuiserie", "etat": "neuf", "observations": null, "photo_indices": []},
{"nom": "Prises électriques", "type": "electricite", "etat": "bon", "observations": "4 prises dont 1 sans cache", "photo_indices": []}

--- Exemple 3 : Compteurs ---
Entrée (extrait) :
COMPTEURS ET RELEVÉS
Électricité - PDL : 16174095495231
Index HP : 7 548 kWh / HC : 9 808 kWh

Sortie attendue (extrait) :
[{"type": "electricite", "numero": "16174095495231", "index": "HP : 7548 kWh, HC : 9808 kWh", "commentaire": null, "photo_indices": []}]

═══════════════════════════════════════════

IMPORTANT : Produis un JSON COMPLET et VALIDE. Ne tronque jamais la réponse. Assure-toi que toutes les accolades et crochets sont correctement fermés.
SYSTEM;
    }

    /**
     * Construire le contenu user (images des pages + instruction courte)
     */
    private function buildUserContentForPdfImport(array $images, int $photoCount, ?string $sourceFormat): array
    {
        $content = [];

        foreach ($images as $imagePath) {
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = str_ends_with($imagePath, '.png') ? 'image/png' : 'image/jpeg';
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$mimeType};base64," . $imageData,
                    'detail' => 'low',
                ],
            ];
        }

        $instruction = "Analyse ce PDF d'état des lieux et extrais toutes les données au format JSON selon le schéma défini.";

        if ($sourceFormat) {
            $instruction .= "\nCe document provient du logiciel {$sourceFormat}.";
        }

        if ($photoCount > 0) {
            $instruction .= "\n{$photoCount} photos ont été extraites du PDF (numérotées de 1 à {$photoCount}). Associe chaque photo au bon élément, compteur ou clé via photo_indices.";
        }

        $content[] = [
            'type' => 'text',
            'text' => $instruction,
        ];

        return $content;
    }

    /**
     * Appel API avec retry (max 2 tentatives)
     */
    private function callApiWithRetry(string $systemMessage, array $userContent, string $model): array
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $messages = [
                    ['role' => 'user', 'content' => $userContent],
                ];

                // En cas de retry, ajouter un message de relance
                if ($attempt > 1) {
                    $this->logger->warning('Import PDF - Retry tentative ' . $attempt, ['error' => $lastError]);
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => "Je vais réessayer l'extraction en produisant un JSON complet et valide.",
                    ];
                    $messages[] = [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => "La réponse précédente était invalide ({$lastError}). Produis un JSON complet et valide. Assure-toi que toutes les pièces et éléments sont inclus, et que le JSON se termine correctement avec toutes les accolades/crochets fermants.",
                            ],
                        ],
                    ];
                }

                $this->logger->info('Import PDF - Appel API', ['model' => $model, 'attempt' => $attempt]);

                $response = $this->client->chat()->create([
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemMessage],
                        ...$messages,
                    ],
                    'max_tokens' => 16000,
                    'response_format' => ['type' => 'json_object'],
                ]);

                $content = $response->choices[0]->message->content;
                $finishReason = $response->choices[0]->finishReason ?? 'unknown';
                $usage = $response->usage;

                $this->logger->info('Import PDF - Réponse reçue', [
                    'attempt' => $attempt,
                    'finish_reason' => $finishReason,
                    'prompt_tokens' => $usage->promptTokens ?? 0,
                    'completion_tokens' => $usage->completionTokens ?? 0,
                    'content_length' => mb_strlen($content ?? ''),
                ]);

                if ($finishReason === 'length') {
                    throw new \RuntimeException('JSON tronqué: max_tokens atteint (' . ($usage->completionTokens ?? '?') . ' tokens)');
                }

                $data = $this->parseJsonString($content);

                // Vérifier que le résultat est complet
                if (empty($data['pieces']) && empty($data['logement'])) {
                    throw new \RuntimeException('JSON incomplet: pas de pièces ni de logement');
                }

                $this->logger->info('Import PDF - Extraction réussie', ['attempt' => $attempt]);
                return $data;

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $this->logger->warning('Import PDF - Tentative ' . $attempt . ' échouée', ['error' => $lastError]);

                if ($attempt >= 2) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Échec de l\'extraction après 2 tentatives: ' . $lastError);
    }

    /**
     * Sauvegarder une photo extraite dans le répertoire cible et retourner le nom du fichier
     */
    public function saveExtractedPhoto(string $tempPath, string $targetDirectory): ?string
    {
        if (!file_exists($tempPath)) {
            return null;
        }

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $filename = uniqid() . '_imported.png';
        $targetPath = $targetDirectory . '/' . $filename;

        if (copy($tempPath, $targetPath)) {
            @unlink($tempPath);
            return $filename;
        }

        return null;
    }

    /**
     * Nettoyer les photos temporaires
     */
    public function cleanupTempPhotos(array $photos): void
    {
        foreach ($photos as $photo) {
            if (isset($photo['path']) && file_exists($photo['path'])) {
                @unlink($photo['path']);
            }
        }

        // Nettoyer les dossiers temp vides
        $tempDirs = glob(sys_get_temp_dir() . '/edlya_photos_*', GLOB_ONLYDIR);
        foreach ($tempDirs as $dir) {
            if (is_dir($dir) && count(glob($dir . '/*')) === 0) {
                @rmdir($dir);
            }
        }
    }

    // ========================================================================
    //  Méthodes utilitaires
    // ========================================================================

    private function buildImageContent(string $imageBase64OrUrl): array
    {
        if (str_starts_with($imageBase64OrUrl, 'http')) {
            return [
                'type' => 'image_url',
                'image_url' => ['url' => $imageBase64OrUrl],
            ];
        }

        if (!str_starts_with($imageBase64OrUrl, 'data:image')) {
            $imageBase64OrUrl = 'data:image/jpeg;base64,' . $imageBase64OrUrl;
        }

        return [
            'type' => 'image_url',
            'image_url' => ['url' => $imageBase64OrUrl],
        ];
    }

    private function parseResponse($response): array
    {
        $content = $response->choices[0]->message->content;
        return $this->parseJsonString($content);
    }

    private function parseJsonString(string $text): array
    {
        $text = trim($text);

        // Retirer les blocs markdown ```json ... ```
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $matches)) {
            $text = $matches[1];
        }

        $result = json_decode(trim($text), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Réponse JSON invalide: ' . json_last_error_msg());
        }

        return $result;
    }

    private function buildPromptAnalysePhoto(?string $nomPiece): string
    {
        $pieceContext = $nomPiece ? "Cette photo représente : $nomPiece." : '';

        return <<<PROMPT
Analyse cette photo d'une pièce d'un logement pour un état des lieux.
$pieceContext

Identifie tous les éléments visibles et leur état apparent.

Réponds en JSON avec cette structure exacte :
{
  "piece_detectee": "nom de la pièce si identifiable",
  "elements": [
    {
      "nom": "nom de l'élément",
      "type": "sol|mur|plafond|menuiserie|electricite|plomberie|chauffage|equipement|mobilier|electromenager|autre",
      "etat": "neuf|tres_bon|bon|usage|mauvais|hors_service",
      "observations": "description de l'état observé",
      "degradations": ["liste des dégradations visibles si applicable"]
    }
  ],
  "observations_generales": "remarques générales sur la pièce",
  "confiance": 0.0-1.0
}
PROMPT;
    }

    private function buildPromptDegradation(string $typeElement, ?string $nomElement): string
    {
        $context = $nomElement ? "Élément : $nomElement ($typeElement)" : "Type d'élément : $typeElement";

        return <<<PROMPT
Analyse cette photo d'un élément pour détecter les dégradations.
$context

Réponds en JSON avec cette structure exacte :
{
  "etat_global": "neuf|tres_bon|bon|usage|mauvais|hors_service",
  "degradations_detectees": [
    {
      "type": "nom de la dégradation",
      "severite": "legere|moyenne|importante|critique",
      "localisation": "où sur l'élément",
      "description": "description détaillée"
    }
  ],
  "estimation_reparation": {
    "necessaire": true/false,
    "type_intervention": "nettoyage|reparation|remplacement",
    "cout_estime_min": 0,
    "cout_estime_max": 0
  },
  "observations": "remarques complémentaires",
  "confiance": 0.0-1.0
}
PROMPT;
    }

    private function buildPromptEstimation(array $degradations): string
    {
        $degradationsJson = json_encode($degradations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Estime le coût de réparation pour ces dégradations constatées lors d'un état des lieux en France.

Dégradations à estimer :
$degradationsJson

Réponds en JSON avec cette structure exacte :
{
  "estimations": [
    {
      "element": "nom de l'élément",
      "piece": "nom de la pièce",
      "degradation": "description",
      "intervention": "type d'intervention recommandée",
      "cout_min": 0,
      "cout_max": 0,
      "cout_moyen": 0,
      "justification": "explication du coût"
    }
  ],
  "total_min": 0,
  "total_max": 0,
  "total_moyen": 0,
  "recommandations": ["liste de recommandations"],
  "avertissement": "Les estimations sont indicatives et peuvent varier selon les prestataires et la région."
}

Base tes estimations sur les prix moyens du marché français en 2024.
PROMPT;
    }

    public function isConfigured(): bool
    {
        return $this->client !== null;
    }
}
