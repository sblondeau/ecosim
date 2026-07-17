<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationAdvisor;
use PHPUnit\Framework\TestCase;

final class RenovationAdvisorTest extends TestCase
{
    private RenovationAdvisor $advisor;

    protected function setUp(): void
    {
        $this->advisor = new RenovationAdvisor();
    }

    private function house(EnvelopeState $envelope, HeatingSystem $heating = HeatingSystem::FuelOilBoiler, bool $broken = false): Household
    {
        return new Household(0.0, 0.0, $envelope, $heating, $broken);
    }

    private function bare(): EnvelopeState
    {
        return new EnvelopeState(false, WallInsulation::None, Glazing::Single);
    }

    public function testRoofInsulationIsAlwaysAneutralRepere(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::RoofInsulation, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('gain/prix', $advice->message);
    }

    public function testHeatPumpCautionedInAPoorlyInsulatedHouse(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::HeatPump, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Caution, $advice->level);
        self::assertStringContainsString('surdimensionnée', $advice->message);
    }

    public function testHeatPumpIsInfoOnceInsulated(): void
    {
        $insulated = new EnvelopeState(true, WallInsulation::Interior, Glazing::Single); // f = 0.60
        $advice = $this->advisor->adviceFor(Renovation::HeatPump, $this->house($insulated));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
    }

    public function testHeatPumpDuringBreakdownIsInfoNotCaution(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::HeatPump, $this->house($this->bare(), broken: true));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('fioul', $advice->message);
    }

    public function testGlazingCautionedWhileEnvelopeUntreated(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::Glazing, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Caution, $advice->level);
    }

    public function testGlazingIsInfoOnceEnvelopeTreated(): void
    {
        $insulated = new EnvelopeState(true, WallInsulation::Interior, Glazing::Single); // f = 0.60
        $advice = $this->advisor->adviceFor(Renovation::Glazing, $this->house($insulated));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
    }

    public function testWallOptionsDescribeTheirTradeoff(): void
    {
        $iti = $this->advisor->adviceFor(Renovation::WallInsulationInterior, $this->house($this->bare()));
        $ite = $this->advisor->adviceFor(Renovation::WallInsulationExterior, $this->house($this->bare()));

        self::assertNotNull($iti);
        self::assertNotNull($ite);
        self::assertStringContainsString('surface habitable', $iti->message);
        self::assertStringContainsString('façade', $ite->message);
    }

    public function testLowTempEmittersHighlightTheScopGainWithAHeatPump(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::LowTempEmitters, $this->house($this->bare(), HeatingSystem::HeatPump));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('SCOP', $advice->message);
    }

    public function testLowTempEmittersAreOfLimitedUseWithoutAHeatPump(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::LowTempEmitters, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('pompe à chaleur', $advice->message);
    }

    public function testPelletBoilerAdvisesLowCarbonButManualHandling(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::PelletBoiler, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('silo', $advice->message);
    }
}
