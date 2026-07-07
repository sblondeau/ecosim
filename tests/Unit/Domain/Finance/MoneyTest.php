<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Finance\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testStoresExactCents(): void
    {
        self::assertSame(1999, Money::fromEuros(19.99)->cents);
        self::assertSame(1, Money::fromEuros(0.005)->cents, 'Half a cent rounds up.');
        self::assertSame(0, Money::zero()->cents);
    }

    public function testArithmeticIsExact(): void
    {
        $a = Money::fromEuros(0.10);
        $b = Money::fromEuros(0.20);

        // The classic float trap 0.1 + 0.2 !== 0.3 cannot happen in cents.
        self::assertSame(30, $a->plus($b)->cents);
        self::assertSame(-10, $a->minus($b)->cents);
        self::assertTrue($a->minus($b)->isNegative());
    }

    public function testFrenchFormat(): void
    {
        self::assertSame('1 234,56 €', Money::fromEuros(1234.56)->format());
        self::assertSame('0,00 €', Money::zero()->format());
        self::assertSame('−42,50 €', Money::fromEuros(-42.5)->format());
    }
}
