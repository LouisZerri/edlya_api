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
            ->leftJoin('p.etatDesLieux', 'edl')->addSelect('edl')
            ->leftJoin('edl.logement', 'l')->addSelect('l')
            ->leftJoin('edl.user', 'u')->addSelect('u')
            ->leftJoin('edl.pieces', 'pi')->addSelect('pi')
            ->leftJoin('pi.elements', 'el')->addSelect('el')
            ->leftJoin('el.photos', 'ph')->addSelect('ph')
            ->leftJoin('edl.compteurs', 'co')->addSelect('co')
            ->leftJoin('edl.cles', 'cl')->addSelect('cl')
            ->where('p.token = :token')
            ->andWhere('p.expireAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
