<?php

declare(strict_types=1);

namespace App\Controller\Admin;

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
        private readonly ShelfRepository $shelfRepository
    ) {
    }

    public function index(): Response
    {
        $shelves = $this->shelfRepository->findBy([], ['id' => 'ASC']);

        return $this->render('admin/buffet.html.twig', [
            'shelves' => $shelves,
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
