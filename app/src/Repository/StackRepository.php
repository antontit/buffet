<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Stack;
use App\Entity\Shelf;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class StackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stack::class);
    }

    /**
     * @return list<Stack>
     */
    public function findByShelfWithDish(Shelf $shelf): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.dish', 'd')
            ->addSelect('d')
            ->where('s.shelf = :shelf')
            ->setParameter('shelf', $shelf)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Stack>
     */
    public function findAllWithDish(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.dish', 'd')
            ->addSelect('d')
            ->leftJoin('s.shelf', 'sh')
            ->addSelect('sh')
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
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
                FROM stack s
                WHERE s.shelf_id = :shelfId
                  AND box(point(c.x, c.y), point(c.x + :width, c.y + :height))
                      && box(point(s.x, s.y), point(s.x + s.width, s.y + s.height))
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

    public function save(Stack $stack): void
    {
        $this->getEntityManager()->persist($stack);
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

    public function remove(Stack $stack, bool $flush = false): void
    {
        $this->getEntityManager()->remove($stack);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
