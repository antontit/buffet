<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ShelfRepository;
use App\Repository\StackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/buffet', name: 'buffet_public', methods: ['GET'])]
final class BuffetController extends AbstractController
{
    public function __construct(
        private readonly ShelfRepository $shelfRepository,
        private readonly StackRepository $stackRepository
    ) {
    }

    public function __invoke(): Response
    {
        $shelves = $this->shelfRepository->findAllOrderedByIdAsc();
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

        return $this->render('buffet/index.html.twig', [
            'shelves' => $shelves,
            'placementsByShelf' => $placementsByShelf,
            'stackGroupsByShelf' => $stackGroupsByShelf,
        ]);
    }
}
