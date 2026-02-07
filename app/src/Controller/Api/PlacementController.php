<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/placements')]
final class PlacementController
{
    #[Route('/{id}', name: 'api_placements_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Not implemented', 'id' => $id],
            Response::HTTP_NOT_IMPLEMENTED
        );
    }
}
