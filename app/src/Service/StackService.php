<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Dish;
use App\Entity\Shelf;
use App\Entity\Stack;
use App\Factory\StackFactory;
use App\Repository\StackRepository;

final readonly class StackService
{
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

        $result = $this->stackRepository->mergeStacks($movedCount, $sourceRemaining, $source, $target);

        return new StackMergeResult(
            $result['movedCount'],
            $result['sourceRemaining'],
            $result['target'],
            $result['source']
        );
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
        $stack = $this->stackFactory->create($clampedX, 0, $shelf, $dish);

        try {
            $this->stackRepository->saveWithCollisionCheck($stack);
        } catch (\App\Exception\CollisionException) {
            return null;
        }

        return $stack;
    }

    /**
     * @throws \Throwable|\InvalidArgumentException|\App\Exception\CollisionException
     */
    public function addDish(Dish $dish, Stack $stack): Stack {

        if ($dish->getStackLimit() <= 1) {
            throw new \InvalidArgumentException('Dish is not stackable.');
        }

        if ($stack->getDish()->getType() !== $dish->getType()) {
            throw new \InvalidArgumentException('Dish types do not match.');
        }

        $nextCount = $stack->getCount() + 1;
        if ($nextCount > $dish->getStackLimit()) {
            throw new \InvalidArgumentException('Stack is full.');
        }
        $stack->setCount($nextCount);
        $this->stackRepository->saveWithCollisionCheck($stack);

        return $stack;
    }
}
