<?php

namespace App\Tests\Service;

use App\Service\AdminLinkFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

final class AdminLinkFactoryTest extends TestCase
{
    public function testQualifyTurnsARelativeAdminPathIntoAnAbsoluteUrl(): void
    {
        $factory = new AdminLinkFactory($this->createMock(AdminUrlGeneratorInterface::class), 'https://diare.example/');

        self::assertSame(
            'https://diare.example/administration?crudAction=detail&entityId=4',
            $factory->qualify('/administration?crudAction=detail&entityId=4'),
        );
    }

    public function testQualifyLeavesAnAbsoluteUrlUntouched(): void
    {
        $factory = new AdminLinkFactory($this->createMock(AdminUrlGeneratorInterface::class), 'https://diare.example');

        self::assertSame('https://already.example/administration', $factory->qualify('https://already.example/administration'));
        self::assertSame('', $factory->qualify(''));
    }
}
