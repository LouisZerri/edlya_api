<?php

namespace App\Controller;

use App\Entity\EtatDesLieux;
use App\Entity\Logement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Retourne les statistiques enrichies du dashboard
     */
    #[Route('/api/stats/dashboard', name: 'api_stats_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $user->getId();

        // Total logements
        $totalLogements = $this->em->getRepository(Logement::class)
            ->count(['user' => $user]);

        // Total EDL
        $totalEdl = $this->em->getRepository(EtatDesLieux::class)
            ->count(['user' => $user]);

        // EDL en attente (brouillon + en_cours)
        $qb = $this->em->createQueryBuilder();
        $edlEnAttente = $qb->select('COUNT(e.id)')
            ->from(EtatDesLieux::class, 'e')
            ->where('e.user = :user')
            ->andWhere($qb->expr()->in('e.statut', ['brouillon', 'en_cours']))
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // EDL signés
        $edlSignes = $this->em->getRepository(EtatDesLieux::class)
            ->count(['user' => $user, 'statut' => 'signe']);

        // Répartition entrée/sortie
        $edlEntreeCount = $this->em->getRepository(EtatDesLieux::class)
            ->count(['user' => $user, 'type' => 'entree']);

        $edlSortieCount = $this->em->getRepository(EtatDesLieux::class)
            ->count(['user' => $user, 'type' => 'sortie']);

        // Activité EDL sur les 14 derniers jours
        $activity = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} days");
            $dateStr = $date->format('Y-m-d');
            $activity[$dateStr] = ['date' => $dateStr, 'count' => 0];
        }

        $qbActivity = $this->em->createQueryBuilder();
        $since = new \DateTime('-13 days');
        $since->setTime(0, 0, 0);

        $activityResults = $qbActivity->select("SUBSTRING(e.createdAt, 1, 10) AS day, COUNT(e.id) AS cnt")
            ->from(EtatDesLieux::class, 'e')
            ->where('e.user = :user')
            ->andWhere('e.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->groupBy('day')
            ->getQuery()
            ->getResult();

        foreach ($activityResults as $row) {
            if (isset($activity[$row['day']])) {
                $activity[$row['day']]['count'] = (int) $row['cnt'];
            }
        }

        // Logements sans EDL (5 derniers)
        $qb2 = $this->em->createQueryBuilder();
        $logementsSansEdl = $qb2->select('l.id', 'l.nom', 'l.adresse', 'l.ville')
            ->from(Logement::class, 'l')
            ->leftJoin('l.etatDesLieux', 'e')
            ->where('l.user = :user')
            ->andWhere('e.id IS NULL')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'success' => true,
            'stats' => [
                'total_logements' => (int) $totalLogements,
                'total_edl' => (int) $totalEdl,
                'edl_en_attente' => (int) $edlEnAttente,
                'edl_signes' => (int) $edlSignes,
                'edl_entree_count' => (int) $edlEntreeCount,
                'edl_sortie_count' => (int) $edlSortieCount,
            ],
            'logements_sans_edl' => $logementsSansEdl,
            'activity' => array_values($activity),
        ]);
    }
}
