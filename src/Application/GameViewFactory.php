<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\PropertyValuator;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationQuoter;
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
        private FinanceCalibration $finance = new FinanceCalibration(),
        private PropertyValuator $property = new PropertyValuator(),
        private RenovationQuoter $quoter = new RenovationQuoter(),
    ) {
    }

    public function build(GameConfig $config, GameState $state): GameView
    {
        $snapshot = $this->engine->snapshot($config, $state);
        $balance = $snapshot->balance;
        $totals = $state->totals;
        $household = $state->household;

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
            savingsLabel: $state->savings->format(),
            savingsNegative: $state->savings->isNegative(),
            electricityCostLabel: $snapshot->bill->electricityCost->format(),
            fuelOilCostLabel: $snapshot->bill->fuelOilCost->format(),
            surplusRevenueLabel: $snapshot->bill->surplusRevenue->format(),
            incomeCreditedToday: $snapshot->incomeCredited->cents > 0,
            monthlyIncomeLabel: Money::fromEuros($this->finance->monthlyNetIncome()->value)->format(),
            monthlyExpensesLabel: Money::fromEuros($this->finance->monthlyLivingExpenses()->value)->format(),
            monthlyNetIncomeLabel: Money::fromEuros(
                $this->finance->monthlyNetIncome()->value - $this->finance->monthlyLivingExpenses()->value,
            )->format(),
            propertyValueLabel: $this->property->valueFor($household->dpeClass())->format(),
            loanActive: $state->loan->isActive(),
            loanMonthlyPaymentLabel: $state->loan->monthlyPayment->format(),
            loanRemainingLabel: $state->loan->remaining->format(),
            heatingLabel: $household->heatingSystem->label(),
            insulationLabel: $household->insulation->label(),
            dpeLetter: $household->dpeClass()->label(),
            heatingElectricityKwh: $snapshot->heating->electricityKwh,
            fuelOilLitres: $snapshot->heating->fuelOilLitres,
            comfortScorePct: $snapshot->comfort->score,
            indoorTemperatureC: $snapshot->comfort->indoorC,
            feltTemperatureC: $snapshot->comfort->feltC,
            solarKwc: $household->solarKwc,
            batteryLevelKwh: $balance->batteryLevelKwh,
            batteryCapacityKwh: $household->batteryKwh,
            batteryPct: $household->batteryKwh > 0.0 ? (int) round($balance->batteryLevelKwh / $household->batteryKwh * 100) : 0,
            batteryDischargedKwh: $balance->batteryDischargedKwh,
            totalProductionKwh: round($totals->productionKwh, 1),
            totalImportKwh: round($totals->importKwh, 1),
            totalExportKwh: round($totals->exportKwh, 1),
            totalSelfSufficiencyPct: (int) round($totals->selfSufficiencyRatio() * 100),
            totalFuelOilLitres: round($totals->fuelOilLitres, 1),
            averageComfortPct: $totals->averageComfortScore(),
            totalElectricityCostLabel: $totals->electricityCost->format(),
            totalFuelOilCostLabel: $totals->fuelOilCost->format(),
            totalSurplusRevenueLabel: $totals->surplusRevenue->format(),
            totalNetEnergyCostLabel: $totals->netEnergyCost()->format(),
            actions: $this->actionsFor($state),
        );
    }

    /**
     * @return array<string, ActionView>
     */
    private function actionsFor(GameState $state): array
    {
        $loanCap = Money::fromEuros($this->finance->loanCap()->value);
        $actions = [];

        foreach (Renovation::cases() as $work) {
            $quote = $this->quoter->quote($work, $state->household);
            if (null === $quote) {
                continue;
            }

            $net = $quote->netCost();

            $actions[$work->value] = new ActionView(
                work: $work->value,
                title: $quote->title,
                costLabel: $quote->cost->format(),
                subsidyLabel: $quote->subsidy->cents > 0 ? $quote->subsidy->format() : '',
                netCostLabel: $net->format(),
                cashAllowed: $state->savings->cents >= $net->cents,
                loanAllowed: $work->isLoanEligible()
                    && $state->loan->borrowedTotal->plus($net)->cents <= $loanCap->cents,
            );
        }

        return $actions;
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
