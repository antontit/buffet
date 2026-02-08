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
    private const SQLSTATE_EXCLUSION_VIOLATION = '23P01';

    public function __construct(
        private PlacementRepository $placementRepository,
        private PlacementFactory $placementFactory
    ) {}

    /**
     * @throw CollisionException
     */
    public function updatePosition(Placement $placement, int $x, int $y, ?Shelf $shelf): Placement {
        $placement->setX($x);
        $placement->setY(0);

        if ($shelf !== null) {
            $placement->setShelf($shelf);
        }

        $this->saveWithCollisionCheck($placement);

        return $placement;
    }

    public function placeDishOnShelf(Shelf $shelf, Dish $dish, int $x): ?Placement {
        $stackTarget = $this->placementRepository->findStackTargetForDishOnShelf($shelf->getId(), $dish->getId());
        if ($stackTarget !== null) {
            return $this->placeOnExistingStack($shelf, $dish, $stackTarget);
        }

        $maxX = $shelf->getWidth() - $dish->getWidth();
        if ($maxX < 0) {
            return null;
        }

        return $this->placeOnSpecificX($shelf, $dish, $x, $maxX);
    }

    public function placeDishOnShelfStacked(Shelf $shelf, Dish $dish, Placement $target): Placement
    {
        $this->assertStackable($shelf, $dish, $target);

        try {
            return $this->placementRepository->transactional(function () use ($shelf, $dish, $target): Placement {
                $stackId = $target->getStackId();
                if ($stackId === null) {
                $stackId = $this->placementRepository->getNextStackId();
                $target->setStackId($stackId);
                $target->setStackIndex(0);
                $target->setY(0);
                $this->placementRepository->save($target);
            }

            $stackIndex = $this->placementRepository->getNextStackIndex((int) $stackId);
            $placement = $this->placementFactory->createStacked($shelf, $dish, $target, $stackIndex);
            $this->placementRepository->save($placement);

                return $placement;
            });
        } catch (\Throwable $exception) {
            $this->rethrowCollision($exception);
        }
    }

    private function assertStackable(Shelf $shelf, Dish $dish, Placement $target): void
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
    }

    private function placeOnExistingStack(Shelf $shelf, Dish $dish, Placement $stackTarget): Placement
    {
        if ($stackTarget->getY() !== 0) {
            $stackTarget->setY(0);
            $this->placementRepository->save($stackTarget);
        }

        $stackIndex = $this->placementRepository->getNextStackIndex((int) $stackTarget->getStackId());
        $placement = $this->placementFactory->createStacked($shelf, $dish, $stackTarget, $stackIndex);
        $this->placementRepository->save($placement);

        return $placement;
    }

    private function saveWithCollisionCheck(Placement $placement): void
    {
        try {
            $this->placementRepository->save($placement);
        } catch (\Throwable $exception) {
            $this->rethrowCollision($exception);
        }
    }

    private function rethrowCollision(\Throwable $exception): void
    {
        if ($this->isCollisionException($exception)) {
            throw new CollisionException('Placement collides with existing items.', 0, $exception);
        }

        throw $exception;
    }

    private function isCollisionException(\Throwable $exception): bool {
        if ($exception instanceof DbalException && $exception->getSQLSTATE() === self::SQLSTATE_EXCLUSION_VIOLATION) {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof \Throwable) {
            return $this->isCollisionException($previous);
        }

        return false;
    }

    private function placeOnSpecificX(Shelf $shelf, Dish $dish, int $x, int $maxX): ?Placement
    {
        $clampedX = max(0, min($x, $maxX));
        $placement = $this->placementFactory->create($shelf, $dish, $clampedX, 0);

        try {
            $this->saveWithCollisionCheck($placement);
        } catch (CollisionException) {
            $this->placementRepository->detach($placement);
            return null;
        }

        return $placement;
    }
}
