<?php

namespace App\Service;

use App\Entity\EtatDesLieux;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfGenerator
{
    public function __construct(
        private Environment $twig,
        private string $projectDir,
        private EntityManagerInterface $em,
    ) {}

    public function generateEtatDesLieux(EtatDesLieux $edl): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', $this->projectDir . '/public');
        $options->setIsPhpEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->setBasePath($this->projectDir . '/public');

        $logement = $edl->getLogement();

        // Pour les EDL de sortie, récupérer l'EDL d'entrée correspondant
        $edlEntree = null;
        if ($edl->getType() === 'sortie') {
            $edlEntree = $this->em->getRepository(EtatDesLieux::class)->findOneBy(
                ['logement' => $logement, 'type' => 'entree'],
                ['dateRealisation' => 'DESC']
            );
        }

        $html = $this->twig->render('pdf/etat_des_lieux.html.twig', [
            'edl' => $edl,
            'edlEntree' => $edlEntree,
            'logement' => $logement,
            'pieces' => $edl->getPieces(),
            'compteurs' => $edl->getCompteurs(),
            'cles' => $edl->getCles(),
            'projectDir' => $this->projectDir,
        ]);

        // Redimensionner les images pour réduire la taille du PDF
        $tempFiles = [];
        $html = preg_replace_callback('/file:\/\/([^"\'>\s]+)/', function ($matches) use (&$tempFiles) {
            $originalPath = $matches[1];
            $resized = $this->resizeImageForPdf($originalPath);
            if ($resized) {
                $tempFiles[] = $resized;
                return 'file://' . $resized;
            }
            return $matches[0];
        }, $html);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();

        // Nettoyer les fichiers temporaires
        foreach ($tempFiles as $tmpFile) {
            @unlink($tmpFile);
        }

        return $output;
    }

    /**
     * Redimensionne une image à max 1200px et la compresse en JPEG 85%.
     * Retourne le chemin du fichier temporaire, ou null si échec.
     */
    private function resizeImageForPdf(string $originalPath, int $maxDim = 1200, int $quality = 85): ?string
    {
        if (!file_exists($originalPath)) {
            return null;
        }

        $info = @getimagesize($originalPath);
        if (!$info) {
            return null;
        }

        [$origW, $origH] = $info;
        $mime = $info['mime'];

        // Déjà petite et JPEG → pas besoin de redimensionner
        if ($origW <= $maxDim && $origH <= $maxDim && $mime === 'image/jpeg' && filesize($originalPath) < 200000) {
            return null;
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($originalPath),
            'image/png' => @imagecreatefrompng($originalPath),
            'image/webp' => @imagecreatefromwebp($originalPath),
            default => null,
        };

        if (!$source) {
            return null;
        }

        // Calculer les nouvelles dimensions
        $ratio = min($maxDim / $origW, $maxDim / $origH, 1.0);
        $newW = (int) round($origW * $ratio);
        $newH = (int) round($origH * $ratio);

        $dest = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $tmpFile = tempnam(sys_get_temp_dir(), 'edlya_pdf_') . '.jpg';
        imagejpeg($dest, $tmpFile, $quality);

        imagedestroy($source);
        imagedestroy($dest);

        return $tmpFile;
    }

    public function generateComparatif(array $data): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->twig->render('pdf/comparatif.html.twig', $data);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function generateEstimations(array $data): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->twig->render('pdf/estimations.html.twig', $data);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
