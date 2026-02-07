<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Placement;
use App\Repository\PlacementRepository;
use Doctrine\ORM\EntityManagerInterface;

final class StackService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlacementRepository $placementRepository
    ) {
    }

    /**
     * @return list<Placement>
     */
    public function merge(Placement $source, Placement $target, string|int|null $position): array
    {
        if ($source->getId() === $target->getId()) {
            throw new \InvalidArgumentException('Source and target must differ.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $targetStackId = $target->getStackId();
            if ($targetStackId === null) {
                $targetStackId = $this->placementRepository->getNextStackId();
                $target->setStackId($targetStackId);
                $target->setStackIndex(0);
            }

            $this->detachFromOldStack($source, $targetStackId);

            $source->setShelf($target->getShelf());
            $source->setX($target->getX());
            $source->setY($target->getY());
            $source->setStackId($targetStackId);

            $items = $this->placementRepository->findByStackId($targetStackId);
            $items = array_values(array_filter(
                $items,
                static fn (Placement $item): bool => $item->getId() !== $source->getId()
            ));

            $items = $this->insertByPosition($items, $source, $position);
            $this->normalizeStackIndexes($items);

            $this->entityManager->flush();
            $connection->commit();

            return $items;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @return list<Placement>
     */
    public function unstack(Placement $placement): array
    {
        $stackId = $placement->getStackId();
        if ($stackId === null) {
            throw new \InvalidArgumentException('Placement is not in a stack.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $siblings = $this->placementRepository->findByStackId($stackId);
            $remaining = array_values(array_filter(
                $siblings,
                static fn (Placement $item): bool => $item->getId() !== $placement->getId()
            ));

            $placement->setStackId(null);
            $placement->setStackIndex(null);

            if (count($remaining) <= 1) {
                foreach ($remaining as $item) {
                    $item->setStackId(null);
                    $item->setStackIndex(null);
                }

                $this->entityManager->flush();
                $connection->commit();

                return $remaining;
            }

            $this->normalizeStackIndexes($remaining);

            $this->entityManager->flush();
            $connection->commit();

            return $remaining;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @return list<Placement>
     */
    public function moveStack(int $stackId, \App\Entity\Shelf $shelf, int $x, int $y): array
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $items = $this->placementRepository->findByStackId($stackId);
            if ($items === []) {
                throw new \InvalidArgumentException('Stack not found.');
            }

            foreach ($items as $placement) {
                $placement->setShelf($shelf);
                $placement->setX($x);
                $placement->setY($y);
            }

            $this->entityManager->flush();
            $connection->commit();

            return $items;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @param list<Placement> $items
     * @return list<Placement>
     */
    private function insertByPosition(array $items, Placement $source, string|int|null $position): array
    {
        if (is_int($position)) {
            $index = max(0, min($position, count($items)));
            array_splice($items, $index, 0, [$source]);

            return $items;
        }

        if (is_string($position) && strtolower($position) === 'bottom') {
            array_unshift($items, $source);

            return $items;
        }

        $items[] = $source;

        return $items;
    }

    /**
     * @param list<Placement> $items
     */
    private function normalizeStackIndexes(array $items): void
    {
        foreach ($items as $index => $placement) {
            $placement->setStackIndex($index);
        }
    }

    private function detachFromOldStack(Placement $source, int $targetStackId): void
    {
        $oldStackId = $source->getStackId();
        if ($oldStackId === null || $oldStackId === $targetStackId) {
            return;
        }

        $siblings = $this->placementRepository->findByStackId($oldStackId);
        $siblings = array_values(array_filter(
            $siblings,
            static fn (Placement $item): bool => $item->getId() !== $source->getId()
        ));

        if (count($siblings) === 1) {
            $remaining = $siblings[0];
            $remaining->setStackId(null);
            $remaining->setStackIndex(null);

            return;
        }

        foreach ($siblings as $index => $placement) {
            $placement->setStackIndex($index);
        }
    }
}
