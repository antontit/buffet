<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\PlacementRepository;
use App\Repository\ShelfRepository;
use App\Service\StackService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stacks')]
final class StackController
{
    public function __construct(
        private readonly PlacementRepository $placementRepository,
        private readonly ShelfRepository $shelfRepository,
        private readonly StackService $stackService
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

        if (!isset($payload['sourcePlacementId'], $payload['targetPlacementId'])) {
            return new JsonResponse(['error' => 'Missing placement ids'], Response::HTTP_BAD_REQUEST);
        }

        $sourceId = $payload['sourcePlacementId'];
        $targetId = $payload['targetPlacementId'];
        if ((!is_int($sourceId) && !ctype_digit((string) $sourceId))
            || (!is_int($targetId) && !ctype_digit((string) $targetId))
        ) {
            return new JsonResponse(['error' => 'Placement ids must be integers'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\Placement|null $source */
        $source = $this->placementRepository->find((int) $sourceId);
        /** @var \App\Entity\Placement|null $target */
        $target = $this->placementRepository->find((int) $targetId);
        if ($source === null || $target === null) {
            return new JsonResponse(['error' => 'Placement not found'], Response::HTTP_NOT_FOUND);
        }

        $position = $payload['position'] ?? null;
        if ($position !== null
            && !is_int($position)
            && (!is_string($position) || !in_array(strtolower($position), ['top', 'bottom'], true))
        ) {
            return new JsonResponse(['error' => 'position must be top, bottom, or integer'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $items = $this->stackService->merge($source, $target, $position);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $payload = [
            'stackId' => $target->getStackId(),
            'placements' => array_map(
                static fn ($placement): array => [
                    'id' => $placement->getId(),
                    'stackId' => $placement->getStackId(),
                    'stackIndex' => $placement->getStackIndex(),
                    'shelfId' => $placement->getShelf()->getId(),
                    'x' => $placement->getX(),
                    'y' => $placement->getY(),
                ],
                $items
            ),
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

        if (!isset($payload['placementId'])) {
            return new JsonResponse(['error' => 'Missing placementId'], Response::HTTP_BAD_REQUEST);
        }

        $placementId = $payload['placementId'];
        if (!is_int($placementId) && !ctype_digit((string) $placementId)) {
            return new JsonResponse(['error' => 'placementId must be an integer'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\Placement|null $placement */
        $placement = $this->placementRepository->find((int) $placementId);
        if ($placement === null) {
            return new JsonResponse(['error' => 'Placement not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $placements = $this->stackService->unstack($placement);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(
            [
                'placements' => array_map(
                    static fn (\App\Entity\Placement $placement): array => [
                        'id' => $placement->getId(),
                        'stackId' => $placement->getStackId(),
                        'stackIndex' => $placement->getStackIndex(),
                    ],
                    $placements
                ),
            ],
            Response::HTTP_OK
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

        try {
            $items = $this->stackService->moveStack($stackId, $shelf, (int) $x, (int) $y);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            [
                'stackId' => $stackId,
                'placements' => array_map(
                    static fn ($item): array => [
                        'id' => $item->getId(),
                        'shelfId' => $item->getShelf()->getId(),
                        'x' => $item->getX(),
                        'y' => $item->getY(),
                        'stackIndex' => $item->getStackIndex(),
                    ],
                    $items
                ),
            ],
            Response::HTTP_OK
        );
    }
}
