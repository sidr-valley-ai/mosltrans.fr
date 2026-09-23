<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly Packages $assets)
    {
    }

    public function index(): Response
    {
        return $this->redirectToRoute('admin_lead_index');
    }

    public function configureDashboard(): Dashboard
    {
        $logoUrl = $this->assets->getUrl('images/logo-blanc.png');

        return Dashboard::new()
          ->setTitle(sprintf(
              '<img src="%s" alt="" class="brand-logo"><span class="sr-only">MOSLTRANS</span>',
              $logoUrl
          ));
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkTo(LeadCrudController::class, 'Demandes de contact', 'fas fa-users');
    }
}
