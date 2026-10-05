<?php

namespace App\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

final class PublicSiteAction
{
    public static function add(Actions $actions, callable $urlFor): Actions
    {
        foreach ([Crud::PAGE_INDEX, Crud::PAGE_DETAIL, Crud::PAGE_EDIT] as $page) {
            $actions->add($page, self::create($urlFor));
        }

        return $actions;
    }

    private static function create(callable $urlFor): Action
    {
        return Action::new('viewOnSite', 'Voir sur le site', 'fa fa-arrow-up-right-from-square')
            ->linkToUrl(static function (object $entity) use ($urlFor): string {
                $url = $urlFor($entity);

                return is_string($url) && $url !== '' ? $url : '#';
            })
            ->displayIf(static function (object $entity) use ($urlFor): bool {
                $url = $urlFor($entity);

                return is_string($url) && $url !== '';
            })
            ->setHtmlAttributes([
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ]);
    }
}
