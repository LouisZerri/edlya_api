<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private string $fromEmail
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'], $data['name'])) {
            return new JsonResponse(['error' => 'Email, password et name requis'], Response::HTTP_BAD_REQUEST);
        }

        $email = trim($data['email']);
        $password = $data['password'];
        $name = trim($data['name']);
        $telephone = isset($data['telephone']) ? trim($data['telephone']) : null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Format d\'email invalide'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($name) < 2 || strlen($name) > 100) {
            return new JsonResponse(['error' => 'Le nom doit contenir entre 2 et 100 caractères'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[a-z]/', $password)
            || !preg_match('/[0-9]/', $password)
            || !preg_match('/[^a-zA-Z0-9]/', $password)
        ) {
            return new JsonResponse(['error' => 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial'], Response::HTTP_BAD_REQUEST);
        }

        if ($telephone !== null && strlen($telephone) > 20) {
            return new JsonResponse(['error' => 'Numéro de téléphone trop long'], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Email déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setTelephone($telephone);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'Votre compte a été créé avec succès',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'telephone' => $user->getTelephone(),
        ]);
    }

    #[Route('/api/profile', name: 'api_profile_update', methods: ['PUT'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name']) && !empty($data['name'])) {
            $name = trim($data['name']);
            if (strlen($name) < 2 || strlen($name) > 100) {
                return new JsonResponse(['error' => 'Le nom doit contenir entre 2 et 100 caractères'], Response::HTTP_BAD_REQUEST);
            }
            $user->setName($name);
        }

        if (array_key_exists('telephone', $data)) {
            $telephone = $data['telephone'] ? trim($data['telephone']) : null;
            if ($telephone !== null && strlen($telephone) > 20) {
                return new JsonResponse(['error' => 'Numéro de téléphone trop long'], Response::HTTP_BAD_REQUEST);
            }
            $user->setTelephone($telephone);
        }

        $user->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'telephone' => $user->getTelephone(),
        ]);
    }

    /**
     * Demande de réinitialisation de mot de passe
     * Génère un token et envoie un email
     */
    #[Route('/api/auth/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        PasswordResetTokenRepository $tokenRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || empty($data['email'])) {
            return new JsonResponse([
                'error' => 'Email requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = trim($data['email']);

        // Message de succès générique (sécurité : ne pas révéler si l'email existe)
        $successResponse = new JsonResponse([
            'success' => true,
            'message' => 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.'
        ]);

        // Chercher l'utilisateur
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            // Ne pas révéler que l'email n'existe pas
            return $successResponse;
        }

        // Invalider les anciens tokens de cet utilisateur
        $tokenRepository->invalidateUserTokens($user);

        // Créer un nouveau token (valide 1 heure)
        $resetToken = new PasswordResetToken();
        $resetToken->setToken(PasswordResetToken::generateToken());
        $resetToken->setUser($user);
        $resetToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $em->persist($resetToken);
        $em->flush();

        // Lien HTTPS qui redirige vers le deep link (les clients mail bloquent les custom schemes)
        $resetLink = $request->getSchemeAndHttpHost() . '/api/auth/redirect-reset?token=' . $resetToken->getToken();

        // Envoyer l'email
        try {
            $emailMessage = (new Email())
                ->from($this->fromEmail)
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe Edlya')
                ->html($this->renderResetPasswordEmail($user, $resetLink));

            $mailer->send($emailMessage);
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas révéler à l'utilisateur
            // En prod, utiliser un logger
        }

        return $successResponse;
    }

    /**
     * Réinitialisation du mot de passe avec le token
     */
    #[Route('/api/auth/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        PasswordResetTokenRepository $tokenRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['token']) || empty($data['token'])) {
            return new JsonResponse([
                'error' => 'Token requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['password']) || empty($data['password'])) {
            return new JsonResponse([
                'error' => 'Nouveau mot de passe requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $password = $data['password'];

        // Validation du mot de passe
        if (strlen($password) < 8
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[a-z]/', $password)
            || !preg_match('/[0-9]/', $password)
            || !preg_match('/[^a-zA-Z0-9]/', $password)
        ) {
            return new JsonResponse([
                'error' => 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Trouver le token valide
        $resetToken = $tokenRepository->findValidToken($data['token']);

        if (!$resetToken) {
            return new JsonResponse([
                'error' => 'Token invalide ou expiré'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $resetToken->getUser();

        // Mettre à jour le mot de passe
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setUpdatedAt(new \DateTimeImmutable());

        // Marquer le token comme utilisé
        $resetToken->setUsedAt(new \DateTimeImmutable());

        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Votre mot de passe a été modifié avec succès. Vous pouvez maintenant vous connecter.'
        ]);
    }

    /**
     * Échange un refresh token contre un nouveau JWT + nouveau refresh token
     */
    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refreshToken(
        Request $request,
        EntityManagerInterface $em,
        RefreshTokenRepository $refreshTokenRepository,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['refresh_token']) || empty($data['refresh_token'])) {
            return new JsonResponse(['error' => 'Refresh token requis'], Response::HTTP_BAD_REQUEST);
        }

        $refreshToken = $refreshTokenRepository->findValidToken($data['refresh_token']);

        if (!$refreshToken) {
            return new JsonResponse(['error' => 'Refresh token invalide ou expiré'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $refreshToken->getUser();

        // Révoquer l'ancien refresh token (rotation)
        $refreshToken->setRevokedAt(new \DateTimeImmutable());

        // Créer un nouveau refresh token
        $newRefreshToken = new RefreshToken();
        $newRefreshToken->setToken(RefreshToken::generateToken());
        $newRefreshToken->setUser($user);
        $newRefreshToken->setExpiresAt(new \DateTimeImmutable('+30 days'));

        $em->persist($newRefreshToken);
        $em->flush();

        // Générer un nouveau JWT
        $jwt = $jwtManager->create($user);

        return new JsonResponse([
            'token' => $jwt,
            'refresh_token' => $newRefreshToken->getToken(),
        ]);
    }

    /**
     * Révoque tous les refresh tokens de l'utilisateur (logout)
     */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(
        RefreshTokenRepository $refreshTokenRepository
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $refreshTokenRepository->revokeAllForUser($user);

        return new JsonResponse(['message' => 'Déconnexion réussie']);
    }

    /**
     * Page web intermédiaire qui redirige vers le deep link de l'app
     * Les clients mail bloquent les custom URL schemes (edlya://)
     * mais autorisent les liens https:// qui redirigent ensuite
     */
    #[Route('/api/auth/redirect-reset', name: 'api_auth_redirect_reset', methods: ['GET'])]
    public function redirectReset(Request $request): Response
    {
        $token = $request->query->get('token', '');
        $deepLink = 'edlya://reset-password?token=' . htmlspecialchars($token, ENT_QUOTES);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edlya - Réinitialisation</title>
    <meta http-equiv="refresh" content="2;url={$deepLink}">
</head>
<body style="font-family: -apple-system, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f5f5f5;">
    <div style="text-align: center; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 400px;">
        <div style="font-size: 28px; font-weight: bold; color: #4F46E5; margin-bottom: 20px;">Edlya</div>
        <p style="color: #374151; margin-bottom: 20px;">Ouverture de l'application...</p>
        <a href="{$deepLink}" style="display: inline-block; background: #4F46E5; color: white; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600;">
            Ouvrir Edlya
        </a>
        <p style="color: #9CA3AF; font-size: 12px; margin-top: 20px;">
            Si l'application ne s'ouvre pas automatiquement, appuyez sur le bouton ci-dessus.
        </p>
    </div>
</body>
</html>
HTML;

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Génère le contenu HTML de l'email de réinitialisation
     */
    private function renderResetPasswordEmail(User $user, string $resetLink): string
    {
        $userName = htmlspecialchars($user->getName());
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
    <div style="background-color: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
        <div style="text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #4F46E5;">
            <div style="font-size: 28px; font-weight: bold; color: #4F46E5;">Edlya</div>
        </div>

        <h1 style="color: #1f2937; font-size: 22px; margin-bottom: 20px;">Réinitialisation de mot de passe</h1>

        <p>Bonjour {$userName},</p>

        <p>Vous avez demandé la réinitialisation de votre mot de passe sur Edlya.</p>

        <p>Cliquez sur le bouton ci-dessous pour ouvrir l'application et définir un nouveau mot de passe :</p>

        <div style="text-align: center; margin: 25px 0;">
            <a href="{$resetLink}" style="display: inline-block; background-color: #4F46E5; color: #ffffff !important; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: 600;">
                Réinitialiser mon mot de passe
            </a>
        </div>

        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin: 15px 0; font-size: 14px;">
            Ce lien est valable pendant <strong>1 heure</strong>. Passé ce délai, vous devrez faire une nouvelle demande.
        </div>

        <p style="color: #6b7280; font-size: 14px; margin-top: 20px;">
            Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email.
            Votre mot de passe restera inchangé.
        </p>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 12px; color: #6b7280;">
            <p>Cet email a été envoyé automatiquement par Edlya.</p>
            <p>Si vous n'êtes pas concerné par ce message, veuillez l'ignorer.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}