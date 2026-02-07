<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/buffet.html.twig');
    }

    #[Route('/admin/dishes', name: 'admin_dishes')]
    public function dishes(): Response
    {
        return $this->render('admin/dishes.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Buffet');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Buffet', 'fa fa-cutlery');
        yield MenuItem::linkToRoute('Dishes', 'fa fa-plate-wheat', 'admin_dishes');
    }
}
