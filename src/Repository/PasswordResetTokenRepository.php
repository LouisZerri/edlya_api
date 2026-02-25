<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    /**
     * Trouve un token valide (non expiré et non utilisé)
     */
    public function findValidToken(string $token): ?PasswordResetToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.token = :token')
            ->andWhere('t.expiresAt > :now')
            ->andWhere('t.usedAt IS NULL')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Invalide tous les tokens précédents d'un utilisateur
     */
    public function invalidateUserTokens(User $user): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.usedAt', ':now')
            ->where('t.user = :user')
            ->andWhere('t.usedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les tokens expirés (nettoyage)
     */
    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
