<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stacks')]
final class StackController
{
    #[Route('/merge', name: 'api_stacks_merge', methods: ['POST'])]
    public function merge(Request $request): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Not implemented'],
            Response::HTTP_NOT_IMPLEMENTED
        );
    }

    #[Route('/unstack', name: 'api_stacks_unstack', methods: ['POST'])]
    public function unstack(Request $request): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Not implemented'],
            Response::HTTP_NOT_IMPLEMENTED
        );
    }

    #[Route('/{stackId}', name: 'api_stacks_update', methods: ['PATCH'])]
    public function update(int $stackId, Request $request): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Not implemented', 'stackId' => $stackId],
            Response::HTTP_NOT_IMPLEMENTED
        );
    }
}
