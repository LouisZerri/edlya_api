<?php

namespace App\Controller;

use App\Controller\Trait\AuthorizationTrait;
use App\Entity\EtatDesLieux;
use App\Entity\Logement;
use App\Repository\EtatDesLieuxRepository;
use App\Service\ComparatifService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ComparatifController extends AbstractController
{
    use AuthorizationTrait;
    public function __construct(
        private ComparatifService $comparatifService,
    ) {
    }
    /**
     * Comparatif à partir d'un EDL (entrée ou sortie)
     * Trouve automatiquement l'EDL correspondant du même logement
     */
    #[Route('/api/edl/{id}/comparatif', name: 'api_edl_comparatif', methods: ['GET'])]
    public function comparatifEdl(
        int $id,
        EtatDesLieuxRepository $edlRepo
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $edl = $edlRepo->findWithFullRelations($id);

        if (!$edl) {
            return new JsonResponse(['error' => 'État des lieux non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($edl, $user)) return $denied;

        $logement = $edl->getLogement();

        // Déterminer si c'est un EDL d'entrée ou de sortie et trouver l'autre
        if ($edl->getType() === 'sortie') {
            $edlSortie = $edl;
            $edlEntree = $edlRepo->findLastByLogementAndType($logement, 'entree');
        } else {
            $edlEntree = $edl;
            $edlSortie = $edlRepo->findLastByLogementAndType($logement, 'sortie');
        }

        $comparatif = $this->comparatifService->buildComparatif($edlEntree, $edlSortie);

        return new JsonResponse([
            'logement' => [
                'id' => $logement->getId(),
                'nom' => $logement->getNom(),
                'adresse' => $logement->getAdresse(),
                'ville' => $logement->getVille(),
            ],
            'entree' => $edlEntree ? $this->formatEdlSummary($edlEntree) : null,
            'sortie' => $edlSortie ? $this->formatEdlSummary($edlSortie) : null,
            'comparatif' => $comparatif,
        ]);
    }

    #[Route('/api/logements/{id}/comparatif', name: 'api_logement_comparatif', methods: ['GET'])]
    public function comparatif(
        int $id,
        EntityManagerInterface $em,
        EtatDesLieuxRepository $edlRepo
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) return $user;

        $logement = $em->getRepository(Logement::class)->find($id);

        if (!$logement) {
            return new JsonResponse(['error' => 'Logement non trouvé'], Response::HTTP_NOT_FOUND);
        }

        if ($denied = $this->denyUnlessOwner($logement, $user)) return $denied;

        // Récupérer le dernier EDL d'entrée et de sortie signés ou terminés
        $edlEntree = $edlRepo->findLastByLogementTypeAndStatuts($logement, 'entree', ['termine', 'signe']);
        $edlSortie = $edlRepo->findLastByLogementTypeAndStatuts($logement, 'sortie', ['termine', 'signe']);

        if (!$edlEntree && !$edlSortie) {
            return new JsonResponse([
                'error' => 'Aucun état des lieux d\'entrée ou de sortie terminé/signé trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $comparatif = $this->comparatifService->buildComparatif($edlEntree, $edlSortie);

        return new JsonResponse([
            'logement' => [
                'id' => $logement->getId(),
                'nom' => $logement->getNom(),
                'adresse' => $logement->getAdresse(),
                'ville' => $logement->getVille(),
            ],
            'entree' => $edlEntree ? $this->formatEdlSummary($edlEntree) : null,
            'sortie' => $edlSortie ? $this->formatEdlSummary($edlSortie) : null,
            'comparatif' => $comparatif,
        ]);
    }

    private function formatEdlSummary(EtatDesLieux $edl): array
    {
        return [
            'id' => $edl->getId(),
            'type' => $edl->getType(),
            'dateRealisation' => $edl->getDateRealisation()->format('Y-m-d'),
            'locataireNom' => $edl->getLocataireNom(),
            'statut' => $edl->getStatut(),
        ];
    }

}
