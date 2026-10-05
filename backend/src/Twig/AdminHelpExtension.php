<?php

namespace App\Twig;

use App\Admin\Help\AdminHelp;
use App\Admin\Help\AdminHelpRegistry;
use App\Controller\Admin\DashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminHelpExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminHelpRegistry $helps,
        private readonly AdminContextProvider $adminContextProvider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_page_help', $this->currentHelp(...)),
        ];
    }

    public function currentHelp(): ?AdminHelp
    {
        $context = $this->adminContextProvider->getContext();
        if ($context === null) {
            return null;
        }

        $crud = $context->getCrud();
        if ($crud === null) {
            return $this->helps->getHelpFor(DashboardController::class, 'index');
        }

        $controller = $crud->getControllerFqcn();
        if ($controller === null) {
            return null;
        }

        return $this->helps->getHelpFor($controller, $crud->getCurrentAction());
    }
}
