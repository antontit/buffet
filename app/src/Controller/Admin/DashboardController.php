<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\DishRepository;
use App\Service\BuffetLayoutBuilder;
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
        private readonly BuffetLayoutBuilder $buffetLayoutBuilder
    ) {
    }

    public function index(): Response
    {
        $shelves = $this->shelfRepository->findAllOrderedByIdAsc();
        $dishes = $this->dishRepository->findAllOrderedByIdAsc();

        $layout = $this->buffetLayoutBuilder->build();

        return $this->render('admin/buffet.html.twig', [
            'shelves' => $shelves,
            'dishes' => $dishes,
            'placementsByShelf' => $layout['placementsByShelf'],
            'stackGroupsByShelf' => $layout['stackGroupsByShelf'],
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
