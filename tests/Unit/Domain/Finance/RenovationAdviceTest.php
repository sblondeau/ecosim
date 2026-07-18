<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\RenovationAdvice;
use PHPUnit\Framework\TestCase;

final class RenovationAdviceTest extends TestCase
{
    public function testCarriesLevelAndMessage(): void
    {
        $advice = new RenovationAdvice(AdviceLevel::Caution, 'Isolez d\'abord.');

        self::assertSame(AdviceLevel::Caution, $advice->level);
        self::assertSame('Isolez d\'abord.', $advice->message);
    }

    public function testLevelsCarryAnIcon(): void
    {
        self::assertSame('💡', AdviceLevel::Info->icon());
        self::assertSame('⚠️', AdviceLevel::Caution->icon());
    }
}
