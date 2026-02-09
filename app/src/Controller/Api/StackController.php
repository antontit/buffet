<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\StackRepository;
use App\Repository\ShelfRepository;
use App\Service\StackService;
use App\Repository\DishRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stacks')]
final class StackController
{
    public function __construct(
        private readonly StackRepository $stackRepository,
        private readonly ShelfRepository $shelfRepository,
        private readonly StackService $stackService,
        private readonly DishRepository $dishRepository
    ) {
    }

    #[Route('/merge', name: 'api_stacks_merge', methods: ['POST'])]
    public function merge(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['sourceStackId'], $payload['targetStackId'])) {
            return new JsonResponse(['error' => 'Missing stack ids'], Response::HTTP_BAD_REQUEST);
        }

        $sourceId = $payload['sourceStackId'];
        $targetId = $payload['targetStackId'];
        if ((!is_int($sourceId) && !ctype_digit((string) $sourceId))
            || (!is_int($targetId) && !ctype_digit((string) $targetId))
        ) {
            return new JsonResponse(['error' => 'Stack ids must be integers'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\Stack|null $source */
        $source = $this->stackRepository->find((int) $sourceId);
        /** @var \App\Entity\Stack|null $target */
        $target = $this->stackRepository->find((int) $targetId);
        if ($source === null || $target === null) {
            return new JsonResponse(['error' => 'Stack not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->stackService->merge($source, $target);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $payload = [
            'targetId' => $result->getTarget()->getId(),
            'targetCount' => $result->getTarget()->getCount(),
            'sourceId' => $result->getSource()?->getId(),
            'sourceRemainingCount' => $result->getSourceRemaining(),
            'movedCount' => $result->getMovedCount(),
        ];

        return new JsonResponse($payload, Response::HTTP_OK);
    }

    #[Route('/unstack', name: 'api_stacks_unstack', methods: ['POST'])]
    public function unstack(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['stackId'])) {
            return new JsonResponse(['error' => 'Missing stackId'], Response::HTTP_BAD_REQUEST);
        }

        $stackId = $payload['stackId'];
        if (!is_int($stackId) && !ctype_digit((string) $stackId)) {
            return new JsonResponse(['error' => 'stackId must be an integer'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\Stack|null $stack */
        $stack = $this->stackRepository->find((int) $stackId);
        if ($stack === null) {
            return new JsonResponse(['error' => 'Stack not found'], Response::HTTP_NOT_FOUND);
        }

        $beforeCount = $stack->getCount();
        $this->stackService->unstackOneItem($stack);

        return new JsonResponse(
            [
                'stackId' => (int) $stackId,
                'removedCount' => 1,
                'remainingCount' => max(0, $beforeCount - 1),
                'deleted' => $beforeCount <= 1,
            ],
            Response::HTTP_OK
        );
    }

    #[Route('/add', name: 'api_stacks_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['dishId'], $payload['targetStackId'])) {
            return new JsonResponse(['error' => 'Missing dishId or targetStackId'], Response::HTTP_BAD_REQUEST);
        }

        $dishId = $payload['dishId'];
        if (!is_int($dishId) && !ctype_digit((string) $dishId)) {
            return new JsonResponse(['error' => 'dishId must be an integer'], Response::HTTP_BAD_REQUEST);
        }

        $targetStackId = $payload['targetStackId'];
        if (!is_int($targetStackId) && !ctype_digit((string) $targetStackId)) {
            return new JsonResponse(['error' => 'targetStackId must be an integer'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\Stack|null $target */
        $target = $this->stackRepository->find((int) $targetStackId);
        if ($target === null) {
            return new JsonResponse(['error' => 'Target stack not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var \App\Entity\Dish $dish */
        $dish = $this->dishRepository->find((int) $dishId);
        if ($dish === null) {
            return new JsonResponse(['error' => 'Dish not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $placement = $this->stackService->addDish($dish, $target);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\App\Exception\CollisionException) {
            return new JsonResponse(['error' => 'Collision detected'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(
            [
                'id' => $placement->getId(),
                'count' => $placement->getCount(),
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/{stackId}', name: 'api_stacks_update', methods: ['PATCH'])]
    public function update(int $stackId, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['shelfId'], $payload['x'], $payload['y'])) {
            return new JsonResponse(['error' => 'Missing shelfId, x, or y'], Response::HTTP_BAD_REQUEST);
        }

        $shelfId = $payload['shelfId'];
        if (!is_int($shelfId) && !ctype_digit((string) $shelfId)) {
            return new JsonResponse(['error' => 'shelfId must be an integer'], Response::HTTP_BAD_REQUEST);
        }

        $x = $payload['x'];
        $y = $payload['y'];
        if ((!is_int($x) && !ctype_digit((string) $x)) || (!is_int($y) && !ctype_digit((string) $y))) {
            return new JsonResponse(['error' => 'x and y must be integers'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\Shelf|null $shelf */
        $shelf = $this->shelfRepository->find((int) $shelfId);
        if ($shelf === null) {
            return new JsonResponse(['error' => 'Shelf not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var \App\Entity\Stack|null $stack */
        $stack = $this->stackRepository->find($stackId);
        if ($stack === null) {
            return new JsonResponse(['error' => 'Stack not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $stack = $this->stackService->move((int) $x, (int) $y, $shelf, $stack);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\App\Exception\CollisionException) {
            return new JsonResponse(['error' => 'Collision detected'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(
            [
                'stackId' => $stackId,
                'id' => $stack->getId(),
                'shelfId' => $stack->getShelf()->getId(),
                'x' => $stack->getX(),
                'y' => $stack->getY(),
            ],
            Response::HTTP_OK
        );
    }

    #[Route('/{stackId}', name: 'api_stacks_delete', methods: ['DELETE'])]
    public function delete(int $stackId): JsonResponse
    {
        /** @var \App\Entity\Stack|null $stack */
        $stack = $this->stackRepository->find($stackId);
        if ($stack === null) {
            return new JsonResponse(['error' => 'Stack not found'], Response::HTTP_NOT_FOUND);
        }

        $this->stackRepository->remove($stack);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
