<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\SimulationEngine;
use App\Domain\Time\GameDate;

use function sprintf;

/**
 * Builds the flat {@see GameView} from the domain state (game-design §3).
 *
 * This is the boundary between the pure simulation and the UI: it reads the
 * current day's snapshot and the running totals and turns them into
 * display-ready scalars (French labels, percentages, rounded figures).
 */
final readonly class GameViewFactory
{
    private const array MONTHS_FR = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

    private const array WEEKDAYS_FR = [
        1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi',
        5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche',
    ];

    public function __construct(
        private SimulationEngine $engine = new SimulationEngine(),
    ) {
    }

    public function build(GameConfig $config, GameState $state): GameView
    {
        $snapshot = $this->engine->snapshot($config, $state);
        $balance = $snapshot->balance;
        $totals = $state->totals;

        return new GameView(
            dayNumber: $state->currentDay + 1,
            dateLabel: $this->frenchDate($snapshot->date),
            seasonLabel: $snapshot->date->season()->label(),
            horizonDays: $config->horizonDays,
            finished: $this->engine->isFinished($config, $state),
            progressPct: (int) round(min(1.0, $state->currentDay / $config->horizonDays) * 100),
            cloudPct: (int) round($snapshot->weather->cloudCover * 100),
            temperatureC: $snapshot->weather->temperatureC,
            productionKwh: $balance->productionKwh,
            demandKwh: $balance->demandKwh,
            selfSufficiencyPct: (int) round($balance->selfSufficiencyRatio() * 100),
            gridImportKwh: $balance->gridImportKwh,
            gridExportKwh: $balance->gridExportKwh,
            solarKwc: $state->solarKwc,
            batteryLevelKwh: $balance->batteryLevelKwh,
            batteryCapacityKwh: $state->batteryKwh,
            batteryPct: $state->batteryKwh > 0.0 ? (int) round($balance->batteryLevelKwh / $state->batteryKwh * 100) : 0,
            totalProductionKwh: round($totals->productionKwh, 1),
            totalImportKwh: round($totals->importKwh, 1),
            totalExportKwh: round($totals->exportKwh, 1),
            totalSelfSufficiencyPct: (int) round($totals->selfSufficiencyRatio() * 100),
        );
    }

    private function frenchDate(GameDate $date): string
    {
        $weekday = self::WEEKDAYS_FR[(int) $date->format('N')];
        $day = (int) $date->format('j');
        $month = self::MONTHS_FR[(int) $date->format('n')];
        $year = $date->format('Y');

        return sprintf('%s %d %s %s', $weekday, $day, $month, $year);
    }
}
