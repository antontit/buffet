<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\PlacementRepository;
use App\Repository\ShelfRepository;
use App\Service\PlacementService;
use App\Exception\CollisionException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/placements')]
final class PlacementController
{
    public function __construct(
        private readonly PlacementRepository $placementRepository,
        private readonly ShelfRepository $shelfRepository,
        private readonly PlacementService $placementService
    ) {
    }

    #[Route('/{id}', name: 'api_placements_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        if (!array_key_exists('x', $payload) || !array_key_exists('y', $payload)) {
            return new JsonResponse(['error' => 'Missing x or y'], Response::HTTP_BAD_REQUEST);
        }

        $x = $payload['x'];
        $y = $payload['y'];
        if ((!is_int($x) && !ctype_digit((string) $x)) || (!is_int($y) && !ctype_digit((string) $y))) {
            return new JsonResponse(['error' => 'x and y must be integers'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\Placement $placement */
        $placement = $this->placementRepository->find($id);
        if ($placement === null) {
            return new JsonResponse(['error' => 'Placement not found'], Response::HTTP_NOT_FOUND);
        }

        $shelf = null;
        if (array_key_exists('shelfId', $payload)) {
            $shelfId = $payload['shelfId'];
            if (!is_int($shelfId) && !ctype_digit((string)$shelfId)) {
                return new JsonResponse(['error' => 'shelfId must be an integer'], Response::HTTP_BAD_REQUEST);
            }

            $shelf = $this->shelfRepository->find((int)$shelfId);
            if ($shelf === null) {
                return new JsonResponse(['error' => 'Shelf not found'], Response::HTTP_NOT_FOUND);
            }
        }

        try {
            $updated = $this->placementService->updatePosition(
                $placement,
                (int)$x,
                (int)$y,
                $shelf
            );
        } catch (CollisionException) {
            return new JsonResponse(['error' => 'Collision detected'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(
            [
                'id' => $updated->getId(),
                'x' => $updated->getX(),
                'y' => $updated->getY(),
                'shelfId' => $updated->getShelf()->getId(),
            ],
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'api_placements_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var \App\Entity\Placement $placement */
        $placement = $this->placementRepository->find($id);
        if ($placement === null) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $this->placementRepository->remove($placement, true);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
