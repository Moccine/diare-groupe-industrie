<?php

namespace App\Tests;

use App\Entity\Statistic;
use PHPUnit\Framework\TestCase;

final class StatisticTest extends TestCase
{
    public function testPlaceholderIsNotAnimated(): void
    {
        $statistic = (new Statistic())->setValue('À renseigner')->setLabel('Années d’expérience');

        self::assertFalse($statistic->isNumeric());
    }

    public function testWholeNumberIsNumeric(): void
    {
        $statistic = (new Statistic())->setValue('12')->setLabel('Sites');

        self::assertTrue($statistic->isNumeric());
    }

    public function testPrefixedNumberIsNumeric(): void
    {
        $statistic = (new Statistic())->setValue('+20')->setLabel('Années');

        self::assertTrue($statistic->isNumeric());
    }
}
