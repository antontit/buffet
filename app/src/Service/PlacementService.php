<?php

declare(strict_types=1);

namespace App\Service;

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
    public function updatePosition(Placement $placement, int $x, int $y, ?Shelf $shelf): Placement
    {
        $placement->setX($x);
        $placement->setY($y);

        if ($shelf !== null) {
            $placement->setShelf($shelf);
        }

        try {
            $this->entityManager->flush();
        } catch (DbalException $exception) {
            if ($this->isCollisionException($exception)) {
                throw new CollisionException('Placement collides with existing items.', 0, $exception);
            }

            throw $exception;
        }

        return $placement;
    }

    private function isCollisionException(DbalException $exception): bool
    {
        $sqlState = $exception->getSQLSTATE();
        if ($sqlState === '23P01') {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof DbalException && $previous->getSQLSTATE() === '23P01') {
            return true;
        }

        return false;
    }
}
