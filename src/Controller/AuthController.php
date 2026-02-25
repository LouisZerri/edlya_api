<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
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

        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'Email déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setTelephone($data['telephone'] ?? null);
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
            $user->setName($data['name']);
        }

        if (array_key_exists('telephone', $data)) {
            $user->setTelephone($data['telephone'] ?: null);
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

        // Générer le lien de réinitialisation
        $deepLink = 'edlya://reset-password?token=' . $resetToken->getToken();

        // Envoyer l'email
        try {
            $emailMessage = (new Email())
                ->from($this->fromEmail)
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe Edlya')
                ->html($this->renderResetPasswordEmail($user, $deepLink));

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

        // Validation du mot de passe (minimum 8 caractères)
        if (strlen($password) < 8) {
            return new JsonResponse([
                'error' => 'Le mot de passe doit contenir au moins 8 caractères'
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
     * Génère le contenu HTML de l'email de réinitialisation
     */
    private function renderResetPasswordEmail(User $user, string $deepLink): string
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
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0;">Edlya</h1>
    </div>

    <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
        <h2 style="color: #333;">Bonjour {$userName},</h2>

        <p>Vous avez demandé la réinitialisation de votre mot de passe sur Edlya.</p>

        <p>Cliquez sur le bouton ci-dessous pour ouvrir l'application et définir un nouveau mot de passe :</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{$deepLink}" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
                Ouvrir l'application Edlya
            </a>
        </div>

        <p style="color: #666; font-size: 14px;">
            Ce lien est valable pendant <strong>1 heure</strong>. Passé ce délai, vous devrez faire une nouvelle demande.
        </p>

        <p style="color: #999; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email.<br>
            Votre mot de passe restera inchangé.
        </p>
    </div>

    <div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">
        © {$year} Edlya - États des lieux simplifiés
    </div>
</body>
</html>
HTML;
    }
}