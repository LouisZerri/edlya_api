<?php

namespace App\Controller;

use App\Entity\ActivationCode;
use App\Entity\User;
use App\Repository\ActivationCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ActivationController extends AbstractController
{
    public function __construct(
        private string $adminApiKey,
    ) {}

    /**
     * Crée un code d'activation pour un email donné
     * Appelé par l'admin Gestimmo avec une clé API
     */
    #[Route('/api/admin/create-code', name: 'api_admin_create_code', methods: ['POST'])]
    public function createCode(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $apiKey = $request->headers->get('X-Admin-Api-Key');
        if ($apiKey !== $this->adminApiKey) {
            return new JsonResponse(['error' => 'Non autorisé'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Email valide requis'], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower(trim($email));

        // Vérifier s'il existe déjà un code non utilisé pour cet email
        $existing = $em->getRepository(ActivationCode::class)->findOneBy([
            'email' => $email,
            'usedAt' => null,
        ]);

        if ($existing) {
            return new JsonResponse([
                'code' => $existing->getCode(),
                'email' => $existing->getEmail(),
                'message' => 'Un code existe déjà pour cet email',
            ]);
        }

        $activationCode = new ActivationCode();
        $activationCode->setEmail($email);
        $activationCode->setCode(ActivationCode::generateCode());

        $em->persist($activationCode);
        $em->flush();

        return new JsonResponse([
            'code' => $activationCode->getCode(),
            'email' => $activationCode->getEmail(),
            'message' => 'Code créé avec succès',
        ], Response::HTTP_CREATED);
    }

    /**
     * Active un compte utilisateur avec un code
     * Appelé par l'app mobile
     */
    #[Route('/api/activate', name: 'api_activate', methods: ['POST'])]
    public function activate(
        Request $request,
        EntityManagerInterface $em,
        ActivationCodeRepository $codeRepository,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isVerified()) {
            return new JsonResponse(['message' => 'Compte déjà vérifié']);
        }

        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? null;

        if (!$code) {
            return new JsonResponse(['error' => 'Code requis'], Response::HTTP_BAD_REQUEST);
        }

        $activationCode = $codeRepository->findValidCode($user->getEmail(), $code);

        if (!$activationCode) {
            return new JsonResponse(['error' => 'Code invalide ou déjà utilisé'], Response::HTTP_BAD_REQUEST);
        }

        // Marquer le code comme utilisé
        $activationCode->setUsedAt(new \DateTimeImmutable());

        // Vérifier l'utilisateur
        $user->setIsVerified(true);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();

        return new JsonResponse([
            'message' => 'Compte activé avec succès',
            'isVerified' => true,
        ]);
    }
}
