<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Energy\Battery;
use App\Domain\Energy\EnergyBalanceCalculator;
use App\Domain\Energy\EnergyCalibration;
use App\Domain\Energy\EnergyDemandCalculator;
use App\Domain\Energy\SolarProductionCalculator;
use App\Domain\Time\GameDate;
use App\Domain\Weather\WeatherGenerator;
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
 * Advances the in-game calendar day by day and, for each tick, generates the
 * weather and settles the energy balance (solar production vs household demand,
 * with the battery bridging to the evening) — exercising the pure domain core.
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

        $seed = (int) $input->getOption('seed');
        $solarKwc = (float) $input->getOption('solar');
        $batteryKwh = (float) $input->getOption('battery');

        $calibration = new EnergyCalibration();
        $weather = new WeatherGenerator();
        $solar = new SolarProductionCalculator($calibration);
        $demand = new EnergyDemandCalculator($calibration);
        $balancer = new EnergyBalanceCalculator($calibration);
        $battery = Battery::of($batteryKwh, $calibration->batteryOneWayEfficiency());

        $io->title(sprintf('EcoSim — %d jours depuis %s', $days, $epochOption));
        $io->text(sprintf('Graine %d · Solaire %.1f kWc · Batterie %.1f kWh', $seed, $solarKwc, $batteryKwh));

        $rows = [];
        $totalProduction = 0.0;
        $totalDemand = 0.0;
        $totalImport = 0.0;
        $totalExport = 0.0;
        $batteryLevel = 0.0;

        $date = GameDate::epoch($epoch);
        for ($tick = 0; $tick < $days; ++$tick) {
            $today = $weather->for($seed, $date);
            $production = $solar->dailyProductionKwh($solarKwc, $today, $date);
            $consumption = $demand->dailyDemandKwh($date);
            $balance = $balancer->settle($production, $consumption, $battery, $batteryLevel);
            $batteryLevel = $balance->batteryLevelKwh;

            $grid = $balance->gridImportKwh - $balance->gridExportKwh;

            $rows[] = [
                $date->format('Y-m-d'),
                sprintf('%d %%', (int) round($today->cloudCover * 100)),
                sprintf('%.1f°', $today->temperatureC),
                sprintf('%.1f', $production),
                sprintf('%.1f', $consumption),
                sprintf('%d %%', (int) round($balance->selfSufficiencyRatio() * 100)),
                sprintf('%+.1f', -$grid),
                sprintf('%.1f', $batteryLevel),
            ];

            $totalProduction += $production;
            $totalDemand += $consumption;
            $totalImport += $balance->gridImportKwh;
            $totalExport += $balance->gridExportKwh;

            $date = $date->next();
        }

        $io->table(
            ['Date', 'Nébul.', 'Temp', 'Prod', 'Conso', 'Autoconso', 'Réseau±', 'Batt'],
            $rows,
        );

        $selfSufficiency = $totalDemand > 0.0 ? ($totalDemand - $totalImport) / $totalDemand : 1.0;
        $io->definitionList(
            ['Production totale' => sprintf('%.1f kWh', $totalProduction)],
            ['Consommation totale' => sprintf('%.1f kWh', $totalDemand)],
            ['Importé du réseau' => sprintf('%.1f kWh', $totalImport)],
            ['Exporté (surplus)' => sprintf('%.1f kWh', $totalExport)],
            ['Autosuffisance' => sprintf('%d %%', (int) round($selfSufficiency * 100))],
        );

        $io->success(sprintf('Simulation terminée : %d jours joués.', $days));

        return Command::SUCCESS;
    }
}
