<?php

namespace App\Repository;

use App\Entity\ActivationCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivationCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivationCode::class);
    }

    public function findValidCode(string $email, string $code): ?ActivationCode
    {
        return $this->createQueryBuilder('ac')
            ->where('ac.email = :email')
            ->andWhere('ac.code = :code')
            ->andWhere('ac.usedAt IS NULL')
            ->setParameter('email', strtolower(trim($email)))
            ->setParameter('code', strtoupper(trim($code)))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
