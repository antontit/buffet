<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\PlacementRepository;
use App\Repository\ShelfRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/shelves')]
final class ShelfController
{
    public function __construct(
        private readonly ShelfRepository $shelfRepository,
        private readonly PlacementRepository $placementRepository
    ) {
    }

    #[Route('/{id}', name: 'api_shelves_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var \App\Entity\Shelf $shelf */
        $shelf = $this->shelfRepository->find($id);
        if ($shelf === null) {
            return new JsonResponse(['error' => 'Shelf not found'], Response::HTTP_NOT_FOUND);
        }

        $placements = $this->placementRepository->findByShelfWithDish($shelf);

        $payload = [
            'shelf' => [
                'id' => $shelf->getId(),
                'name' => $shelf->getName(),
                'width' => $shelf->getWidth(),
                'height' => $shelf->getHeight(),
                'x' => $shelf->getX(),
                'y' => $shelf->getY(),
            ],
            'placements' => array_map(
                static function (\App\Entity\Placement $placement): array {
                    $dish = $placement->getDish();

                    return [
                        'id' => $placement->getId(),
                        'x' => $placement->getX(),
                        'y' => $placement->getY(),
                        'width' => $placement->getWidth(),
                        'height' => $placement->getHeight(),
                        'stackId' => $placement->getStackId(),
                        'stackIndex' => $placement->getStackIndex(),
                        'dish' => [
                            'id' => $dish->getId(),
                            'name' => $dish->getName(),
                            'type' => $dish->getType(),
                            'image' => $dish->getImage(),
                            'width' => $dish->getWidth(),
                            'height' => $dish->getHeight(),
                            'isStacked' => $dish->isStacked(),
                        ],
                    ];
                },
                $placements
            ),
        ];

        return new JsonResponse($payload, Response::HTTP_OK);
    }
}
