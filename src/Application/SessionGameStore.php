<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Energy\EnergyCalibration;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\PeriodTotals;
use DateTimeImmutable;

use function is_array;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Persists the current game in the HTTP session (Phase 0-1 vertical slice).
 *
 * No database yet: a game is stored as a flat array of primitives and rebuilt
 * into domain objects on load. Implements {@see GameStore} so a Doctrine-backed
 * store can replace this class later — the controller depends on the
 * interface, not on this implementation.
 */
final readonly class SessionGameStore implements GameStore
{
    private const string SESSION_KEY = 'ecosim_game';
    private const string DEFAULT_EPOCH = '2025-01-01';
    private const int DEFAULT_HORIZON_DAYS = 365;

    public function __construct(
        private RequestStack $requestStack,
        private EnergyCalibration $calibration = new EnergyCalibration(),
    ) {
    }

    /**
     * The game in progress, or a fresh one if none has been started yet.
     */
    public function current(): Game
    {
        $data = $this->requestStack->getSession()->get(self::SESSION_KEY);

        return is_array($data) ? $this->hydrate($data) : $this->reset();
    }

    public function save(Game $game): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $this->dehydrate($game));
    }

    /**
     * Start a new game with the default Phase 0-1 equipment and store it.
     */
    public function reset(): Game
    {
        $epoch = new DateTimeImmutable(self::DEFAULT_EPOCH);
        $config = new GameConfig(
            seed: random_int(1, 1_000_000),
            epoch: $epoch,
            solarKwc: $this->calibration->defaultSolarPeakPowerKwc()->value,
            batteryKwh: $this->calibration->defaultBatteryCapacityKwh()->value,
            horizonDays: self::DEFAULT_HORIZON_DAYS,
        );

        $game = new Game($config, GameState::start());
        $this->save($game);

        return $game;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): Game
    {
        $config = new GameConfig(
            seed: (int) ($data['seed'] ?? 0),
            epoch: new DateTimeImmutable((string) ($data['epoch'] ?? self::DEFAULT_EPOCH)),
            solarKwc: (float) ($data['solarKwc'] ?? 0.0),
            batteryKwh: (float) ($data['batteryKwh'] ?? 0.0),
            horizonDays: max(1, (int) ($data['horizonDays'] ?? self::DEFAULT_HORIZON_DAYS)),
        );

        $state = new GameState(
            max(0, (int) ($data['currentDay'] ?? 0)),
            (float) ($data['batteryLevelKwh'] ?? 0.0),
            new PeriodTotals(
                productionKwh: (float) ($data['totalProduction'] ?? 0.0),
                demandKwh: (float) ($data['totalDemand'] ?? 0.0),
                importKwh: (float) ($data['totalImport'] ?? 0.0),
                exportKwh: (float) ($data['totalExport'] ?? 0.0),
            ),
        );

        return new Game($config, $state);
    }

    /**
     * @return array<string, mixed>
     */
    private function dehydrate(Game $game): array
    {
        return [
            'seed' => $game->config->seed,
            'epoch' => $game->config->epoch->format('Y-m-d'),
            'solarKwc' => $game->config->solarKwc,
            'batteryKwh' => $game->config->batteryKwh,
            'horizonDays' => $game->config->horizonDays,
            'currentDay' => $game->state->currentDay,
            'batteryLevelKwh' => $game->state->batteryLevelKwh,
            'totalProduction' => $game->state->totals->productionKwh,
            'totalDemand' => $game->state->totals->demandKwh,
            'totalImport' => $game->state->totals->importKwh,
            'totalExport' => $game->state->totals->exportKwh,
        ];
    }
}
