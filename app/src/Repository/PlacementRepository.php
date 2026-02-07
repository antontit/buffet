<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Placement;
use App\Entity\Shelf;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PlacementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Placement::class);
    }

    /**
     * @return list<Placement>
     */
    public function findByShelfWithDish(Shelf $shelf): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.dish', 'd')
            ->addSelect('d')
            ->where('p.shelf = :shelf')
            ->setParameter('shelf', $shelf)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Placement>
     */
    public function findByStackId(int $stackId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.stackId = :stackId')
            ->setParameter('stackId', $stackId)
            ->orderBy('p.stackIndex', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getNextStackId(): int
    {
        $value = $this->getEntityManager()
            ->getConnection()
            ->fetchOne('SELECT COALESCE(MAX(stack_id), 0) + 1 FROM placement');

        return (int) $value;
    }
}
