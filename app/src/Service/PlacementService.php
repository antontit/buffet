<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Dish;
use App\Entity\Placement;
use App\Entity\Shelf;
use App\Exception\CollisionException;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;

final class PlacementService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @throw CollisionException
     */
    public function updatePosition(Placement $placement, int $x, int $y, ?Shelf $shelf): Placement {
        $placement->setX($x);
        $placement->setY($y);

        if ($shelf !== null) {
            $placement->setShelf($shelf);
        }

        try {
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            if ($this->isCollisionException($exception)) {
                throw new CollisionException('Placement collides with existing items.', 0, $exception);
            }

            throw $exception;
        }

        return $placement;
    }

    public function placeDishOnShelf(Shelf $shelf, Dish $dish): ?Placement {
        $maxX = $shelf->getWidth() - $dish->getWidth();
        $maxY = $shelf->getHeight() - $dish->getHeight();
        if ($maxX < 0 || $maxY < 0) {
            return null;
        }

        $connection = $this->entityManager->getConnection();
        $coords = $this->findFirstFreeSpot($connection,
            $shelf->getId(),
            $maxX,
            $maxY,
            $dish->getWidth(),
            $dish->getHeight()
        );

        if ($coords === null) {
            return null;
        }

        $placement = $this->createPlacement($shelf, $dish, $coords['x'], $coords['y']);

        try {
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->entityManager->detach($placement);

            if ($this->isCollisionException($exception)) {
                $coords = $this->findFirstFreeSpot(
                    $connection,
                    $shelf->getId(),
                    $maxX,
                    $maxY,
                    $dish->getWidth(),
                    $dish->getHeight()
                );
                if ($coords === null) {
                    return null;
                }

                $placement = $this->createPlacement($shelf, $dish, $coords['x'], $coords['y']);
                $this->entityManager->flush();

                return $placement;
            }

            throw $exception;
        }

        return $placement;
    }

    /**
     * @return array{x:int, y:int}|null
     */
    private function findFirstFreeSpot(
        \Doctrine\DBAL\Connection $connection,
        int $shelfId,
        int $maxX,
        int $maxY,
        int $width,
        int $height
    ): ?array {
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

        $row = $connection->fetchAssociative($sql, [
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
            'x' => (int)$row['x'],
            'y' => (int)$row['y'],
        ];
    }

    private function createPlacement(Shelf $shelf, Dish $dish, int $x, int $y): Placement {
        $placement = new Placement();
        $placement->setShelf($shelf);
        $placement->setDish($dish);
        $placement->setX($x);
        $placement->setY($y);
        $placement->setWidth($dish->getWidth());
        $placement->setHeight($dish->getHeight());
        $this->entityManager->persist($placement);

        return $placement;
    }

    private function isCollisionException(\Throwable $exception): bool {
        if ($exception instanceof DbalException && $exception->getSQLSTATE() === '23P01') {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof \Throwable) {
            return $this->isCollisionException($previous);
        }

        return false;
    }
}
