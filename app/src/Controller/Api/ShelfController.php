<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\DishRepository;
use App\Repository\PlacementRepository;
use App\Repository\ShelfRepository;
use App\Exception\CollisionException;
use App\Service\PlacementService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/shelves')]
final class ShelfController
{
    public function __construct(
        private readonly ShelfRepository $shelfRepository,
        private readonly PlacementRepository $placementRepository,
        private readonly DishRepository $dishRepository,
        private readonly PlacementService $placementService
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

    #[Route('/{id}/placements', name: 'api_shelves_add_placement', methods: ['POST'])]
    public function addPlacement(int $id, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['shelfId'], $payload['dishId'])) {
            return new JsonResponse(['error' => 'Missing shelfId or dishId'], Response::HTTP_BAD_REQUEST);
        }

        $shelfId = $payload['shelfId'];
        $dishId = $payload['dishId'];
        if ((!is_int($shelfId) && !ctype_digit((string) $shelfId))
            || (!is_int($dishId) && !ctype_digit((string) $dishId))
        ) {
            return new JsonResponse(['error' => 'shelfId and dishId must be integers'], Response::HTTP_BAD_REQUEST);
        }

        if ((int) $shelfId !== $id) {
            return new JsonResponse(['error' => 'shelfId does not match route id'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\Shelf $shelf */
        $shelf = $this->shelfRepository->find($id);
        if ($shelf === null) {
            return new JsonResponse(['error' => 'Shelf not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var \App\Entity\Dish $dish */
        $dish = $this->dishRepository->find((int) $dishId);
        if ($dish === null) {
            return new JsonResponse(['error' => 'Dish not found'], Response::HTTP_NOT_FOUND);
        }

        $x = null;
        if (array_key_exists('x', $payload)) {
            $rawX = $payload['x'];
            if (!is_int($rawX) && !ctype_digit((string) $rawX)) {
                return new JsonResponse(['error' => 'x must be an integer'], Response::HTTP_BAD_REQUEST);
            }
            $x = (int) $rawX;
        }

        if (isset($payload['targetPlacementId'])) {
            $targetId = $payload['targetPlacementId'];
            if (!is_int($targetId) && !ctype_digit((string) $targetId)) {
                return new JsonResponse(['error' => 'targetPlacementId must be an integer'], Response::HTTP_BAD_REQUEST);
            }

            /** @var \App\Entity\Placement|null $target */
            $target = $this->placementRepository->find((int) $targetId);
            if ($target === null) {
                return new JsonResponse(['error' => 'Target placement not found'], Response::HTTP_NOT_FOUND);
            }

            try {
                $placement = $this->placementService->placeDishOnShelfStacked($shelf, $dish, $target);
            } catch (CollisionException) {
                return new JsonResponse(
                    ['error' => 'Collision detected'],
                    Response::HTTP_CONFLICT,
                    ['X-No-Space' => '1']
                );
            } catch (\InvalidArgumentException $exception) {
                return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        } else {
            $placement = $this->placementService->placeDishOnShelf($shelf, $dish, $x);
        }
        if ($placement === null) {
            return new JsonResponse(
                ['error' => 'No space available'],
                Response::HTTP_CONFLICT,
                ['X-No-Space' => '1']
            );
        }

        return new JsonResponse(
            [
                'id' => $placement->getId(),
                'shelfId' => $placement->getShelf()->getId(),
                'dishId' => $placement->getDish()->getId(),
                'x' => $placement->getX(),
                'y' => $placement->getY(),
                'width' => $placement->getWidth(),
                'height' => $placement->getHeight(),
            ],
            Response::HTTP_CREATED
        );
    }
}
