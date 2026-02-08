<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Stack;
use App\Repository\StackRepository;

final class BuffetLayoutBuilder
{
    public function __construct(private readonly StackRepository $stackRepository)
    {
    }

    /**
     * @return array{
     *     placementsByShelf: array<int, list<Stack>>,
     *     stackGroupsByShelf: array<int, list<array{placement: Stack, count: int}>>
     * }
     */
    public function build(): array
    {
        $placementsByShelf = [];
        $stackGroupsByShelf = [];

        foreach ($this->stackRepository->findAllWithDish() as $placement) {
            $shelfId = $placement->getShelf()->getId();
            if (!isset($placementsByShelf[$shelfId])) {
                $placementsByShelf[$shelfId] = [];
            }
            $placementsByShelf[$shelfId][] = $placement;
        }

        foreach ($placementsByShelf as $shelfId => $placements) {
            foreach ($placements as $placement) {
                $stackGroupsByShelf[$shelfId][] = [
                    'placement' => $placement,
                    'count' => $placement->getCount(),
                ];
            }
        }

        return [
            'placementsByShelf' => $placementsByShelf,
            'stackGroupsByShelf' => $stackGroupsByShelf,
        ];
    }
}
