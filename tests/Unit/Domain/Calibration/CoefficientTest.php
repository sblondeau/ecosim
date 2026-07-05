<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Calibration;

use App\Domain\Calibration\Coefficient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CoefficientTest extends TestCase
{
    public function testExposesItsValueAndProvenance(): void
    {
        $c = new Coefficient(12.5, '°C', 11.0, 13.5, 'Météo-France', '2025-01-01', 'annual mean');

        self::assertSame(12.5, $c->value);
        self::assertSame('°C', $c->unit);
        self::assertSame('Météo-France', $c->source);
        self::assertSame('2025-01-01', $c->reviewedOn);
        self::assertSame('annual mean', $c->note);
    }

    public function testRejectsInvertedRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Coefficient(5.0, 'x', 10.0, 1.0, 'src', '2025-01-01');
    }

    public function testRejectsValueOutsideRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Coefficient(20.0, 'x', 0.0, 10.0, 'src', '2025-01-01');
    }

    public function testValueOnRangeBoundaryIsAccepted(): void
    {
        $low = new Coefficient(0.0, 'x', 0.0, 10.0, 'src', '2025-01-01');
        $high = new Coefficient(10.0, 'x', 0.0, 10.0, 'src', '2025-01-01');

        self::assertSame(0.0, $low->value);
        self::assertSame(10.0, $high->value);
    }
}
