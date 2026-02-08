<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ShelfRepository;
use App\Service\BuffetLayoutBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/buffet', name: 'buffet_public', methods: ['GET'])]
final class BuffetController extends AbstractController
{
    public function __construct(
        private readonly ShelfRepository $shelfRepository,
        private readonly BuffetLayoutBuilder $buffetLayoutBuilder
    ) {
    }

    public function __invoke(): Response
    {
        $shelves = $this->shelfRepository->findAllOrderedByIdAsc();
        $layout = $this->buffetLayoutBuilder->build();

        return $this->render('buffet/index.html.twig', [
            'shelves' => $shelves,
            'placementsByShelf' => $layout['placementsByShelf'],
            'stackGroupsByShelf' => $layout['stackGroupsByShelf'],
        ]);
    }
}
