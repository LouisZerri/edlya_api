<?php

namespace App\Repository;

use App\Entity\Partage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Partage>
 */
class PartageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partage::class);
    }

    public function findValidByToken(string $token): ?Partage
    {
        return $this->createQueryBuilder('p')
            ->where('p.token = :token')
            ->andWhere('p.expireAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
