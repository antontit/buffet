<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/shelves')]
final class ShelfController
{
    #[Route('/{id}', name: 'api_shelves_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Not implemented', 'id' => $id],
            Response::HTTP_NOT_IMPLEMENTED
        );
    }
}
