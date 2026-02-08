<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\DishRepository;
use App\Repository\StackRepository;
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
        private readonly StackRepository $stackRepository
    ) {
    }

    public function index(): Response
    {
        $shelves = $this->shelfRepository->findAllOrderedByIdAsc();
        $dishes = $this->dishRepository->findAllOrderedByIdAsc();

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

        return $this->render('admin/buffet.html.twig', [
            'shelves' => $shelves,
            'dishes' => $dishes,
            'placementsByShelf' => $placementsByShelf,
            'stackGroupsByShelf' => $stackGroupsByShelf,
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
