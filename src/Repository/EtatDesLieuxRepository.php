<?php

namespace App\Repository;

use App\Entity\EtatDesLieux;
use App\Entity\Logement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EtatDesLieux>
 */
class EtatDesLieuxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EtatDesLieux::class);
    }

    /**
     * Charge un EDL avec toutes ses relations (pièces, éléments, photos, compteurs, clés)
     */
    public function findWithFullRelations(int $id): ?EtatDesLieux
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.pieces', 'pi')->addSelect('pi')
            ->leftJoin('pi.elements', 'el')->addSelect('el')
            ->leftJoin('el.photos', 'ph')->addSelect('ph')
            ->leftJoin('e.compteurs', 'co')->addSelect('co')
            ->leftJoin('e.cles', 'cl')->addSelect('cl')
            ->leftJoin('e.logement', 'l')->addSelect('l')
            ->leftJoin('e.user', 'u')->addSelect('u')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve le dernier EDL d'un logement par type et statuts, avec toutes les relations
     */
    public function findLastByLogementTypeAndStatuts(Logement $logement, string $type, array $statuts): ?EtatDesLieux
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.pieces', 'pi')->addSelect('pi')
            ->leftJoin('pi.elements', 'el')->addSelect('el')
            ->leftJoin('el.photos', 'ph')->addSelect('ph')
            ->leftJoin('e.compteurs', 'co')->addSelect('co')
            ->leftJoin('e.cles', 'cl')->addSelect('cl')
            ->where('e.logement = :logement')
            ->andWhere('e.type = :type')
            ->andWhere('e.statut IN (:statuts)')
            ->setParameter('logement', $logement)
            ->setParameter('type', $type)
            ->setParameter('statuts', $statuts)
            ->orderBy('e.dateRealisation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve le dernier EDL d'un logement par type (tous statuts), avec toutes les relations
     */
    public function findLastByLogementAndType(Logement $logement, string $type): ?EtatDesLieux
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.pieces', 'pi')->addSelect('pi')
            ->leftJoin('pi.elements', 'el')->addSelect('el')
            ->leftJoin('el.photos', 'ph')->addSelect('ph')
            ->leftJoin('e.compteurs', 'co')->addSelect('co')
            ->leftJoin('e.cles', 'cl')->addSelect('cl')
            ->where('e.logement = :logement')
            ->andWhere('e.type = :type')
            ->setParameter('logement', $logement)
            ->setParameter('type', $type)
            ->orderBy('e.dateRealisation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
