<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Dish;
use App\Entity\Placement;
use App\Entity\Shelf;
use App\Exception\CollisionException;
use App\Factory\PlacementFactory;
use App\Repository\PlacementRepository;
use Doctrine\DBAL\Exception as DbalException;

final readonly class PlacementService
{
    public function __construct(
        private PlacementRepository $placementRepository,
        private PlacementFactory $placementFactory
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
            $this->placementRepository->save($placement);
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
            $stackIndex = $this->placementRepository->getNextStackIndex((int) $stackTarget->getStackId());
            $placement = $this->placementFactory->createStacked($shelf, $dish, $stackTarget, $stackIndex);
            $this->placementRepository->save($placement);

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

        $placement = $this->placementFactory->create($shelf, $dish, $coords['x'], $coords['y']);

        try {
            $this->placementRepository->save($placement);
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

                $placement = $this->placementFactory->create($shelf, $dish, $coords['x'], $coords['y']);
                $this->placementRepository->save($placement);

                return $placement;
            }

            throw $exception;
        }

        return $placement;
    }

    public function placeDishOnShelfStacked(Shelf $shelf, Dish $dish, Placement $target): Placement
    {
        if (!$dish->isStacked()) {
            throw new \InvalidArgumentException('Dish is not stackable.');
        }

        if ($target->getShelf()->getId() !== $shelf->getId()) {
            throw new \InvalidArgumentException('Target shelf mismatch.');
        }

        if ($target->getDish()->getType() !== $dish->getType()) {
            throw new \InvalidArgumentException('Dish types do not match.');
        }

        try {
            return $this->placementRepository->transactional(function () use ($shelf, $dish, $target): Placement {
                $stackId = $target->getStackId();
                if ($stackId === null) {
                    $stackId = $this->placementRepository->getNextStackId();
                    $target->setStackId($stackId);
                    $target->setStackIndex(0);
                    $this->placementRepository->save($target);
                }

                $stackIndex = $this->placementRepository->getNextStackIndex((int) $stackId);
                $placement = $this->placementFactory->createStacked($shelf, $dish, $target, $stackIndex);
                $this->placementRepository->save($placement);

                return $placement;
            });
        } catch (\Throwable $exception) {
            if ($this->isCollisionException($exception)) {
                throw new CollisionException('Placement collides with existing items.', 0, $exception);
            }

            throw $exception;
        }
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
