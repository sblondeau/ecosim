<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Energy\EnergyCalibration;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\SimulationEngine;
use DateTimeImmutable;

use function implode;
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
 * ({@see \App\Controller\GameController}) — day by day, printing weather,
 * energy balance, heating and comfort for each tick. The house always starts
 * with the scenario's original envelope (surface-by-surface renovation quotes
 * are a game decision, not a CLI option); only the heating system is
 * configurable, to compare fioul vs heat-pump runs.
 */
#[AsCommand(
    name: 'app:simulate:demo',
    description: 'Advance the simulation day by day and print weather, energy, heating and comfort.',
)]
final class SimulateDemoCommand extends Command
{
    private const string DEFAULT_EPOCH = '2025-01-01';
    private const int DEFAULT_DAYS = 14;
    private const int DEFAULT_SEED = 2025;

    protected function configure(): void
    {
        $calibration = new EnergyCalibration();
        $heatings = implode('|', array_map(static fn (HeatingSystem $h): string => $h->value, HeatingSystem::cases()));

        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days to simulate', (string) self::DEFAULT_DAYS)
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'Epoch date (Y-m-d)', self::DEFAULT_EPOCH)
            ->addOption('seed', 's', InputOption::VALUE_REQUIRED, 'Weather seed (same seed = same weather)', (string) self::DEFAULT_SEED)
            ->addOption('solar', null, InputOption::VALUE_REQUIRED, sprintf('Installed solar peak power (kWc, catalogue model: %.0f)', $calibration->defaultSolarPeakPowerKwc()->value), '0')
            ->addOption('battery', null, InputOption::VALUE_REQUIRED, sprintf('Battery capacity (kWh, catalogue model: %.0f)', $calibration->defaultBatteryCapacityKwh()->value), '0')
            ->addOption('heating', null, InputOption::VALUE_REQUIRED, "Heating system ({$heatings})", HeatingSystem::FuelOilBoiler->value);
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

        $heating = HeatingSystem::tryFrom((string) $input->getOption('heating'));
        if (null === $heating) {
            $io->error(sprintf('Invalid --heating "%s".', (string) $input->getOption('heating')));

            return Command::INVALID;
        }

        $household = new Household(
            solarKwc: (float) $input->getOption('solar'),
            batteryKwh: (float) $input->getOption('battery'),
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: $heating,
        );

        $config = new GameConfig(
            seed: (int) $input->getOption('seed'),
            epoch: $epoch,
            horizonDays: $days,
        );
        $engine = new SimulationEngine();
        $finance = new FinanceCalibration();

        $io->title(sprintf('EcoSim — %d jours depuis %s', $days, $epochOption));
        $io->text(sprintf(
            'Graine %d · Solaire %.1f kWc · Batterie %.1f kWh · Enveloppe %s / %s / %s · %s',
            $config->seed,
            $household->solarKwc,
            $household->batteryKwh,
            $household->envelope->roofInsulated ? 'combles isolés' : 'combles d\'origine',
            $household->envelope->walls->label(),
            $household->envelope->glazing->label(),
            $household->heatingSystem->label(),
        ));

        $rows = [];
        $state = GameState::start($household, Money::fromEuros($finance->startingSavings()->value));
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
                sprintf('%.0f', $snapshot->heating->needKwh),
                match (true) {
                    $state->household->boilerBroken => 'panne ⚠',
                    $snapshot->heating->fuelOilLitres > 0.0 => sprintf('%.1f L', $snapshot->heating->fuelOilLitres),
                    default => sprintf('%.1f kWh', $snapshot->heating->electricityKwh),
                },
                sprintf('%d %%', $snapshot->comfort->score),
                $snapshot->bill->netCost()->format(),
                sprintf('%+.1f', -$grid),
            ];

            $state = $engine->advance($config, $state);
        }

        $io->table(
            ['Date', 'Nébul.', 'Temp', 'Prod', 'Conso él.', 'Besoin ch.', 'Combustible', 'Confort', 'Facture/j', 'Réseau±'],
            $rows,
        );

        $totals = $state->totals;
        $io->definitionList(
            ['Production totale' => sprintf('%.1f kWh', $totals->productionKwh)],
            ['Consommation élec. totale' => sprintf('%.1f kWh', $totals->demandKwh)],
            ['Importé du réseau' => sprintf('%.1f kWh', $totals->importKwh)],
            ['Exporté (surplus)' => sprintf('%.1f kWh', $totals->exportKwh)],
            ['Autosuffisance' => sprintf('%d %%', (int) round($totals->selfSufficiencyRatio() * 100))],
            ['Fioul consommé' => sprintf('%.1f L', $totals->fuelOilLitres)],
            ['Confort moyen' => sprintf('%d %%', $totals->averageComfortScore())],
            ['Facture électricité' => $totals->electricityCost->format()],
            ['Facture fioul' => $totals->fuelOilCost->format()],
            ['Revente cumulée' => $totals->surplusRevenue->format()],
            ['Coût énergie net' => $totals->netEnergyCost()->format()],
            ['Épargne finale' => $state->savings->format()],
        );

        $io->success(sprintf('Simulation terminée : %d jours joués.', $days));

        return Command::SUCCESS;
    }
}
