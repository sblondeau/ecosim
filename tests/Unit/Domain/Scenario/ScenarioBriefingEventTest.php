<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Scenario;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\Loan;
use App\Domain\Finance\Money;
use App\Domain\Scenario\ScenarioBriefingEvent;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\PeriodTotals;
use PHPUnit\Framework\TestCase;

final class ScenarioBriefingEventTest extends TestCase
{
    public function testIsIdentifiedByItsOwnPartialName(): void
    {
        self::assertSame('briefing', new ScenarioBriefingEvent()->id());
    }

    public function testAlwaysHasOccurredSinceItIsRelevantFromTheFirstRender(): void
    {
        $household = new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler);
        $event = new ScenarioBriefingEvent();

        self::assertTrue($event->hasOccurred(new GameState(0, $household, 0.0, Money::zero(), Loan::none(), new PeriodTotals())));
        self::assertTrue($event->hasOccurred(new GameState(50, $household, 0.0, Money::zero(), Loan::none(), new PeriodTotals())), 'Only acknowledgement, not the day, retires it.');
    }

    public function testRestartsTheClockOnAcknowledgeSinceItIsTheLastModalBeforePlay(): void
    {
        self::assertTrue(new ScenarioBriefingEvent()->restartsClockOnAcknowledge());
    }
}
