<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Game;
use App\Application\SessionGameStore;
use App\Domain\Energy\EnergyBalance;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
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

    public function testStartsAFreshGameWhenTheSessionIsEmpty(): void
    {
        $game = $this->store->current();

        self::assertSame(0, $game->state->currentDay);
        self::assertGreaterThan(0.0, $game->state->solarKwc, 'Default equipment is installed.');
        self::assertTrue($this->session->has(self::SESSION_KEY), 'The fresh game is persisted.');
    }

    public function testRoundTripsAGameThroughTheSession(): void
    {
        $config = new GameConfig(seed: 42, epoch: new DateTimeImmutable('2025-01-01'), horizonDays: 10);
        $state = GameState::start(solarKwc: 3.0, batteryKwh: 5.0)
            ->advanced(2.5, new EnergyBalance(8.0, 6.0, 5.0, 1.0, 2.0, 3.0, 1.5, 2.5));

        $this->store->save(new Game($config, $state));
        $loaded = $this->store->current();

        self::assertSame(42, $loaded->config->seed);
        self::assertSame('2025-01-01', $loaded->config->epoch->format('Y-m-d'));
        self::assertSame(10, $loaded->config->horizonDays);
        self::assertSame(1, $loaded->state->currentDay);
        self::assertSame(3.0, $loaded->state->solarKwc);
        self::assertSame(5.0, $loaded->state->batteryKwh);
        self::assertSame(2.5, $loaded->state->batteryLevelKwh);
        self::assertSame(8.0, $loaded->state->totals->productionKwh);
        self::assertSame(1.0, $loaded->state->totals->importKwh);
    }

    public function testResetsWhenTheStoredFormatVersionMismatches(): void
    {
        // A pre-versioning payload (or any older format): day 99, no version key.
        $this->session->set(self::SESSION_KEY, [
            'seed' => 1,
            'epoch' => '2025-01-01',
            'horizonDays' => 365,
            'currentDay' => 99,
        ]);

        $game = $this->store->current();

        self::assertSame(0, $game->state->currentDay, 'Stale formats restart the game instead of half-loading.');
    }
}
