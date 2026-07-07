<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\DpeClass;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\InsulationLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DpeClassTest extends TestCase
{
    #[DataProvider('buildingProvider')]
    public function testClassifiesTheBuilding(InsulationLevel $insulation, HeatingSystem $heating, DpeClass $expected): void
    {
        self::assertSame($expected, DpeClass::fromBuilding($insulation, $heating));
    }

    /**
     * @return iterable<string, array{InsulationLevel, HeatingSystem, DpeClass}>
     */
    public static function buildingProvider(): iterable
    {
        yield 'starting passoire (none + fioul) is G' => [InsulationLevel::None, HeatingSystem::FuelOilBoiler, DpeClass::G];
        yield 'heat pump alone improves to E' => [InsulationLevel::None, HeatingSystem::HeatPump, DpeClass::E];
        yield 'insulation alone improves to E' => [InsulationLevel::Retrofitted, HeatingSystem::FuelOilBoiler, DpeClass::E];
        yield 'retrofitted + heat pump reaches C' => [InsulationLevel::Retrofitted, HeatingSystem::HeatPump, DpeClass::C];
        yield 'reinforced + fioul stays D (fossil ceiling)' => [InsulationLevel::Reinforced, HeatingSystem::FuelOilBoiler, DpeClass::D];
        yield 'full renovation reaches B' => [InsulationLevel::Reinforced, HeatingSystem::HeatPump, DpeClass::B];
    }

    public function testEveryEnumHasANonEmptyLabel(): void
    {
        foreach (DpeClass::cases() as $class) {
            self::assertNotSame('', $class->label());
        }
        foreach (InsulationLevel::cases() as $level) {
            self::assertNotSame('', $level->label());
        }
        foreach (HeatingSystem::cases() as $system) {
            self::assertNotSame('', $system->label());
        }
    }
}
