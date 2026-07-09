<?php

declare(strict_types=1);

namespace App\Application;

use function abs;

use App\Domain\Building\BuildingCalibration;
use App\Domain\Energy\EnergyCalibration;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\PropertyValuator;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationQuoter;
use App\Domain\Simulation\AnnualOutcome;
use App\Domain\Simulation\AnnualOutcomeEstimator;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\Scenario;
use App\Domain\Simulation\SimulationEngine;
use App\Domain\Time\GameDate;
use App\Domain\Weather\Weather;
use App\Domain\Weather\WeatherGenerator;

use function array_map;
use function count;
use function implode;
use function max;
use function min;
use function number_format;
use function range;
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
        private Scenario $scenario = new Scenario(),
        private WeatherGenerator $weather = new WeatherGenerator(),
        private AnnualOutcomeEstimator $estimator = new AnnualOutcomeEstimator(),
        private BuildingCalibration $building = new BuildingCalibration(),
        private EnergyCalibration $energy = new EnergyCalibration(),
    ) {
    }

    public function build(GameConfig $config, GameState $state): GameView
    {
        $snapshot = $this->engine->snapshot($config, $state);
        $balance = $snapshot->balance;
        $totals = $state->totals;
        $household = $state->household;

        return new GameView(
            dayNumber: min($state->currentDay + 1, $config->horizonDays),
            dateLabel: $this->frenchDate($snapshot->date),
            seasonLabel: $snapshot->date->season()->label(),
            horizonDays: $config->horizonDays,
            finished: $this->engine->isFinished($config, $state),
            progressPct: (int) round(min(1.0, $state->currentDay / $config->horizonDays) * 100),
            cloudPct: (int) round($snapshot->weather->cloudCover * 100),
            temperatureC: $snapshot->weather->temperatureC,
            weatherSparkline: $this->weatherSparkline($config, $state),
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
            boilerBroken: $household->boilerBroken,
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
            help: $this->helpTexts(),
            actions: $this->actionsFor($state),
            endReport: $this->engine->isFinished($config, $state) ? $this->endReport($state) : null,
        );
    }

    /**
     * Measures the whole game against the scenario's day 0 — one delta per
     * axis, never an aggregate (game-design §1: liquid savings and resale-only
     * property value must not be summed).
     */
    private function endReport(GameState $state): EndReportView
    {
        $initialSavings = $this->scenario->startingSavings();
        $initialDpe = $this->scenario->initialHousehold()->dpeClass();
        $finalDpe = $state->household->dpeClass();

        $initialProperty = $this->property->valueFor($initialDpe);
        $finalProperty = $this->property->valueFor($finalDpe);

        $savingsDelta = $state->savings->minus($initialSavings);

        return new EndReportView(
            daysLived: $state->totals->days,
            savingsStartLabel: $initialSavings->format(),
            savingsEndLabel: $state->savings->format(),
            savingsDeltaLabel: self::signed($savingsDelta),
            savingsDeltaNegative: $savingsDelta->isNegative(),
            dpeStartLetter: $initialDpe->label(),
            dpeEndLetter: $finalDpe->label(),
            propertyStartLabel: $initialProperty->format(),
            propertyEndLabel: $finalProperty->format(),
            propertyDeltaLabel: self::signed($finalProperty->minus($initialProperty)),
            loanActive: $state->loan->isActive(),
            loanRemainingLabel: $state->loan->remaining->format(),
            averageComfortPct: $state->totals->averageComfortScore(),
            totalFuelOilLitres: round($state->totals->fuelOilLitres, 1),
            totalSelfSufficiencyPct: (int) round($state->totals->selfSufficiencyRatio() * 100),
            totalNetEnergyCostLabel: $state->totals->netEnergyCost()->format(),
        );
    }

    /**
     * Explicitly signed amount for a delta ("+1 234,56 €" / "−1 234,56 €").
     */
    private static function signed(Money $delta): string
    {
        return $delta->isNegative() ? $delta->format() : '+'.$delta->format();
    }

    /**
     * Player-facing explanations, phrased from the calibration registry: the
     * quoted figures are the simulated ones, and their sources are named.
     *
     * @return array<string, string>
     */
    private function helpTexts(): array
    {
        $priceKwh = $this->finance->electricityPricePerKwh()->value;
        $sellKwh = $this->finance->surplusSellPricePerKwh()->value;

        return [
            'comfort' => sprintf(
                '100 %% tant que la température ressentie reste entre %.0f et %.0f °C (repères ADEME) ; −%.0f points par °C en dehors. Le ressenti descend sous la température de l\'air quand les murs sont mal isolés (effet parois froides).',
                $this->building->comfortMinC()->value,
                $this->building->comfortMaxC()->value,
                $this->building->comfortLossPerDegree()->value,
            ),
            'selfSufficiency' => 'Part de votre consommation électrique couverte par votre propre production (directement ou via la batterie) au lieu d\'être achetée au réseau.',
            'cloud' => sprintf(
                'Couverture nuageuse du jour : sous un ciel totalement couvert, les panneaux perdent jusqu\'à %.0f %% de leur production (calibration sourcée).',
                $this->energy->solarCloudLossFactor()->value * 100,
            ),
            'electricity' => sprintf(
                'Électricité achetée au réseau à %s €/kWh (tarif réglementé, CRE).',
                number_format($priceKwh, 2, ',', ' '),
            ),
            'surplus' => sprintf(
                'Le surplus injecté est racheté %s €/kWh (contrat type EDF OA) — environ %d fois moins que le prix d\'achat. Autoconsommer vaut bien plus que revendre.',
                number_format($sellKwh, 3, ',', ' '),
                (int) round($priceKwh / $sellKwh),
            ),
            'fuelOil' => sprintf(
                'Litres brûlés pour couvrir le besoin de chauffage du jour : rendement de chaudière ~%.0f %%, %s kWh par litre (ADEME / DGEC), à %s €/L.',
                $this->energy->fuelOilBoilerEfficiency()->value * 100,
                number_format($this->energy->fuelOilEnergyKwhPerLitre()->value, 2, ',', ' '),
                number_format($this->finance->fuelOilPricePerLitre()->value, 2, ',', ' '),
            ),
            'netIncome' => 'Revenu net du foyer (INSEE) moins les dépenses de vie hors énergie, crédité le 1er du mois. L\'énergie, elle, est payée jour par jour par la simulation.',
            'worksEstimate' => 'Effets estimés en simulant une année météo type complète avec et sans les travaux, via le moteur du jeu lui-même. L\'effet réel dépendra de la météo de VOTRE partie et de la date des travaux.',
            'propertyValue' => sprintf(
                'Prix d\'achat de la maison (Notaires de France) revalorisé de +%.0f %% par classe DPE gagnée. Cette valeur n\'est réalisable qu\'à la revente — elle ne s\'additionne jamais à l\'épargne.',
                $this->finance->dpeClassValueStep()->value * 100,
            ),
        ];
    }

    private const int SPARKLINE_DAYS = 30;

    /**
     * The last ≤30 days of weather, recomputed on the fly: the generator is
     * seeded and deterministic, so the past needs no storage at all.
     */
    private function weatherSparkline(GameConfig $config, GameState $state): SparklineView
    {
        $firstDay = max(0, $state->currentDay - (self::SPARKLINE_DAYS - 1));

        $window = array_map(
            fn (int $day): Weather => $this->weather->for($config->seed, GameDate::fromDayIndex($config->epoch, $day)),
            range($firstDay, $state->currentDay),
        );
        $temperatures = array_map(static fn (Weather $w): float => $w->temperatureC, $window);
        $clouds = array_map(static fn (Weather $w): float => $w->cloudCover, $window);

        // Pad the temperature scale so a flat spell does not fill the box.
        $minTemp = min($temperatures) - 1.0;
        $maxTemp = max($temperatures) + 1.0;

        return new SparklineView(
            days: count($temperatures),
            temperaturePoints: self::polyline($temperatures, $minTemp, $maxTemp),
            cloudPoints: self::polyline($clouds, 0.0, 1.0),
            temperatureMinLabel: sprintf('%.0f°', $minTemp + 1.0),
            temperatureMaxLabel: sprintf('%.0f°', $maxTemp - 1.0),
        );
    }

    /**
     * Projects a series into the sparkline viewBox (y axis pointing down).
     *
     * @param list<float> $values
     */
    private static function polyline(array $values, float $min, float $max): string
    {
        $count = count($values);
        $span = $max - $min;
        $points = [];

        foreach ($values as $i => $value) {
            $x = 1 === $count ? SparklineView::WIDTH : $i / ($count - 1) * SparklineView::WIDTH;
            $y = SparklineView::HEIGHT * (1.0 - ($value - $min) / $span);
            $points[] = sprintf('%.1f,%.1f', $x, $y);
        }

        return implode(' ', $points);
    }

    /**
     * @return array<string, ActionView>
     */
    private function actionsFor(GameState $state): array
    {
        $loanCap = Money::fromEuros($this->finance->loanCap()->value);
        $actions = [];
        $before = null;

        foreach (Renovation::cases() as $work) {
            $quote = $this->quoter->quote($work, $state->household);
            if (null === $quote) {
                continue;
            }

            // Estimated lazily: one reference year for the current house…
            $before ??= $this->estimator->estimate($state->household);
            // …and one per available work.
            $after = $this->estimator->estimate($quote->resultingHousehold);

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
                effectLabels: self::effectLabels($before, $after),
            );
        }

        return $actions;
    }

    /**
     * The differences a work makes over the reference year, as player-facing
     * lines — only the axes it actually moves.
     *
     * @return list<string>
     */
    private static function effectLabels(AnnualOutcome $before, AnnualOutcome $after): array
    {
        $labels = [];

        // Nearest 10 € — quoting cents would be false precision for an estimate.
        $billDeltaEuros = 10 * (int) round(($after->netEnergyCost->cents - $before->netEnergyCost->cents) / 1000);
        if (0 !== $billDeltaEuros) {
            $labels[] = sprintf(
                'Facture énergie : ≈ %s%s €/an',
                $billDeltaEuros < 0 ? '−' : '+',
                number_format(abs($billDeltaEuros), 0, ',', ' '),
            );
        }

        if ($after->averageComfortScore !== $before->averageComfortScore) {
            $labels[] = sprintf('Confort moyen : %d %% → %d %%', $before->averageComfortScore, $after->averageComfortScore);
        }

        if ($after->productionKwh > $before->productionKwh) {
            $labels[] = sprintf('Production solaire : ≈ %d kWh/an', (int) round($after->productionKwh, -1));
        }

        $selfBefore = (int) round($before->selfSufficiencyRatio * 100);
        $selfAfter = (int) round($after->selfSufficiencyRatio * 100);
        if ($selfAfter !== $selfBefore) {
            $labels[] = sprintf('Autosuffisance : %d %% → %d %%', $selfBefore, $selfAfter);
        }

        return $labels;
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
