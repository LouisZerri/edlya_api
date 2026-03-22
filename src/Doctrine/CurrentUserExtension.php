<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Cle;
use App\Entity\Compteur;
use App\Entity\Element;
use App\Entity\EtatDesLieux;
use App\Entity\Logement;
use App\Entity\Partage;
use App\Entity\Photo;
use App\Entity\Piece;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class CurrentUserExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        // Entités directement liées à User
        if ($resourceClass === Logement::class || $resourceClass === EtatDesLieux::class) {
            $queryBuilder->andWhere(sprintf('%s.user = :current_user', $rootAlias))
                ->setParameter('current_user', $user);
            return;
        }

        // Piece → etatDesLieux.user
        if ($resourceClass === Piece::class) {
            $queryBuilder->innerJoin(sprintf('%s.etatDesLieux', $rootAlias), 'edl_filter')
                ->andWhere('edl_filter.user = :current_user')
                ->setParameter('current_user', $user);
            return;
        }

        // Element → piece.etatDesLieux.user
        if ($resourceClass === Element::class) {
            $queryBuilder->innerJoin(sprintf('%s.piece', $rootAlias), 'piece_filter')
                ->innerJoin('piece_filter.etatDesLieux', 'edl_filter')
                ->andWhere('edl_filter.user = :current_user')
                ->setParameter('current_user', $user);
            return;
        }

        // Photo → element.piece.etatDesLieux.user
        if ($resourceClass === Photo::class) {
            $queryBuilder->innerJoin(sprintf('%s.element', $rootAlias), 'element_filter')
                ->innerJoin('element_filter.piece', 'piece_filter')
                ->innerJoin('piece_filter.etatDesLieux', 'edl_filter')
                ->andWhere('edl_filter.user = :current_user')
                ->setParameter('current_user', $user);
            return;
        }

        // Compteur → etatDesLieux.user
        if ($resourceClass === Compteur::class) {
            $queryBuilder->innerJoin(sprintf('%s.etatDesLieux', $rootAlias), 'edl_filter')
                ->andWhere('edl_filter.user = :current_user')
                ->setParameter('current_user', $user);
            return;
        }

        // Cle → etatDesLieux.user
        if ($resourceClass === Cle::class) {
            $queryBuilder->innerJoin(sprintf('%s.etatDesLieux', $rootAlias), 'edl_filter')
                ->andWhere('edl_filter.user = :current_user')
                ->setParameter('current_user', $user);
            return;
        }

        // Partage → etatDesLieux.user
        if ($resourceClass === Partage::class) {
            $queryBuilder->innerJoin(sprintf('%s.etatDesLieux', $rootAlias), 'edl_filter')
                ->andWhere('edl_filter.user = :current_user')
                ->setParameter('current_user', $user);
            return;
        }
    }
}