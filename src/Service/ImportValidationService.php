<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service de validation et auto-correction des données extraites par l'IA depuis un PDF
 * Copie conforme de la logique web Laravel ImportValidationService
 */
class ImportValidationService
{
    private const ETAT_MAPPING = [
        // Format BDD direct
        'neuf' => 'neuf',
        'tres_bon' => 'tres_bon',
        'bon' => 'bon',
        'usage' => 'usage',
        'mauvais' => 'mauvais',
        'hors_service' => 'hors_service',
        // Legacy 4-tier format
        'bon_etat' => 'bon',
        'etat_moyen' => 'usage',
        'mauvais_etat' => 'mauvais',
        // Variantes textuelles
        'très bon' => 'tres_bon',
        'très bon état' => 'tres_bon',
        'tres bon' => 'tres_bon',
        'tres bon etat' => 'tres_bon',
        'bon état' => 'bon',
        'bon etat' => 'bon',
        'état moyen' => 'usage',
        'etat moyen' => 'usage',
        'usagé' => 'usage',
        'usé' => 'usage',
        'use' => 'usage',
        'mauvais état' => 'mauvais',
        'mauvais etat' => 'mauvais',
        'dégradé' => 'mauvais',
        'degrade' => 'mauvais',
        'hors service' => 'hors_service',
        'hs' => 'hors_service',
        'à remplacer' => 'hors_service',
        'a remplacer' => 'hors_service',
        // Abbréviations logiciels (Homepad, Immopad, etc.)
        'n' => 'neuf',
        'tb' => 'tres_bon',
        'tbe' => 'tres_bon',
        'b' => 'bon',
        'be' => 'bon',
        'u' => 'usage',
        'm' => 'mauvais',
        'me' => 'mauvais',
        // Formats numérotés
        '1' => 'neuf',
        '2' => 'bon',
        '3' => 'usage',
        '4' => 'mauvais',
    ];

    private const VALID_ELEMENT_TYPES = [
        'sol', 'mur', 'plafond', 'menuiserie', 'electricite',
        'plomberie', 'chauffage', 'equipement', 'mobilier',
        'electromenager', 'autre',
    ];

    private const CLE_TYPE_MAPPING = [
        'porte_entree' => 'porte_entree',
        "porte d'entrée" => 'porte_entree',
        'porte d\'entrée' => 'porte_entree',
        'porte principale' => 'porte_entree',
        'porte entree' => 'porte_entree',
        'parties_communes' => 'parties_communes',
        'parties communes' => 'parties_communes',
        'partie commune' => 'parties_communes',
        'boite_lettres' => 'boite_lettres',
        'boîte aux lettres' => 'boite_lettres',
        'boite aux lettres' => 'boite_lettres',
        'boîte à lettres' => 'boite_lettres',
        'bal' => 'boite_lettres',
        'cave' => 'cave',
        'garage' => 'garage',
        'parking' => 'parking',
        'local_velo' => 'local_velo',
        'local vélo' => 'local_velo',
        'local velo' => 'local_velo',
        'portail' => 'portail',
        'interphone' => 'interphone',
        'badge' => 'badge',
        'telecommande' => 'telecommande',
        'télécommande' => 'telecommande',
        'vigik' => 'vigik',
        'digicode' => 'digicode',
        'autre' => 'autre',
    ];

    private const COMPTEUR_TYPE_MAPPING = [
        'electricite' => 'electricite',
        'électricité' => 'electricite',
        'electrique' => 'electricite',
        'eau_froide' => 'eau_froide',
        'eau_chaude' => 'eau_chaude',
        'eau' => 'eau_froide',
        'gaz' => 'gaz',
    ];

    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * Valider et auto-corriger les données extraites par l'IA
     */
    public function validate(array $data, int $photoCount = 0): array
    {
        $corrections = [];

        $data = $this->normalizeDate($data, $corrections);
        $data = $this->normalizeAddress($data, $corrections);
        $data = $this->normalizeLogement($data, $corrections);
        $data = $this->normalizeCompteurs($data, $corrections);
        $data = $this->normalizeCles($data, $corrections);
        $data = $this->normalizePieces($data, $photoCount, $corrections);

        if (!empty($corrections)) {
            $this->logger->info('Import PDF - Auto-corrections appliquées', ['corrections' => $corrections]);
        }

        return $data;
    }

