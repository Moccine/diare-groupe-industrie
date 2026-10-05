<?php

namespace App\Tests;

use App\Admin\Help\AdminHelp;
use App\Admin\Help\AdminHelpRegistry;
use App\Admin\Help\AdminHelpSection;
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
use App\Controller\Admin\DashboardController;
use PHPUnit\Framework\TestCase;

final class AdminHelpRegistryTest extends TestCase
{
    public function testEveryMainScreenHasStructuredHelp(): void
    {
        $registry = new AdminHelpRegistry();
        $screens = [
            DashboardController::class => 'Tableau de bord',
            PageCrudController::class => 'Pages du site',
            SectionCrudController::class => 'Contenus des pages',
            HeroSlideCrudController::class => 'Bannières d’accueil',
            MediaCrudController::class => 'Bibliothèque d’images',
            SiteSettingsCrudController::class => 'Coordonnées et réglages',
            ProductCrudController::class => 'Gestion des produits',
            ProductCategoryCrudController::class => 'Catégories de produits',
            StatisticCrudController::class => 'Chiffres clés',
            NewsCrudController::class => 'Actualités',
            JobOfferCrudController::class => 'Offres d’emploi',
            PartnerCrudController::class => 'Partenaires',
            ContactRequestCrudController::class => 'Messages reçus',
            UserCrudController::class => 'Administrateurs',
        ];

        foreach ($screens as $class => $title) {
            $help = $registry->getHelpFor($class, 'edit');
            self::assertNotNull($help, $class);
            self::assertSame($title, $help->title);
            self::assertGreaterThanOrEqual(3, count($help->sections));
            $markup = $help->markup();
            self::assertStringContainsString('À quoi sert cet écran ?', $markup);
            self::assertStringContainsString('dgi-help-ico', $markup);
            self::assertStringContainsString('fa fa-', $markup);
            self::assertStringNotContainsString('<script', $markup);
            self::assertStringNotContainsString('border-top', $markup);

            foreach ($help->sections as $section) {
                self::assertMatchesRegularExpression('/^fa-[a-z0-9-]+$/', $section->icon);
                self::assertNotSame('', $section->tone);
            }
        }
    }

    public function testComplexScreensIncludeAConcreteExample(): void
    {
        $registry = new AdminHelpRegistry();
        $examples = [
            PageCrudController::class => 'nos-produits',
            SectionCrudController::class => 'feuille vide',
            HeroSlideCrudController::class => 'Découvrir nos produits',
            MediaCrudController::class => 'Usine Conakry',
            SiteSettingsCrudController::class => 'Produits laitiers',
            ProductCrudController::class => 'Lait entier 1 L',
            ProductCategoryCrudController::class => 'Produits laitiers',
            StatisticCrudController::class => 'Années d’expérience',
            JobOfferCrudController::class => 'https://exemple.com/candidature',
        ];

        foreach ($examples as $class => $needle) {
            $help = $registry->getHelpFor($class, 'index');
            self::assertNotNull($help, $class);
            $markup = $help->markup();
            self::assertStringContainsString('Exemple', $markup);
            self::assertStringContainsString($needle, $markup);
            self::assertStringContainsString('fa-lightbulb', $markup);
        }
    }

    public function testHelpMarkupEscapesTextAndRejectsUnsafeIcons(): void
    {
        $help = new AdminHelp('Titre', [
            new AdminHelpSection(
                heading: 'Exemple <i>',
                paragraphs: ['<script>alert(1)</script>'],
                items: ['<img alt="x">'],
                icon: 'fa-lightbulb"><script>',
                tone: 'example" style="x',
            ),
        ]);

        $markup = $help->markup();

        self::assertStringContainsString('dgi-help-block--info', $markup);
        self::assertStringContainsString('fa fa-circle-info', $markup);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $markup);
        self::assertStringContainsString('&lt;img alt=&quot;x&quot;&gt;', $markup);
        self::assertStringNotContainsString('<script>', $markup);
        self::assertStringNotContainsString('<img', $markup);
    }

    public function testUnknownScreenHasNoHelp(): void
    {
        self::assertNull((new AdminHelpRegistry())->getHelpFor(self::class, 'index'));
    }
}
