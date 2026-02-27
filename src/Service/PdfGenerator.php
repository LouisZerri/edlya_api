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
        $debugLog = $this->projectDir . '/var/signature-debug.log';
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - [PdfGen] START memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB\n", FILE_APPEND);

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

        // Compter les photos pour diagnostiquer la mémoire
        $totalPhotos = 0;
        foreach ($edl->getPieces() as $piece) {
            foreach ($piece->getElements() as $element) {
                $totalPhotos += count($element->getPhotos());
            }
        }
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - [PdfGen] EDL #" . $edl->getId() . " has " . count($edl->getPieces()) . " pieces, " . $totalPhotos . " photos\n", FILE_APPEND);

        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - [PdfGen] Rendering Twig... memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB\n", FILE_APPEND);
        $html = $this->twig->render('pdf/etat_des_lieux.html.twig', [
            'edl' => $edl,
            'edlEntree' => $edlEntree,
            'logement' => $logement,
            'pieces' => $edl->getPieces(),
            'compteurs' => $edl->getCompteurs(),
            'cles' => $edl->getCles(),
            'projectDir' => $this->projectDir,
        ]);
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - [PdfGen] Twig OK (" . round(strlen($html) / 1024, 1) . "KB) memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB\n", FILE_APPEND);

        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - [PdfGen] loadHtml...\n", FILE_APPEND);
        $dompdf->loadHtml($html);
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - [PdfGen] loadHtml OK, memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB\n", FILE_APPEND);

        $dompdf->setPaper('A4', 'portrait');

        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - [PdfGen] render()...\n", FILE_APPEND);
        $dompdf->render();
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - [PdfGen] render() OK, memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB\n", FILE_APPEND);

        $output = $dompdf->output();
        file_put_contents($debugLog, date('Y-m-d H:i:s') . " - [PdfGen] output OK (" . round(strlen($output) / 1024, 1) . "KB) memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB\n", FILE_APPEND);

        return $output;
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
