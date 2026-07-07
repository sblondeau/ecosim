<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Game;
use App\Application\SessionGameStore;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Simulation\DailySnapshot;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\SimulationEngine;
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
        self::assertGreaterThan(0.0, $game->state->household->solarKwc, 'Default equipment is installed.');
        self::assertSame(InsulationLevel::Original, $game->state->household->insulation, 'The scenario starts uninsulated.');
        self::assertSame(HeatingSystem::FuelOilBoiler, $game->state->household->heatingSystem, 'The scenario starts on fuel oil.');
        self::assertTrue($this->session->has(self::SESSION_KEY), 'The fresh game is persisted.');
    }

    public function testRoundTripsAGameThroughTheSession(): void
    {
        $config = new GameConfig(seed: 42, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: 10);
        $household = new Household(3.0, 5.0, InsulationLevel::Retrofitted, HeatingSystem::HeatPump);
        $state = GameState::start($household)->advanced($this->someDay($config, $household));

        $this->store->save(new Game($config, $state));
        $loaded = $this->store->current();

        self::assertSame(42, $loaded->config->seed);
        self::assertSame('2025-01-01', $loaded->config->epoch->format('Y-m-d'));
        self::assertSame(10, $loaded->config->horizonDays);
        self::assertSame(1, $loaded->state->currentDay);
        self::assertSame(3.0, $loaded->state->household->solarKwc);
        self::assertSame(5.0, $loaded->state->household->batteryKwh);
        self::assertSame(InsulationLevel::Retrofitted, $loaded->state->household->insulation);
        self::assertSame(HeatingSystem::HeatPump, $loaded->state->household->heatingSystem);
        self::assertSame($state->batteryLevelKwh, $loaded->state->batteryLevelKwh);
        self::assertSame($state->totals->productionKwh, $loaded->state->totals->productionKwh);
        self::assertSame($state->totals->fuelOilLitres, $loaded->state->totals->fuelOilLitres);
        self::assertSame($state->totals->days, $loaded->state->totals->days);
    }

    public function testResetsWhenTheStoredFormatVersionMismatches(): void
    {
        // A pre-versioning payload (or any older format): day 99, no/old version key.
        $this->session->set(self::SESSION_KEY, [
            'version' => 2,
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
        return new SimulationEngine()->snapshot($config, GameState::start($household));
    }
}
