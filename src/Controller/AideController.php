<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AIService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AideController extends AbstractController
{
    public function __construct(
        private AIService $aiService
    ) {}

    /**
     * Améliorer une observation avec l'IA (max 100 caractères, style professionnel)
     */
    #[Route('/api/aide/ameliorer-observation', name: 'api_aide_ameliorer_observation', methods: ['POST'])]
    public function ameliorerObservation(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->aiService->isConfigured()) {
            return new JsonResponse(['error' => 'Service IA non configuré'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['element'])) {
            return new JsonResponse(['error' => 'Le champ element est requis'], Response::HTTP_BAD_REQUEST);
        }

        $element = $data['element'];
        $etat = $data['etat'] ?? 'bon';
        $observation = $data['observation'] ?? null;
        $degradations = $data['degradations'] ?? [];

        $result = $this->aiService->ameliorerObservation($element, $etat, $observation, $degradations);

        if ($result === null) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'amélioration de l\'observation',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'success' => true,
            'observation_amelioree' => $result,
        ]);
    }
}
