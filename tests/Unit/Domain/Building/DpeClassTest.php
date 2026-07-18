<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\DpeClass;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\WallInsulation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DpeClassTest extends TestCase
{
    /**
     * @param non-empty-string $_label
     */
    #[DataProvider('energyProvider')]
    public function testClassifiesByEnergyIntensity(string $_label, float $kwhEp, DpeClass $expected): void
    {
        self::assertSame($expected, DpeClass::fromEnergyIntensity($kwhEp));
    }

    /**
     * @return iterable<array{string, float, DpeClass}>
     */
    public static function energyProvider(): iterable
    {
        yield ['A at the boundary', 70.0, DpeClass::A];
        yield ['just into B', 71.0, DpeClass::B];
        yield ['C', 180.0, DpeClass::C];
        yield ['E', 330.0, DpeClass::E];
        yield ['F (a passoire)', 420.0, DpeClass::F];
        yield ['G past the last threshold', 421.0, DpeClass::G];
    }

    /**
     * @param non-empty-string $_label
     */
    #[DataProvider('climateProvider')]
    public function testClassifiesByClimateIntensity(string $_label, float $kgCo2, DpeClass $expected): void
    {
        self::assertSame($expected, DpeClass::fromClimateIntensity($kgCo2));
    }

    /**
     * @return iterable<array{string, float, DpeClass}>
     */
    public static function climateProvider(): iterable
    {
        yield ['A (low-carbon)', 6.0, DpeClass::A];
        yield ['B', 11.0, DpeClass::B];
        yield ['C', 30.0, DpeClass::C];
        yield ['F', 100.0, DpeClass::F];
        yield ['G (fuel oil)', 101.0, DpeClass::G];
    }

    public function testFinalClassIsTheWorseOfTheTwoLabels(): void
    {
        // A heat pump: great climate (B), mediocre energy (E) → final E.
        self::assertSame(DpeClass::E, DpeClass::worstOf(DpeClass::E, DpeClass::B));
        self::assertSame(DpeClass::G, DpeClass::worstOf(DpeClass::G, DpeClass::A));
        self::assertSame(DpeClass::C, DpeClass::worstOf(DpeClass::C, DpeClass::C));
    }

    public function testFillPctPositionsTheCursorInsideTheBand(): void
    {
        // 398 kWhEP sits high in the F band [330, 420] — close to tipping into G.
        self::assertEqualsWithDelta(75.6, DpeClass::fillPct(398.0, DpeClass::F->energyBand()), 0.5);
        // Bottom and top of a band clamp to 0 and 100.
        self::assertSame(0.0, DpeClass::fillPct(330.0, DpeClass::F->energyBand()));
        self::assertSame(100.0, DpeClass::fillPct(420.0, DpeClass::F->energyBand()));
    }

    public function testEveryEnumHasANonEmptyLabel(): void
    {
        foreach (DpeClass::cases() as $class) {
            self::assertNotSame('', $class->label());
        }
        foreach (WallInsulation::cases() as $walls) {
            self::assertNotSame('', $walls->label());
        }
        foreach (Glazing::cases() as $glazing) {
            self::assertNotSame('', $glazing->label());
        }
        foreach (HeatingSystem::cases() as $system) {
            self::assertNotSame('', $system->label());
        }
    }
}
