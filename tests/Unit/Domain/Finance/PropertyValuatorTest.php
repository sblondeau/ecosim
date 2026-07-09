<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Building\DpeClass;
use App\Domain\Finance\PropertyValuator;
use PHPUnit\Framework\TestCase;

final class PropertyValuatorTest extends TestCase
{
    public function testTheGPassoireIsWorthItsPurchasePrice(): void
    {
        // Bought as a G: the passoire discount is already in the price.
        self::assertSame(200000_00, new PropertyValuator()->valueFor(DpeClass::G)->cents);
    }

    public function testEachClassGainedAddsTheSourcedStep(): void
    {
        $valuator = new PropertyValuator();

        // +8 %/classe (Notaires de France, maison) : E = G + 2 classes = +16 %.
        self::assertSame(232000_00, $valuator->valueFor(DpeClass::E)->cents);
        // B = G + 5 classes = +40 % — the renovated home.
        self::assertSame(280000_00, $valuator->valueFor(DpeClass::B)->cents);
    }

    public function testValueGrowsMonotonicallyWithTheClass(): void
    {
        $valuator = new PropertyValuator();

        $previous = $valuator->valueFor(DpeClass::G)->cents;
        foreach ([DpeClass::F, DpeClass::E, DpeClass::D, DpeClass::C, DpeClass::B, DpeClass::A] as $class) {
            $current = $valuator->valueFor($class)->cents;
            self::assertGreaterThan($previous, $current);
            $previous = $current;
        }
    }
}
