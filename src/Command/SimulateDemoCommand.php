<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Energy\EnergyCalibration;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\SimulationEngine;
use DateTimeImmutable;

use function sprintf;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Demonstrates the Phase 0-1 daily loop in the terminal, with no database or UI.
 *
 * Drives the same {@see SimulationEngine} as the web dashboard
 * ({@see \App\Controller\GameController}) — day by day, printing the weather
 * and energy balance for each tick. Exercises the pure domain core with no
 * other entry-point-specific logic duplicated here.
 */
#[AsCommand(
    name: 'app:simulate:demo',
    description: 'Advance the simulation day by day and print weather + energy balance.',
)]
final class SimulateDemoCommand extends Command
{
    private const string DEFAULT_EPOCH = '2025-01-01';
    private const int DEFAULT_DAYS = 14;
    private const int DEFAULT_SEED = 2025;

    protected function configure(): void
    {
        $calibration = new EnergyCalibration();

        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days to simulate', (string) self::DEFAULT_DAYS)
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'Epoch date (Y-m-d)', self::DEFAULT_EPOCH)
            ->addOption('seed', 's', InputOption::VALUE_REQUIRED, 'Weather seed (same seed = same weather)', (string) self::DEFAULT_SEED)
            ->addOption('solar', null, InputOption::VALUE_REQUIRED, 'Installed solar peak power (kWc)', (string) $calibration->defaultSolarPeakPowerKwc()->value)
            ->addOption('battery', null, InputOption::VALUE_REQUIRED, 'Battery capacity (kWh, 0 = none)', (string) $calibration->defaultBatteryCapacityKwh()->value);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        if ($days < 1) {
            $io->error('The --days option must be a positive integer.');

            return Command::INVALID;
        }

        $epochOption = (string) $input->getOption('from');
        $epoch = DateTimeImmutable::createFromFormat('!Y-m-d', $epochOption);
        if (false === $epoch) {
            $io->error(sprintf('Invalid --from date "%s" (expected Y-m-d).', $epochOption));

            return Command::INVALID;
        }

        $config = new GameConfig(
            seed: (int) $input->getOption('seed'),
            epoch: $epoch,
            solarKwc: (float) $input->getOption('solar'),
            batteryKwh: (float) $input->getOption('battery'),
            horizonDays: $days,
        );
        $engine = new SimulationEngine();

        $io->title(sprintf('EcoSim — %d jours depuis %s', $days, $epochOption));
        $io->text(sprintf('Graine %d · Solaire %.1f kWc · Batterie %.1f kWh', $config->seed, $config->solarKwc, $config->batteryKwh));

        $rows = [];
        $state = GameState::start();
        while (!$engine->isFinished($config, $state)) {
            $snapshot = $engine->snapshot($config, $state);
            $balance = $snapshot->balance;
            $grid = $balance->gridImportKwh - $balance->gridExportKwh;

            $rows[] = [
                $snapshot->date->format('Y-m-d'),
                sprintf('%d %%', (int) round($snapshot->weather->cloudCover * 100)),
                sprintf('%.1f°', $snapshot->weather->temperatureC),
                sprintf('%.1f', $balance->productionKwh),
                sprintf('%.1f', $balance->demandKwh),
                sprintf('%d %%', (int) round($balance->selfSufficiencyRatio() * 100)),
                sprintf('%+.1f', -$grid),
                sprintf('%.1f', $balance->batteryLevelKwh),
            ];

            $state = $engine->advance($config, $state);
        }

        $io->table(
            ['Date', 'Nébul.', 'Temp', 'Prod', 'Conso', 'Autoconso', 'Réseau±', 'Batt'],
            $rows,
        );

        $totals = $state->totals;
        $io->definitionList(
            ['Production totale' => sprintf('%.1f kWh', $totals->productionKwh)],
            ['Consommation totale' => sprintf('%.1f kWh', $totals->demandKwh)],
            ['Importé du réseau' => sprintf('%.1f kWh', $totals->importKwh)],
            ['Exporté (surplus)' => sprintf('%.1f kWh', $totals->exportKwh)],
            ['Autosuffisance' => sprintf('%d %%', (int) round($totals->selfSufficiencyRatio() * 100))],
        );

        $io->success(sprintf('Simulation terminée : %d jours joués.', $days));

        return Command::SUCCESS;
    }
}
