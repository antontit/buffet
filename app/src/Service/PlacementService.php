<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Dish;
use App\Entity\Placement;
use App\Entity\Shelf;
use App\Exception\CollisionException;
use App\Repository\PlacementRepository;
use Doctrine\DBAL\Exception as DbalException;

final class PlacementService
{
    public function __construct(
        private readonly PlacementRepository $placementRepository
    ) {}

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
            $this->placementRepository->flush();
        } catch (\Throwable $exception) {
            if ($this->isCollisionException($exception)) {
                throw new CollisionException('Placement collides with existing items.', 0, $exception);
            }

            throw $exception;
        }

        return $placement;
    }

    public function placeDishOnShelf(Shelf $shelf, Dish $dish): ?Placement {
        $stackTarget = $this->placementRepository->findStackTargetForDishOnShelf($shelf->getId(), $dish->getId());
        if ($stackTarget !== null) {
            $placement = $this->placementRepository->createStackedPlacement($shelf, $dish, $stackTarget);
            $this->placementRepository->flush();

            return $placement;
        }

        $maxX = $shelf->getWidth() - $dish->getWidth();
        $maxY = $shelf->getHeight() - $dish->getHeight();
        if ($maxX < 0 || $maxY < 0) {
            return null;
        }

        $coords = $this->placementRepository->findFirstFreeSpot(
            $shelf->getId(),
            $maxX,
            $maxY,
            $dish->getWidth(),
            $dish->getHeight()
        );

        if ($coords === null) {
            return null;
        }

        $placement = $this->placementRepository->createPlacement($shelf, $dish, $coords['x'], $coords['y']);

        try {
            $this->placementRepository->flush();
        } catch (\Throwable $exception) {
            $this->placementRepository->detach($placement);

            if ($this->isCollisionException($exception)) {
                $coords = $this->placementRepository->findFirstFreeSpot(
                    $shelf->getId(),
                    $maxX,
                    $maxY,
                    $dish->getWidth(),
                    $dish->getHeight()
                );
                if ($coords === null) {
                    return null;
                }

                $placement = $this->placementRepository->createPlacement($shelf, $dish, $coords['x'], $coords['y']);
                $this->placementRepository->flush();

                return $placement;
            }

            throw $exception;
        }

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
