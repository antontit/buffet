<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Stack;
use App\Entity\Shelf;
use App\Exception\CollisionException;
use App\Factory\StackFactory;
use App\Repository\StackRepository;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class StackService
{
    private const SQLSTATE_EXCLUSION_VIOLATION = '23P01';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private StackRepository $stackRepository,
        private StackFactory $stackFactory
    ) {
    }

    /**
     * @return array{
     *     target: Stack,
     *     source: Stack|null,
     *     movedCount: int,
     *     sourceRemaining: int
     * }
     */
    public function merge(Stack $source, Stack $target): array
    {
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

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $available = max(0, $limit - $target->getCount());
            $movedCount = min($available, $source->getCount());

            if ($movedCount === 0) {
                $connection->commit();

                return [
                    'target' => $target,
                    'source' => $source,
                    'movedCount' => 0,
                    'sourceRemaining' => $source->getCount(),
                ];
            }

            $target->setCount($target->getCount() + $movedCount);
            $sourceRemaining = $source->getCount() - $movedCount;

            if ($sourceRemaining <= 0) {
                $this->stackRepository->remove($source);
                $source = null;
                $sourceRemaining = 0;
            } else {
                $source->setCount($sourceRemaining);
            }

            $this->entityManager->flush();
            $connection->commit();

            return [
                'target' => $target,
                'source' => $source,
                'movedCount' => $movedCount,
                'sourceRemaining' => $sourceRemaining,
            ];
        } catch (\Throwable $exception) {
            $connection->rollBack();
            if ($this->isCollisionException($exception)) {
                throw new CollisionException('Stack collides with existing items.', 0, $exception);
            }
            throw $exception;
        }
    }

    public function removeOne(Stack $stack): Stack
    {
        $current = $stack->getCount();
        if ($current <= 1) {
            $this->stackRepository->remove($stack, true);
            return $stack;
        }

        $stack->setCount($current - 1);
        $this->stackRepository->save($stack);

        return $stack;
    }

    public function move(Stack $stack, Shelf $shelf, int $x, int $y): Stack
    {
        $stack->setShelf($shelf);
        $stack->setX($x);
        $stack->setY($y);
        $this->stackRepository->save($stack);

        return $stack;
    }

    /**
     * @throw CollisionException
     */
    public function updatePosition(Stack $stack, int $x, int $y, ?Shelf $shelf): Stack
    {
        $stack->setX($x);
        $stack->setY(0);

        if ($shelf !== null) {
            $stack->setShelf($shelf);
        }

        $this->saveWithCollisionCheck($stack);

        return $stack;
    }

    public function placeDishOnShelf(Shelf $shelf, \App\Entity\Dish $dish, int $x): ?Stack
    {
        $maxX = $shelf->getWidth() - $dish->getWidth();
        if ($maxX < 0) {
            return null;
        }

        $clampedX = max(0, min($x, $maxX));

        return $this->placeOnSpecificX($shelf, $dish, $clampedX, $maxX);
    }

    public function placeDishOnShelfStacked(Shelf $shelf, \App\Entity\Dish $dish, Stack $target): Stack
    {
        $this->assertStackable($shelf, $dish, $target);

        try {
            return $this->stackRepository->transactional(function () use ($dish, $target): Stack {
                $nextCount = $target->getCount() + 1;
                if ($nextCount > $dish->getStackLimit()) {
                    throw new \InvalidArgumentException('Stack is full.');
                }
                $target->setCount($nextCount);
                $this->stackRepository->save($target);

                return $target;
            });
        } catch (\Throwable $exception) {
            $this->rethrowCollision($exception);
        }
    }

    private function assertStackable(Shelf $shelf, \App\Entity\Dish $dish, Stack $target): void
    {
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

    private function saveWithCollisionCheck(Stack $stack): void
    {
        try {
            $this->stackRepository->save($stack);
        } catch (\Throwable $exception) {
            $this->rethrowCollision($exception);
        }
    }

    private function rethrowCollision(\Throwable $exception): void
    {
        if ($this->isCollisionException($exception)) {
            throw new CollisionException('Stack collides with existing items.', 0, $exception);
        }

        throw $exception;
    }

    private function placeOnSpecificX(Shelf $shelf, \App\Entity\Dish $dish, int $x, int $maxX): ?Stack
    {
        $clampedX = max(0, min($x, $maxX));
        $stack = $this->stackFactory->create($shelf, $dish, $clampedX, 0);

        try {
            $this->saveWithCollisionCheck($stack);
        } catch (CollisionException) {
            $this->stackRepository->detach($stack);
            return null;
        }

        return $stack;
    }

    private function isCollisionException(\Throwable $exception): bool
    {
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
