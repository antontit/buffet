<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Exception\CollisionException;
use App\Repository\DishRepository;
use App\Repository\StackRepository;
use App\Repository\ShelfRepository;
use App\Service\StackService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/shelves')]
final class ShelfController
{
    public function __construct(
        private readonly ShelfRepository $shelfRepository,
        private readonly StackRepository $stackRepository,
        private readonly DishRepository $dishRepository,
        private readonly StackService $stackService
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

        $stacks = $this->stackRepository->findByShelfWithDish($shelf);

        $payload = [
            'shelf' => [
                'id' => $shelf->getId(),
                'name' => $shelf->getName(),
                'width' => $shelf->getWidth(),
                'height' => $shelf->getHeight(),
                'x' => $shelf->getX(),
                'y' => $shelf->getY(),
            ],
            'stacks' => array_map(
                static function (\App\Entity\Stack $stack): array {
                    $dish = $stack->getDish();

                    return [
                        'id' => $stack->getId(),
                        'x' => $stack->getX(),
                        'y' => $stack->getY(),
                        'width' => $stack->getWidth(),
                        'height' => $stack->getHeight(),
                        'count' => $stack->getCount(),
                        'dish' => [
                            'id' => $dish->getId(),
                            'name' => $dish->getName(),
                            'type' => $dish->getType(),
                            'image' => $dish->getImage(),
                            'width' => $dish->getWidth(),
                            'height' => $dish->getHeight(),
                            'stackLimit' => $dish->getStackLimit(),
                        ],
                    ];
                },
                $stacks
            ),
        ];

        return new JsonResponse($payload, Response::HTTP_OK);
    }

    #[Route('/{id}/stacks', name: 'api_shelves_add_stack', methods: ['POST'])]
    public function addStack(int $id, Request $request): JsonResponse
    {
        $validation = $this->validateAddStackRequest($request, $id);
        if ($validation instanceof JsonResponse) {
            return $validation;
        }
        ['shelf' => $shelf, 'dish' => $dish, 'x' => $x] = $validation;

        try {
            $stack = $this->stackService->placeDishOnShelf($x, $shelf, $dish);
        } catch (CollisionException) {
            return new JsonResponse(
                ['error' => 'Collision detected'],
                Response::HTTP_CONFLICT
            );
        }
        if ($stack === null) {
            return new JsonResponse(
                ['error' => 'No space available'],
                Response::HTTP_CONFLICT
            );
        }

        return new JsonResponse(
            [
                'id' => $stack->getId(),
                'shelfId' => $stack->getShelf()->getId(),
                'dishId' => $stack->getDish()->getId(),
                'x' => $stack->getX(),
                'y' => $stack->getY(),
                'width' => $stack->getWidth(),
                'height' => $stack->getHeight(),
                'count' => $stack->getCount(),
            ],
            Response::HTTP_CREATED
        );
    }

    /**
     * @return array{shelf: \App\Entity\Shelf, dish: \App\Entity\Dish, x: int}|\Symfony\Component\HttpFoundation\JsonResponse
     */
    private function validateAddStackRequest(Request $request, int $id): array|JsonResponse
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

        if (!array_key_exists('x', $payload)) {
            return new JsonResponse(['error' => 'Missing x'], Response::HTTP_BAD_REQUEST);
        }

        $rawX = $payload['x'];
        if (!is_int($rawX) && !ctype_digit((string) $rawX)) {
            return new JsonResponse(['error' => 'x must be an integer'], Response::HTTP_BAD_REQUEST);
        }

        return [
            'shelf' => $shelf,
            'dish' => $dish,
            'x' => (int) $rawX,
        ];
    }
}
