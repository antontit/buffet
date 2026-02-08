<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Dish;
use App\Entity\Shelf;
use App\Entity\Stack;
use App\Exception\CollisionException;
use App\Factory\StackFactory;
use App\Repository\StackRepository;
use Doctrine\DBAL\Exception as DbalException;

final readonly class StackService
{
    private const SQLSTATE_EXCLUSION_VIOLATION = '23P01';

    public function __construct(
        private StackRepository $stackRepository,
        private StackFactory $stackFactory
    ) {
    }

    public function merge(Stack $source, Stack $target): StackMergeResult {
        if ($source->getId() === $target->getId()) {
            throw new \InvalidArgumentException('Source and target must differ.');
        }

        if ($source->getDish()->getType() !== $target->getDish()->getType()) {
            throw new \InvalidArgumentException('Dish types do not match.');
        }

        $limit = $target->getDish()->getStackLimit();
        if ($limit <= 1) {
            throw new \InvalidArgumentException('Target dish is not stackable.');
        }

        $available = max(0, $limit - $target->getCount());
        $movedCount = min($available, $source->getCount());

        if ($movedCount === 0) {
            return new StackMergeResult(0, $source->getCount(), $target, $source);
        }

        $target->setCount($target->getCount() + $movedCount);
        $sourceRemaining = $source->getCount() - $movedCount;

        try {
            $result = $this->stackRepository->mergeStacks($movedCount, $sourceRemaining, $source, $target);

            return new StackMergeResult(
                $result['movedCount'],
                $result['sourceRemaining'],
                $result['target'],
                $result['source']
            );
        } catch (\Throwable $exception) {
            $this->rethrowCollision($exception);
        }

        throw new \RuntimeException('Unexpected merge failure.');
    }

    public function unstackOneItem(Stack $stack): Stack {
        $current = $stack->getCount();
        if ($current <= 1) {
            $this->stackRepository->remove($stack);
            return $stack;
        }

        $stack->setCount($current - 1);
        $this->stackRepository->save($stack);

        return $stack;
    }

    public function move(int $x, int $y, Shelf $shelf, Stack $stack): Stack {
        $stack->setShelf($shelf);
        $stack->setX($x);
        $stack->setY($y);
        $this->stackRepository->save($stack);

        return $stack;
    }

    public function placeDishOnShelf(int $x, Shelf $shelf, Dish $dish): ?Stack {
        $maxX = $shelf->getWidth() - $dish->getWidth();
        if ($maxX < 0) {
            return null;
        }

        $clampedX = max(0, min($x, $maxX));

        return $this->placeOnSpecificX($clampedX, $maxX, $shelf, $dish);
    }

    public function addDish(Dish $dish, Stack $stack): Stack {
        $this->assertStackable($stack->getShelf(), $dish, $stack);

        try {
            $nextCount = $stack->getCount() + 1;
            if ($nextCount > $dish->getStackLimit()) {
                throw new \InvalidArgumentException('Stack is full.');
            }
            $stack->setCount($nextCount);
            $this->stackRepository->save($stack);

            return $stack;
        } catch (\Throwable $exception) {
            $this->rethrowCollision($exception);
        }
    }

    private function assertStackable(Shelf $shelf, Dish $dish, Stack $target): void {
        if ($dish->getStackLimit() <= 1) {
            throw new \InvalidArgumentException('Dish is not stackable.');
        }

        if ($target->getShelf()->getId() !== $shelf->getId()) {
            throw new \InvalidArgumentException('Target shelf mismatch.');
        }

        if ($target->getDish()->getType() !== $dish->getType()) {
            throw new \InvalidArgumentException('Dish types do not match.');
        }
    }

    private function saveWithCollisionCheck(Stack $stack): void {
        try {
            $this->stackRepository->save($stack);
        } catch (\Throwable $exception) {
            $this->rethrowCollision($exception);
        }
    }

    private function rethrowCollision(\Throwable $exception): void {
        if ($this->isCollisionException($exception)) {
            throw new CollisionException('Stack collides with existing items.', 0, $exception);
        }

        throw $exception;
    }

    private function placeOnSpecificX(int $x, int $maxX, Shelf $shelf, Dish $dish): ?Stack {
        $clampedX = max(0, min($x, $maxX));
        $stack = $this->stackFactory->create($clampedX, 0, $shelf, $dish);

        try {
            $this->saveWithCollisionCheck($stack);
        } catch (CollisionException) {
            $this->stackRepository->detach($stack);
            return null;
        }

        return $stack;
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
}
