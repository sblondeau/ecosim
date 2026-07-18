<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Game;
use App\Application\SessionGameStore;
use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\Ventilation;
use App\Domain\Building\WallInsulation;
use App\Domain\Building\WaterHeater;
use App\Domain\Finance\Money;
use App\Domain\Simulation\DailySnapshot;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\SimulationEngine;
use App\Domain\Time\TickSpeed;
use App\Domain\Time\TimeProgression;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SessionGameStoreTest extends TestCase
{
    private const string SESSION_KEY = 'ecosim_game';

    private Session $session;
    private SessionGameStore $store;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($this->session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->store = new SessionGameStore($requestStack);
    }

    public function testStartsAFreshGameOnTheLockedScenario(): void
    {
        $game = $this->store->current();

        self::assertSame(0, $game->state->currentDay);
        self::assertSame(0.0, $game->state->household->solarKwc, 'The primo-accédant starts with no production equipment.');
        self::assertSame(0.0, $game->state->household->batteryKwh);
        self::assertFalse($game->state->loan->isActive());
        self::assertFalse($game->state->household->envelope->roofInsulated, 'The scenario starts uninsulated.');
        self::assertSame(WallInsulation::None, $game->state->household->envelope->walls);
        self::assertSame(Glazing::Single, $game->state->household->envelope->glazing);
        self::assertSame(Ventilation::None, $game->state->household->envelope->ventilation, 'The scenario starts with natural ventilation.');
        self::assertSame(HeatingSystem::FuelOilBoiler, $game->state->household->heatingSystem, 'The scenario starts on fuel oil.');
        self::assertSame(7750_00, $game->state->savings->cents, 'Tight post-purchase savings: just below the heat pump net cost on day 1.');
        self::assertTrue($this->session->has(self::SESSION_KEY), 'The fresh game is persisted.');
    }

    public function testRoundTripsAGameThroughTheSession(): void
    {
        $config = new GameConfig(seed: 42, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: 10);
        $household = new Household(3.0, 5.0, new EnvelopeState(true, WallInsulation::Interior, Glazing::Double, Ventilation::DoubleFlow), HeatingSystem::HeatPump);
        $state = GameState::start($household, Money::fromEuros(8000.0))->advanced($this->someDay($config, $household));

        $progression = new TimeProgression(new DateTimeImmutable('@1750000000'), TickSpeed::Triple);
        $this->store->save(new Game($config, $state, $progression));
        $loaded = $this->store->current();

        self::assertSame(42, $loaded->config->seed);
        self::assertSame('2025-01-01', $loaded->config->epoch->format('Y-m-d'));
        self::assertSame(10, $loaded->config->horizonDays);
        self::assertSame(TickSpeed::Triple, $loaded->progression->speed);
        self::assertSame(1750000000, $loaded->progression->lastTickAt->getTimestamp());
        self::assertSame(1, $loaded->state->currentDay);
        self::assertSame(3.0, $loaded->state->household->solarKwc);
        self::assertSame(5.0, $loaded->state->household->batteryKwh);
        self::assertTrue($loaded->state->household->envelope->roofInsulated);
        self::assertSame(WallInsulation::Interior, $loaded->state->household->envelope->walls);
        self::assertSame(Glazing::Double, $loaded->state->household->envelope->glazing);
        self::assertSame(Ventilation::DoubleFlow, $loaded->state->household->envelope->ventilation);
        self::assertSame(HeatingSystem::HeatPump, $loaded->state->household->heatingSystem);
        self::assertSame($state->batteryLevelKwh, $loaded->state->batteryLevelKwh);
        self::assertSame($state->savings->cents, $loaded->state->savings->cents);
        self::assertSame($state->totals->fuelOilCost->cents, $loaded->state->totals->fuelOilCost->cents);
        self::assertSame($state->totals->productionKwh, $loaded->state->totals->productionKwh);
        self::assertSame($state->totals->fuelOilLitres, $loaded->state->totals->fuelOilLitres);
        self::assertSame($state->totals->days, $loaded->state->totals->days);
    }

    public function testRoundTripsTheBrokenBoilerFlag(): void
    {
        $config = new GameConfig(seed: 42, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: 10);
        $broken = new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler, boilerBroken: true);

        $progression = new TimeProgression(new DateTimeImmutable('@1750000000'), TickSpeed::Normal);
        $this->store->save(new Game($config, GameState::start($broken, Money::fromEuros(4000.0)), $progression));

        self::assertTrue($this->store->current()->state->household->boilerBroken);
    }

    public function testRoundTripsCumulativePelletTotals(): void
    {
        $config = new GameConfig(seed: 42, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: 10);
        $household = new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::PelletBoiler);
        $state = GameState::start($household, Money::fromEuros(8000.0))->advanced($this->someDay($config, $household));

        self::assertGreaterThan(0.0, $state->totals->pelletKg, 'Sanity check: the pellet boiler burns pellets on a lived day.');
        self::assertGreaterThan(0, $state->totals->pelletCost->cents, 'Sanity check: burning pellets costs money.');

        $progression = new TimeProgression(new DateTimeImmutable('@1750000000'), TickSpeed::Normal);
        $this->store->save(new Game($config, $state, $progression));
        $loaded = $this->store->current();

        self::assertSame($state->totals->pelletKg, $loaded->state->totals->pelletKg);
        self::assertSame($state->totals->pelletCost->cents, $loaded->state->totals->pelletCost->cents);
    }

    public function testRoundTripsTheLowTempEmittersFlag(): void
    {
        $config = new GameConfig(seed: 42, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: 10);
        $lowTemp = new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::HeatPump, lowTempEmitters: true);

        $progression = new TimeProgression(new DateTimeImmutable('@1750000000'), TickSpeed::Normal);
        $this->store->save(new Game($config, GameState::start($lowTemp, Money::fromEuros(4000.0)), $progression));

        self::assertTrue($this->store->current()->state->household->lowTempEmitters);
    }

    public function testRoundTripsTheWaterHeaterChoice(): void
    {
        $config = new GameConfig(seed: 42, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: 10);
        $thermo = new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler, waterHeater: WaterHeater::Thermodynamic);

        $progression = new TimeProgression(new DateTimeImmutable('@1750000000'), TickSpeed::Normal);
        $this->store->save(new Game($config, GameState::start($thermo, Money::fromEuros(4000.0)), $progression));

        self::assertSame(WaterHeater::Thermodynamic, $this->store->current()->state->household->waterHeater);
    }

    public function testRoundTripsTheDraughtProofingAndThermalCurtainsGestures(): void
    {
        $config = new GameConfig(seed: 42, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: 10);
        $household = new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single, Ventilation::None, draughtProofed: true, thermalCurtains: true), HeatingSystem::FuelOilBoiler);

        $progression = new TimeProgression(new DateTimeImmutable('@1750000000'), TickSpeed::Normal);
        $this->store->save(new Game($config, GameState::start($household, Money::fromEuros(4000.0)), $progression));
        $loaded = $this->store->current();

        self::assertTrue($loaded->state->household->envelope->draughtProofed);
        self::assertTrue($loaded->state->household->envelope->thermalCurtains);
    }

    public function testOlderSessionsWithoutAWaterHeaterKeyFallBackToElectricTank(): void
    {
        // Simulates a session on the current FORMAT_VERSION but missing the
        // 'waterHeater' key (e.g. a key added within a tranche without a
        // version bump) — the fallback must still hydrate cleanly.
        $this->session->set(self::SESSION_KEY, [
            'version' => 14,
            'seed' => 1,
            'epoch' => '2025-01-01',
            'horizonDays' => 365,
            'currentDay' => 5,
        ]);

        self::assertSame(WaterHeater::ElectricTank, $this->store->current()->state->household->waterHeater);
    }

    public function testResetsWhenTheStoredFormatVersionMismatches(): void
    {
        // A pre-versioning payload (or any older format): day 99, no/old version key.
        $this->session->set(self::SESSION_KEY, [
            'version' => 4,
            'seed' => 1,
            'epoch' => '2025-01-01',
            'horizonDays' => 365,
            'currentDay' => 99,
        ]);

        $game = $this->store->current();

        self::assertSame(0, $game->state->currentDay, 'Stale formats restart the game instead of half-loading.');
    }

    private function someDay(GameConfig $config, Household $household): DailySnapshot
    {
        return new SimulationEngine()->snapshot($config, GameState::start($household, Money::fromEuros(8000.0)));
    }
}
