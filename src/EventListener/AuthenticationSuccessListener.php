<?php

namespace App\EventListener;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

class AuthenticationSuccessListener
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $refreshToken = new RefreshToken();
        $refreshToken->setToken(RefreshToken::generateToken());
        $refreshToken->setUser($user);
        $refreshToken->setExpiresAt(new \DateTimeImmutable('+30 days'));

        $this->em->persist($refreshToken);
        $this->em->flush();

        $data = $event->getData();
        $data['refresh_token'] = $refreshToken->getToken();
        $event->setData($data);
    }
}
