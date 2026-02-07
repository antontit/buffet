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

    /**
     * @return array{x:int, y:int}|null
     */
    public function findFirstFreeSpot(int $shelfId, int $maxX, int $maxY, int $width, int $height): ?array
    {
        $sql = <<<SQL
            WITH candidates AS (
                SELECT x, y
                FROM generate_series(0, :maxX) AS x
                CROSS JOIN generate_series(0, :maxY) AS y
            )
            SELECT c.x, c.y
            FROM candidates c
            WHERE NOT EXISTS (
                SELECT 1
                FROM placement p
                WHERE p.shelf_id = :shelfId
                  AND box(point(c.x, c.y), point(c.x + :width, c.y + :height))
                      && box(point(p.x, p.y), point(p.x + p.width, p.y + p.height))
            )
            ORDER BY c.y, c.x
            LIMIT 1
            SQL;

        $row = $this->getEntityManager()->getConnection()->fetchAssociative($sql, [
            'maxX' => $maxX,
            'maxY' => $maxY,
            'shelfId' => $shelfId,
            'width' => $width,
            'height' => $height,
        ]);

        if ($row === false) {
            return null;
        }

        return [
            'x' => (int) $row['x'],
            'y' => (int) $row['y'],
        ];
    }


    public function getNextStackIndex(int $stackId): int
    {
        $value = $this->getEntityManager()
            ->getConnection()
            ->fetchOne('SELECT COALESCE(MAX(stack_index), -1) + 1 FROM placement WHERE stack_id = :stackId', [
                'stackId' => $stackId,
            ]);

        return (int) $value;
    }

    public function findStackTargetForDishOnShelf(int $shelfId, int $dishId): ?Placement
    {
        return $this->createQueryBuilder('p')
            ->where('p.shelf = :shelfId')
            ->andWhere('p.dish = :dishId')
            ->andWhere('p.stackId IS NOT NULL')
            ->setParameter('shelfId', $shelfId)
            ->setParameter('dishId', $dishId)
            ->orderBy('p.stackIndex', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Placement $placement): void
    {
        $this->getEntityManager()->persist($placement);
        $this->getEntityManager()->flush();
    }

    public function detach(object $entity): void
    {
        $this->getEntityManager()->detach($entity);
    }
}
