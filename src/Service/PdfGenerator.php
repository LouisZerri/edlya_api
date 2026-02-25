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

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
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
