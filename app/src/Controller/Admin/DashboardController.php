<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\DishRepository;
use App\Repository\PlacementRepository;
use App\Repository\ShelfRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly ShelfRepository $shelfRepository,
        private readonly DishRepository $dishRepository,
        private readonly PlacementRepository $placementRepository
    ) {
    }

    public function index(): Response
    {
        $shelves = $this->shelfRepository->findBy([], ['id' => 'ASC']);
        $dishes = $this->dishRepository->findBy([], ['id' => 'ASC']);
        $placementsByShelf = [];
        $stackGroupsByShelf = [];
        $stackMeta = [];
        foreach ($this->placementRepository->findAllWithDish() as $placement) {
            $shelfId = $placement->getShelf()->getId();
            if (!isset($placementsByShelf[$shelfId])) {
                $placementsByShelf[$shelfId] = [];
            }
            $placementsByShelf[$shelfId][] = $placement;

            $stackId = $placement->getStackId();
            if ($stackId !== null) {
                if (!isset($stackMeta[$stackId])) {
                    $stackMeta[$stackId] = [
                        'count' => 0,
                        'topId' => $placement->getId(),
                        'topIndex' => $placement->getStackIndex() ?? -1,
                    ];
                }
                $stackMeta[$stackId]['count']++;
                $stackIndex = $placement->getStackIndex() ?? -1;
                if ($stackIndex >= $stackMeta[$stackId]['topIndex']) {
                    $stackMeta[$stackId]['topIndex'] = $stackIndex;
                    $stackMeta[$stackId]['topId'] = $placement->getId();
                }
            }
        }
        foreach ($placementsByShelf as $shelfId => $placements) {
            foreach ($placements as $placement) {
                $stackId = $placement->getStackId();
                if ($stackId === null) {
                    $stackGroupsByShelf[$shelfId][] = [
                        'placement' => $placement,
                        'stackId' => null,
                        'count' => 1,
                    ];
                    continue;
                }

                if (($stackMeta[$stackId]['topId'] ?? null) === $placement->getId()) {
                    $stackGroupsByShelf[$shelfId][] = [
                        'placement' => $placement,
                        'stackId' => $stackId,
                        'count' => $stackMeta[$stackId]['count'] ?? 1,
                    ];
                }
            }
        }

        return $this->render('admin/buffet.html.twig', [
            'shelves' => $shelves,
            'dishes' => $dishes,
            'placementsByShelf' => $placementsByShelf,
            'stackGroupsByShelf' => $stackGroupsByShelf,
            'stackMeta' => $stackMeta,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Buffet');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Buffet', 'fa fa-cutlery');
    }
}
