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

        // Tous les compteurs EDL en 1 seule requête (au lieu de 5)
        $edlStats = $this->em->createQueryBuilder()
            ->select(
                'COUNT(e.id) AS total_edl',
                "SUM(CASE WHEN e.statut IN ('brouillon', 'en_cours') THEN 1 ELSE 0 END) AS edl_en_attente",
                "SUM(CASE WHEN e.statut = 'signe' THEN 1 ELSE 0 END) AS edl_signes",
                "SUM(CASE WHEN e.type = 'entree' THEN 1 ELSE 0 END) AS edl_entree",
                "SUM(CASE WHEN e.type = 'sortie' THEN 1 ELSE 0 END) AS edl_sortie"
            )
            ->from(EtatDesLieux::class, 'e')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        $totalLogements = $this->em->getRepository(Logement::class)
            ->count(['user' => $user]);

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
                'total_edl' => (int) $edlStats['total_edl'],
                'edl_en_attente' => (int) $edlStats['edl_en_attente'],
                'edl_signes' => (int) $edlStats['edl_signes'],
                'edl_entree_count' => (int) $edlStats['edl_entree'],
                'edl_sortie_count' => (int) $edlStats['edl_sortie'],
            ],
            'logements_sans_edl' => $logementsSansEdl,
            'activity' => array_values($activity),
        ]);
    }
}
