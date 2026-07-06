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

    /**
     * Bump whenever the stored shape changes: a session written by an older
     * format is thrown away and the game restarts, instead of being silently
     * rebuilt into a valid-looking but absurd state by the hydrate fallbacks.
     */
    private const int FORMAT_VERSION = 2;

    public function __construct(
        private RequestStack $requestStack,
        private EnergyCalibration $calibration = new EnergyCalibration(),
    ) {
    }

    /**
     * The game in progress, or a fresh one if none has been started yet
     * (or if the stored game predates the current session format).
     */
    public function current(): Game
    {
        $data = $this->requestStack->getSession()->get(self::SESSION_KEY);

        if (!is_array($data) || self::FORMAT_VERSION !== ($data['version'] ?? null)) {
            return $this->reset();
        }

        return $this->hydrate($data);
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
        $config = new GameConfig(
            seed: random_int(1, 1_000_000),
            epoch: new DateTimeImmutable(self::DEFAULT_EPOCH),
            horizonDays: self::DEFAULT_HORIZON_DAYS,
        );

        $state = GameState::start(
            solarKwc: $this->calibration->defaultSolarPeakPowerKwc()->value,
            batteryKwh: $this->calibration->defaultBatteryCapacityKwh()->value,
        );

        $game = new Game($config, $state);
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
            horizonDays: max(1, (int) ($data['horizonDays'] ?? self::DEFAULT_HORIZON_DAYS)),
        );

        $state = new GameState(
            max(0, (int) ($data['currentDay'] ?? 0)),
            (float) ($data['solarKwc'] ?? 0.0),
            (float) ($data['batteryKwh'] ?? 0.0),
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
            'version' => self::FORMAT_VERSION,
            'seed' => $game->config->seed,
            'epoch' => $game->config->epoch->format('Y-m-d'),
            'horizonDays' => $game->config->horizonDays,
            'currentDay' => $game->state->currentDay,
            'solarKwc' => $game->state->solarKwc,
            'batteryKwh' => $game->state->batteryKwh,
            'batteryLevelKwh' => $game->state->batteryLevelKwh,
            'totalProduction' => $game->state->totals->productionKwh,
            'totalDemand' => $game->state->totals->demandKwh,
            'totalImport' => $game->state->totals->importKwh,
            'totalExport' => $game->state->totals->exportKwh,
        ];
    }
}
