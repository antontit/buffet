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
    public function findAllWithDish(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.dish', 'd')
            ->addSelect('d')
            ->leftJoin('p.shelf', 's')
            ->addSelect('s')
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

    public function getStackCount(int $stackId): int
    {
        $value = $this->getEntityManager()
            ->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM placement WHERE stack_id = :stackId', [
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

    public function findStackTargetForDishTypeOnShelf(int $shelfId, string $dishType): ?Placement
    {
        return $this->createQueryBuilder('p')
            ->join('p.dish', 'd')
            ->where('p.shelf = :shelfId')
            ->andWhere('d.type = :type')
            ->andWhere('p.stackId IS NOT NULL')
            ->setParameter('shelfId', $shelfId)
            ->setParameter('type', $dishType)
            ->orderBy('p.stackIndex', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findStackTargetAtXForDishOnShelf(int $shelfId, int $dishId, int $x): ?Placement
    {
        return $this->createQueryBuilder('p')
            ->where('p.shelf = :shelfId')
            ->andWhere('p.dish = :dishId')
            ->andWhere('p.stackId IS NOT NULL')
            ->andWhere('p.x = :x')
            ->setParameter('shelfId', $shelfId)
            ->setParameter('dishId', $dishId)
            ->setParameter('x', $x)
            ->orderBy('p.stackIndex', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findTopOfStack(int $stackId): ?Placement
    {
        return $this->createQueryBuilder('p')
            ->where('p.stackId = :stackId')
            ->setParameter('stackId', $stackId)
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

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation)
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->beginTransaction();

        try {
            $result = $operation();
            $this->getEntityManager()->flush();
            $connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function detach(object $entity): void
    {
        $this->getEntityManager()->detach($entity);
    }

    public function remove(Placement $placement, bool $flush = false): void
    {
        $this->getEntityManager()->remove($placement);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function removeByStackId(int $stackId): int
    {
        $items = $this->findByStackId($stackId);
        if ($items === []) {
            return 0;
        }

        foreach ($items as $placement) {
            $this->getEntityManager()->remove($placement);
        }

        $this->getEntityManager()->flush();

        return count($items);
    }
}