    private function normalizeDate(array $data, array &$corrections): array
    {
        if (!empty($data['date_realisation'])) {
            $date = $data['date_realisation'];

            // dd/mm/yyyy → yyyy-mm-dd
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m)) {
                $data['date_realisation'] = "{$m[3]}-{$m[2]}-{$m[1]}";
                $corrections[] = "Date '{$date}' → '{$data['date_realisation']}'";
            }
            // dd-mm-yyyy → yyyy-mm-dd
            elseif (preg_match('#^(\d{2})-(\d{2})-(\d{4})$#', $date, $m)) {
                $data['date_realisation'] = "{$m[3]}-{$m[2]}-{$m[1]}";
                $corrections[] = "Date '{$date}' → '{$data['date_realisation']}'";
            }
        }

        return $data;
    }

    private function normalizeAddress(array $data, array &$corrections): array
    {
        if (empty($data['logement'])) {
            return $data;
        }

        $logement = &$data['logement'];

        // Extraire code postal de l'adresse s'il y est resté
        if (!empty($logement['adresse']) && empty($logement['code_postal'])) {
            if (preg_match('/(\d{5})\s+(.+)$/i', $logement['adresse'], $matches)) {
                $logement['code_postal'] = $matches[1];
                $logement['ville'] = $logement['ville'] ?? trim($matches[2]);
                $logement['adresse'] = trim(str_replace($matches[0], '', $logement['adresse']));
                $corrections[] = "Code postal '{$matches[1]}' extrait de l'adresse";
            }
        }

        // Nettoyer code postal et ville de l'adresse même si déjà renseignés
        if (!empty($logement['adresse']) && !empty($logement['code_postal'])) {
            $cp = preg_quote($logement['code_postal'], '/');
            $cleaned = preg_replace("/,?\s*{$cp}\s*.*/i", '', $logement['adresse']);
            if ($cleaned !== $logement['adresse']) {
                $logement['adresse'] = trim($cleaned, ', ');
                $corrections[] = "Adresse nettoyée (code postal/ville retirés)";
            }
        }

        return $data;
    }

    private function normalizeLogement(array $data, array &$corrections): array
    {
        if (empty($data['logement'])) {
            return $data;
        }

        $logement = &$data['logement'];

        // Normaliser le type de bien (f1/f2/t3 → appartement, etc.)
        // L'IA renvoie le champ "type", l'ancien format utilisait "type_bien"
        $typeKey = !empty($logement['type']) ? 'type' : (!empty($logement['type_bien']) ? 'type_bien' : null);
        if ($typeKey) {
            $type = strtolower(trim($logement[$typeKey]));
            $validTypes = ['appartement', 'maison', 'studio', 'loft', 'chambre', 'commerce', 'bureau', 'parking', 'autre'];

            if (in_array($type, $validTypes)) {
                $logement[$typeKey] = $type;
            } elseif (preg_match('/^[ft]\d$/i', $type)) {
                $corrections[] = "Type logement '{$logement[$typeKey]}' → 'appartement'";
                $logement[$typeKey] = 'appartement';
            } elseif (preg_match('/^maison/i', $type)) {
                $corrections[] = "Type logement '{$logement[$typeKey]}' → 'maison'";
                $logement[$typeKey] = 'maison';
            } else {
                $corrections[] = "Type logement '{$logement[$typeKey]}' inconnu → supprimé";
                $logement[$typeKey] = null;
            }
        }

        // Calculer nombre_pieces à partir des pièces réellement extraites
        $realPieceCount = count($data['pieces'] ?? []);
        if ($realPieceCount > 0) {
            $old = $logement['nombre_pieces'] ?? null;
            if ($old != $realPieceCount) {
                $corrections[] = "Nombre pièces '{$old}' → '{$realPieceCount}' (basé sur pièces extraites)";
            }
            $logement['nombre_pieces'] = $realPieceCount;
        }

        return $data;
    }

    private function normalizeCompteurs(array $data, array &$corrections): array
    {
        if (empty($data['compteurs'])) {
            return $data;
        }

        $normalized = [];

        foreach ($data['compteurs'] as $key => $compteur) {
            // Support both array format [{type: "eau", ...}] and dict format {eau: {...}}
            if (is_array($compteur) && isset($compteur['type'])) {
                $type = $compteur['type'];
            } else {
                $type = $key;
            }

            $normalizedType = self::COMPTEUR_TYPE_MAPPING[strtolower($type)] ?? null;

            if (!$normalizedType) {
                $corrections[] = "Compteur type inconnu '{$type}' ignoré";
                continue;
            }

            if ($normalizedType !== $type) {
                $corrections[] = "Compteur type '{$type}' → '{$normalizedType}'";
                if (is_array($compteur) && isset($compteur['type'])) {
                    $compteur['type'] = $normalizedType;
                }
            }

            $normalized[] = array_merge(
                is_array($compteur) ? $compteur : [],
                ['type' => $normalizedType]
            );
        }

        $data['compteurs'] = $normalized;

        return $data;
    }

    private function normalizeCles(array $data, array &$corrections): array
    {
        if (empty($data['cles'])) {
            return $data;
        }

        $normalized = [];

        foreach ($data['cles'] as $cle) {
            if (!is_array($cle) || empty($cle['type'])) {
                continue;
            }

            $type = $cle['type'];
            $normalizedType = self::CLE_TYPE_MAPPING[strtolower(trim($type))] ?? 'autre';

            if ($normalizedType !== $type) {
                $corrections[] = "Clé type '{$type}' → '{$normalizedType}'";
            }

            // Dédupliquer : fusionner les clés du même type
            $found = false;
            foreach ($normalized as &$existing) {
                if ($existing['type'] === $normalizedType) {
                    $existing['nombre'] = ($existing['nombre'] ?? 0) + ($cle['nombre'] ?? 1);
                    if (!empty($cle['commentaire']) && empty($existing['commentaire'])) {
                        $existing['commentaire'] = $cle['commentaire'];
                    }
                    $existing['photo_indices'] = array_merge(
                        $existing['photo_indices'] ?? [],
                        $cle['photo_indices'] ?? []
                    );
                    $found = true;
                    $corrections[] = "Clé '{$normalizedType}' fusionnée (doublon)";
                    break;
                }
            }
            unset($existing);

            if (!$found) {
                $normalized[] = array_merge($cle, ['type' => $normalizedType]);
            }
        }

        $data['cles'] = $normalized;

        return $data;
    }

    private function normalizePieces(array $data, int $photoCount, array &$corrections): array
    {
        if (empty($data['pieces'])) {
            return $data;
        }

        $pieces = [];
        $seenNames = [];

        foreach ($data['pieces'] as $piece) {
            // Supprimer les pièces sans nom
            if (empty($piece['nom'])) {
                $corrections[] = "Pièce sans nom supprimée";
                continue;
            }

            // Valider photo_indices de la pièce
            if (!empty($piece['photo_indices'])) {
                $piece['photo_indices'] = $this->filterPhotoIndices($piece['photo_indices'], $photoCount);
            }

            // Normaliser les éléments
            if (!empty($piece['elements'])) {
                $piece['elements'] = $this->normalizeElements($piece['elements'], $photoCount, $corrections);
            }

            // Fusion de pièces dupliquées (coupure de tableau entre 2 pages)
            $nameKey = mb_strtolower(trim($piece['nom']));
            if (isset($seenNames[$nameKey])) {
                $existingIdx = $seenNames[$nameKey];
                $pieces[$existingIdx] = $this->mergePieces($pieces[$existingIdx], $piece);
                $corrections[] = "Pièce dupliquée '{$piece['nom']}' fusionnée";
                continue;
            }

            $seenNames[$nameKey] = count($pieces);
            $pieces[] = $piece;
        }

        $data['pieces'] = $pieces;

        return $data;
    }

    private function normalizeElements(array $elements, int $photoCount, array &$corrections): array
    {
        $normalized = [];

        foreach ($elements as $element) {
            // Supprimer les éléments sans nom
            if (empty($element['nom'])) {
                $corrections[] = "Élément sans nom supprimé";
                continue;
            }

            // Normaliser l'état
            if (!empty($element['etat'])) {
                $normalizedEtat = $this->normalizeEtat($element['etat']);
                if ($normalizedEtat !== $element['etat']) {
                    $corrections[] = "État '{$element['etat']}' → '{$normalizedEtat}'";
                }
                $element['etat'] = $normalizedEtat;
            } else {
                $element['etat'] = 'bon';
            }

            // Normaliser le type
            if (empty($element['type']) || !in_array($element['type'], self::VALID_ELEMENT_TYPES)) {
                $oldType = $element['type'] ?? 'null';
                $element['type'] = 'autre';
                if ($oldType !== 'autre') {
                    $corrections[] = "Type élément '{$oldType}' → 'autre'";
                }
            }

            // Valider photo_indices
            if (!empty($element['photo_indices'])) {
                $element['photo_indices'] = $this->filterPhotoIndices($element['photo_indices'], $photoCount);
            }

            $normalized[] = $element;
        }

        return $normalized;
    }

    public function normalizeEtat(string $etat): string
    {
        $key = mb_strtolower(trim($etat));

        return self::ETAT_MAPPING[$key] ?? 'bon';
    }

    private function filterPhotoIndices(array $indices, int $photoCount): array
    {
        if ($photoCount === 0) {
            return $indices;
        }

        return array_values(array_filter($indices, fn($i) => is_int($i) && $i >= 1 && $i <= $photoCount));
    }

    private function mergePieces(array $existing, array $duplicate): array
    {
        // Fusionner les éléments
        if (!empty($duplicate['elements'])) {
            $existing['elements'] = array_merge($existing['elements'] ?? [], $duplicate['elements']);
        }

        // Fusionner les photo_indices
        if (!empty($duplicate['photo_indices'])) {
            $existing['photo_indices'] = array_merge($existing['photo_indices'] ?? [], $duplicate['photo_indices']);
        }

        // Concaténer les observations
        if (!empty($duplicate['observations'])) {
            $existing['observations'] = trim(($existing['observations'] ?? '') . ' ' . $duplicate['observations']);
        }

        return $existing;
    }
}
