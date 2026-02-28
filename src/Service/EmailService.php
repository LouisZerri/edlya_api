<?php

namespace App\Service;

use App\Entity\EtatDesLieux;
use App\Entity\Partage;
use App\Repository\EtatDesLieuxRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private PdfGenerator $pdfGenerator,
        private EtatDesLieuxRepository $edlRepository,
        private string $fromEmail,
        private string $fromName,
        private string $frontendUrl
    ) {
    }

    /**
     * Envoie la confirmation de signature
     */
    public function sendSignatureConfirmation(EtatDesLieux $edl): void
    {
        $logement = $edl->getLogement();

        $html = $this->twig->render('emails/signature_confirmation.html.twig', [
            'edl' => $edl,
            'logement' => $logement,
            'bailleur' => $edl->getUser(),
        ]);

        $pdfContent = $this->pdfGenerator->generateEtatDesLieux($edl);
        $pdfFilename = sprintf('etat_des_lieux_%s.pdf', $edl->getId());

        // Envoyer au locataire (si email renseigné)
        if (!empty($edl->getLocataireEmail())) {
            $emailLocataire = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($edl->getLocataireEmail())
                ->subject(sprintf(
                    'Confirmation signature - État des lieux %s',
                    $logement->getAdresse()
                ))
                ->html($html)
                ->attach($pdfContent, $pdfFilename, 'application/pdf');

            $this->mailer->send($emailLocataire);
        }

        // Envoyer au bailleur
        $emailBailleur = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($edl->getUser()->getEmail())
            ->subject(sprintf(
                'État des lieux signé - %s (%s)',
                $logement->getAdresse(),
                $edl->getLocataireNom()
            ))
            ->html($html)
            ->attach($pdfContent, $pdfFilename, 'application/pdf');

        $this->mailer->send($emailBailleur);
    }

    /**
     * Envoie un EDL partagé avec le PDF en pièce jointe
     */
    public function sendPartageEmail(Partage $partage): void
    {
        if (empty($partage->getEmail())) {
            return;
        }

        $edl = $partage->getEtatDesLieux();
        $logement = $edl->getLogement();

        $shareUrl = rtrim($this->frontendUrl, '/') . '/p/' . $partage->getToken();

        $html = $this->twig->render('emails/partage_link.html.twig', [
            'edl' => $edl,
            'logement' => $logement,
            'bailleur' => $edl->getUser(),
            'shareUrl' => $shareUrl,
        ]);

        $pdfContent = $this->pdfGenerator->generateEtatDesLieux($edl);
        $pdfFilename = sprintf('etat_des_lieux_%s_%s.pdf', $edl->getType(), $edl->getId());

        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($partage->getEmail())
            ->subject(sprintf(
                'État des lieux partagé - %s',
                $logement->getAdresse()
            ))
            ->html($html)
            ->attach($pdfContent, $pdfFilename, 'application/pdf');

        $this->mailer->send($email);
    }

    /**
     * Envoie le comparatif entrée/sortie par email avec le PDF en pièce jointe
     */
    public function sendComparatifEmail(EtatDesLieux $edl, string $toEmail): void
    {
        $logement = $edl->getLogement();

        // Déterminer entrée et sortie
        if ($edl->getType() === 'sortie') {
            $edlSortie = $edl;
            $edlEntree = $this->edlRepository->findLastByLogementAndType($logement, 'entree');
        } else {
            $edlEntree = $edl;
            $edlSortie = $this->edlRepository->findLastByLogementAndType($logement, 'sortie');
        }

        $comparatif = $this->buildComparatif($edlEntree, $edlSortie);

        // Durée en mois
        $dureeMois = null;
        if ($edlEntree && $edlSortie) {
            $duree = $edlEntree->getDateRealisation()->diff($edlSortie->getDateRealisation());
            $dureeMois = $duree->m + ($duree->y * 12);
        }
        $comparatif['duree_mois'] = $dureeMois;

        $data = [
            'logement' => [
                'nom' => $logement->getNom(),
                'adresse' => $logement->getAdresse(),
                'codePostal' => $logement->getCodePostal(),
                'ville' => $logement->getVille(),
                'type' => $logement->getType(),
                'surface' => $logement->getSurface(),
            ],
            'entree' => $edlEntree,
            'sortie' => $edlSortie,
            'comparatif' => $comparatif,
        ];

        $pdfContent = $this->pdfGenerator->generateComparatif($data);
        $pdfFilename = sprintf('comparatif-edl-%s.pdf', $edl->getId());

        $html = $this->twig->render('emails/comparatif.html.twig', [
            'edl' => $edl,
            'logement' => $logement,
            'bailleur' => $edl->getUser(),
            'comparatif' => $comparatif,
        ]);

        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject(sprintf(
                'Comparatif état des lieux - %s',
                $logement->getAdresse()
            ))
            ->html($html)
            ->attach($pdfContent, $pdfFilename, 'application/pdf');

        $this->mailer->send($email);
    }

    /**
     * Envoie le devis estimations par email avec le PDF en pièce jointe
     */
    public function sendEstimationsEmail(EtatDesLieux $edl, string $toEmail, array $lignes): void
    {
        $logement = $edl->getLogement();

        $totalHT = 0;
        $lignesDevis = [];

        foreach ($lignes as $ligne) {
            if (!empty($ligne['description']) && isset($ligne['quantite']) && isset($ligne['prix_unitaire'])) {
                $montant = floatval($ligne['quantite']) * floatval($ligne['prix_unitaire']);
                $totalHT += $montant;

                $lignesDevis[] = [
                    'piece' => $ligne['piece'] ?? '',
                    'description' => $ligne['description'],
                    'quantite' => floatval($ligne['quantite']),
                    'unite' => $ligne['unite'] ?? 'unité',
                    'prix_unitaire' => floatval($ligne['prix_unitaire']),
                    'montant' => $montant,
                ];
            }
        }

        $tva = $totalHT * 0.20;
        $totalTTC = $totalHT + $tva;

        $data = [
            'logement' => [
                'nom' => $logement->getNom(),
                'adresse' => $logement->getAdresse(),
                'codePostal' => $logement->getCodePostal(),
                'ville' => $logement->getVille(),
            ],
            'locataire' => [
                'nom' => $edl->getLocataireNom(),
                'email' => $edl->getLocataireEmail(),
            ],
            'date_sortie' => $edl->getDateRealisation()->format('d/m/Y'),
            'lignes' => $lignesDevis,
            'totalHT' => $totalHT,
            'tva' => $tva,
            'totalTTC' => $totalTTC,
        ];

        $pdfContent = $this->pdfGenerator->generateEstimations($data);
        $pdfFilename = sprintf('devis-reparations-%s.pdf', $edl->getId());

        $html = $this->twig->render('emails/estimations.html.twig', [
            'edl' => $edl,
            'logement' => $logement,
            'bailleur' => $edl->getUser(),
            'totalTTC' => $totalTTC,
            'nbLignes' => count($lignesDevis),
        ]);

        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject(sprintf(
                'Devis réparations - %s',
                $logement->getAdresse()
            ))
            ->html($html)
            ->attach($pdfContent, $pdfFilename, 'application/pdf');

        $this->mailer->send($email);
    }

    private function buildComparatif(?EtatDesLieux $entree, ?EtatDesLieux $sortie): array
    {
        $comparatif = [
            'pieces' => [],
            'compteurs' => [],
            'cles' => [],
            'degradations' => [],
            'statistiques' => [
                'totalElements' => 0,
                'elementsAmeliores' => 0,
                'elementsDegrades' => 0,
                'elementsIdentiques' => 0,
            ],
        ];

        $etatScore = [
            'neuf' => 6,
            'tres_bon' => 5,
            'bon' => 4,
            'usage' => 3,
            'mauvais' => 2,
            'hors_service' => 1,
        ];

        $elementsEntree = [];
        if ($entree) {
            foreach ($entree->getPieces() as $piece) {
                foreach ($piece->getElements() as $element) {
                    $key = $piece->getNom() . '|' . $element->getNom() . '|' . $element->getType();
                    $elementsEntree[$key] = [
                        'piece' => $piece->getNom(),
                        'nom' => $element->getNom(),
                        'type' => $element->getType(),
                        'etat' => $element->getEtat(),
                        'observations' => $element->getObservations(),
                    ];
                }
            }
        }

        if ($sortie) {
            foreach ($sortie->getPieces() as $piece) {
                $pieceName = $piece->getNom();

                if (!isset($comparatif['pieces'][$pieceName])) {
                    $comparatif['pieces'][$pieceName] = [];
                }

                foreach ($piece->getElements() as $element) {
                    $key = $pieceName . '|' . $element->getNom() . '|' . $element->getType();

                    $entreeData = $elementsEntree[$key] ?? null;
                    $sortieData = [
                        'nom' => $element->getNom(),
                        'type' => $element->getType(),
                        'etat' => $element->getEtat(),
                        'observations' => $element->getObservations(),
                    ];

                    $evolution = 'nouveau';
                    if ($entreeData) {
                        $scoreEntree = $etatScore[$entreeData['etat']] ?? 0;
                        $scoreSortie = $etatScore[$sortieData['etat']] ?? 0;

                        if ($scoreSortie > $scoreEntree) {
                            $evolution = 'ameliore';
                            $comparatif['statistiques']['elementsAmeliores']++;
                        } elseif ($scoreSortie < $scoreEntree) {
                            $evolution = 'degrade';
                            $comparatif['statistiques']['elementsDegrades']++;

                            $comparatif['degradations'][] = [
                                'piece' => $pieceName,
                                'element' => $element->getNom(),
                                'type' => $element->getType(),
                                'etatEntree' => $entreeData['etat'],
                                'etatSortie' => $sortieData['etat'],
                                'observations' => $sortieData['observations'],
                            ];
                        } else {
                            $evolution = 'identique';
                            $comparatif['statistiques']['elementsIdentiques']++;
                        }
                    }

                    $comparatif['statistiques']['totalElements']++;

                    $comparatif['pieces'][$pieceName][] = [
                        'element' => $element->getNom(),
                        'type' => $element->getType(),
                        'entree' => $entreeData ? [
                            'etat' => $entreeData['etat'],
                            'observations' => $entreeData['observations'],
                        ] : null,
                        'sortie' => [
                            'etat' => $sortieData['etat'],
                            'observations' => $sortieData['observations'],
                        ],
                        'evolution' => $evolution,
                    ];
                }
            }
        }

        // Compteurs
        $compteursEntree = [];
        if ($entree) {
            foreach ($entree->getCompteurs() as $c) {
                $compteursEntree[$c->getType()] = [
                    'numero' => $c->getNumero(),
                    'index' => $c->getIndexValue(),
                ];
            }
        }

        if ($sortie) {
            foreach ($sortie->getCompteurs() as $c) {
                $entreeC = $compteursEntree[$c->getType()] ?? null;
                $comparatif['compteurs'][] = [
                    'type' => $c->getType(),
                    'entree' => $entreeC,
                    'sortie' => [
                        'numero' => $c->getNumero(),
                        'index' => $c->getIndexValue(),
                    ],
                    'consommation' => ($entreeC && $c->getIndexValue() && $entreeC['index'])
                        ? (int)$c->getIndexValue() - (int)$entreeC['index']
                        : null,
                ];
            }
        }

        // Clés
        $clesEntree = [];
        if ($entree) {
            foreach ($entree->getCles() as $c) {
                $clesEntree[$c->getType()] = $c->getNombre();
            }
        }

        if ($sortie) {
            foreach ($sortie->getCles() as $c) {
                $nbEntree = $clesEntree[$c->getType()] ?? 0;
                $comparatif['cles'][] = [
                    'type' => $c->getType(),
                    'entree' => $nbEntree,
                    'sortie' => $c->getNombre(),
                    'difference' => $c->getNombre() - $nbEntree,
                ];
            }
        }

        return $comparatif;
    }
}
