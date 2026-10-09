<?php

namespace App\Service;

use App\Controller\Admin\DashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

final class AdminLinkFactory
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly string $defaultUri,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public function to(string $controller, string $action = Action::INDEX, ?int $entityId = null, array $parameters = []): string
    {
        $url = $this->adminUrlGenerator
            ->unsetAll()
            ->setDashboard(DashboardController::class)
            ->setController($controller)
            ->setAction($action);

        if ($entityId !== null) {
            $url = $url->setEntityId($entityId);
        }

        foreach ($parameters as $name => $value) {
            $url = $url->set($name, $value);
        }

        return $url->generateUrl();
    }

    public function qualify(string $url): string
    {
        if ($url === '' || str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $base = rtrim($this->defaultUri, '/');
        if ($base === '') {
            return $url;
        }

        return $base.'/'.ltrim($url, '/');
    }

    /** @param array<string, mixed> $parameters */
    public function anchor(string $controller, string $label, string $action = Action::INDEX, ?int $entityId = null, array $parameters = []): string
    {
        return sprintf(
            '<a href="%s">%s</a>',
            htmlspecialchars($this->to($controller, $action, $entityId, $parameters), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }
}
