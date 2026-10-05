<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Crud\ContactRequestCrudController;
use App\Controller\Admin\Crud\HeroSlideCrudController;
use App\Controller\Admin\Crud\JobOfferCrudController;
use App\Controller\Admin\Crud\MediaCrudController;
use App\Controller\Admin\Crud\NewsCrudController;
use App\Controller\Admin\Crud\PageCrudController;
use App\Controller\Admin\Crud\PartnerCrudController;
use App\Controller\Admin\Crud\ProductCategoryCrudController;
use App\Controller\Admin\Crud\ProductCrudController;
use App\Controller\Admin\Crud\SectionCrudController;
use App\Controller\Admin\Crud\SiteSettingsCrudController;
use App\Controller\Admin\Crud\StatisticCrudController;
use App\Controller\Admin\Crud\UserCrudController;
use App\Repository\ContactRequestRepository;
use App\Service\AdminLinkFactory;
use App\Service\DashboardOverview;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/administration', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly ContactRequestRepository $contactRequestRepository,
        private readonly DashboardOverview $dashboardOverview,
        private readonly AdminLinkFactory $adminLinks,
    ) {
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('admin/dashboard.css')
            ->addJsFile('admin/palette.js')
            ->addWebpackEncoreEntry('admin');
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'overview' => $this->dashboardOverview->build(),
            'links' => [
                'pages' => $this->adminLinks->to(PageCrudController::class),
                'newProduct' => $this->adminLinks->to(ProductCrudController::class, Action::NEW),
                'newNews' => $this->adminLinks->to(NewsCrudController::class, Action::NEW),
                'newJob' => $this->adminLinks->to(JobOfferCrudController::class, Action::NEW),
                'newMedia' => $this->adminLinks->to(MediaCrudController::class, Action::NEW),
                'messages' => $this->adminLinks->to(ContactRequestCrudController::class),
                'settings' => $this->adminLinks->to(SiteSettingsCrudController::class),
                'products' => $this->adminLinks->to(ProductCrudController::class),
                'news' => $this->adminLinks->to(NewsCrudController::class),
                'jobs' => $this->adminLinks->to(JobOfferCrudController::class),
                'partners' => $this->adminLinks->to(PartnerCrudController::class),
                'site' => $this->generateUrl('home'),
            ],
        ]);
    }

    public function configureCrud(): Crud
    {
        return parent::configureCrud()
            ->overrideTemplate('layout', 'admin/layout.html.twig')
            ->setFormThemes([
                '@EasyAdmin/crud/form_theme.html.twig',
                '@VichUploader/Form/fields.html.twig',
                'admin/form/field_help_tooltip.html.twig',
            ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Diaré Groupe Industrie')
            ->disableDarkMode();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-house');

        yield MenuItem::section('Site internet');
        yield MenuItem::linkTo(PageCrudController::class, 'Pages du site', 'fa fa-file-lines');
        yield MenuItem::linkTo(SectionCrudController::class, 'Contenus des pages', 'fa fa-layer-group');
        yield MenuItem::linkTo(HeroSlideCrudController::class, 'Bannières d’accueil', 'fa fa-images');
        yield MenuItem::linkTo(MediaCrudController::class, 'Bibliothèque d’images', 'fa fa-image');
        yield MenuItem::linkTo(SiteSettingsCrudController::class, 'Coordonnées et réglages', 'fa fa-sliders');

        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(ProductCrudController::class, 'Produits', 'fa fa-box');
        yield MenuItem::linkTo(ProductCategoryCrudController::class, 'Catégories de produits', 'fa fa-tags');
        yield MenuItem::linkTo(StatisticCrudController::class, 'Chiffres clés', 'fa fa-chart-simple');

        yield MenuItem::section('Communication');
        yield MenuItem::linkTo(NewsCrudController::class, 'Actualités', 'fa fa-newspaper');
        yield MenuItem::linkTo(JobOfferCrudController::class, 'Offres d’emploi', 'fa fa-briefcase');
        yield MenuItem::linkTo(PartnerCrudController::class, 'Partenaires', 'fa fa-handshake');

        $messages = MenuItem::linkTo(ContactRequestCrudController::class, 'Messages reçus', 'fa fa-envelope');
        $unread = $this->contactRequestRepository->countUnread();
        if ($unread > 0) {
            $messages = $messages->setBadge($unread, 'warning');
        }
        yield MenuItem::section('Messages');
        yield $messages;

        yield MenuItem::section('Administration');
        yield MenuItem::linkTo(UserCrudController::class, 'Administrateurs', 'fa fa-user-shield');
        yield MenuItem::linkToRoute('Voir le site', 'fa fa-arrow-up-right-from-square', 'home')->setLinkTarget('_blank');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-right-from-bracket');
    }
}
