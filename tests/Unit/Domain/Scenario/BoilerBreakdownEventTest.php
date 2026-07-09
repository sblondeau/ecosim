<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Scenario;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Loan;
use App\Domain\Finance\Money;
use App\Domain\Scenario\BoilerBreakdownEvent;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\PeriodTotals;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BoilerBreakdownEventTest extends TestCase
{
    private static function config(): GameConfig
    {
        return new GameConfig(seed: 1, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: 30);
    }

    private static function stateAt(int $day, Household $household): GameState
    {
        return new GameState($day, $household, 0.0, Money::zero(), Loan::none(), new PeriodTotals());
    }

    private static function fioulHome(bool $broken = false): Household
    {
        return new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler, $broken);
    }

    public function testFiresOnlyOnItsExactDay(): void
    {
        $event = new BoilerBreakdownEvent(19);

        self::assertFalse($event->shouldFire(self::config(), self::stateAt(18, self::fioulHome())));
        self::assertTrue($event->shouldFire(self::config(), self::stateAt(19, self::fioulHome())));
        self::assertFalse($event->shouldFire(self::config(), self::stateAt(20, self::fioulHome())), 'A scene, not a wear model.');
    }

    public function testDoesNotFireOnceTheBoilerIsGoneOrAlreadyBroken(): void
    {
        $event = new BoilerBreakdownEvent(19);
        $heatPumpHome = new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::HeatPump);

        self::assertFalse($event->shouldFire(self::config(), self::stateAt(19, $heatPumpHome)), 'Anticipating the switch avoids the event.');
        self::assertFalse($event->shouldFire(self::config(), self::stateAt(19, self::fioulHome(broken: true))));
    }

    public function testFiringBreaksTheBoilerAndTouchesNothingElse(): void
    {
        $state = self::stateAt(19, self::fioulHome());

        $after = new BoilerBreakdownEvent(19)->fire($state);

        self::assertTrue($after->household->boilerBroken);
        self::assertSame($state->currentDay, $after->currentDay);
        self::assertSame($state->savings->cents, $after->savings->cents, 'Breaking down is free — deciding what next is the game.');
    }
}
