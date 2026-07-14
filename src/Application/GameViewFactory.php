<?php

declare(strict_types=1);

namespace App\Application;

use function abs;

use App\Domain\Building\BuildingCalibration;
use App\Domain\Building\DpeCertifier;
use App\Domain\Building\DpeClass;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Energy\EnergyCalibration;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Loan;
use App\Domain\Finance\Money;
use App\Domain\Finance\PropertyValuator;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationQuoter;
use App\Domain\Scenario\PrimoAccedantScenario;
use App\Domain\Scenario\Scenario;
use App\Domain\Simulation\AnnualOutcome;
use App\Domain\Simulation\AnnualOutcomeEstimator;
use App\Domain\Simulation\DailySnapshot;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\SimulationEngine;
use App\Domain\Time\GameDate;
use App\Domain\Weather\Weather;
use App\Domain\Weather\WeatherGenerator;

use function array_map;
use function ceil;
use function count;
use function implode;
use function intdiv;
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
        private Scenario $scenario = new PrimoAccedantScenario(),
        private WeatherGenerator $weather = new WeatherGenerator(),
        private AnnualOutcomeEstimator $estimator = new AnnualOutcomeEstimator(),
        private BuildingCalibration $building = new BuildingCalibration(),
        private EnergyCalibration $energy = new EnergyCalibration(),
        private DpeCertifier $dpeCertifier = new DpeCertifier(),
    ) {
    }

    public function build(GameConfig $config, GameState $state): GameView
    {
        $snapshot = $this->engine->snapshot($config, $state);
        $balance = $snapshot->balance;
        $totals = $state->totals;
        $household = $state->household;

        // One reference-year estimate of the CURRENT house, shared by the
        // fuel-poverty rate and the works panel's "before" (no double sim).
        $currentAnnual = $this->estimator->estimate($household);
        $annualIncome = $this->finance->monthlyNetIncome()->value * 12.0;
        $effortRate = $currentAnnual->netEnergyCost->euros() / $annualIncome;

        // The two official DPE labels (energy + climate), from the year's real energy.
        $dpe = $this->dpeCertifier->certify($currentAnnual->electricityKwh, $currentAnnual->fuelOilLitres);

        // Monthly budget split by nature (living / energy / debt) so the Finances
        // panel shows where the money goes. Energy is the reference year ÷ 12 (an
        // average — the real bill is seasonal), net of solar resale.
        $monthlyIncome = Money::fromEuros($this->finance->monthlyNetIncome()->value);
        $monthlyLiving = Money::fromEuros($this->finance->monthlyLivingExpenses()->value);
        $monthlyEnergy = Money::fromCents(intdiv($currentAnnual->netEnergyCost->cents, 12));
        $monthlyLeftover = $monthlyIncome->minus($monthlyLiving)->minus($monthlyEnergy)->minus($state->loan->monthlyPayment);

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
            scene: $this->houseScene($snapshot, $household),
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
            dayNetLabel: self::signed($dayNet = $snapshot->incomeCredited->minus($snapshot->bill->netCost())->minus($snapshot->loanPayment)),
            dayNetNegative: $dayNet->isNegative(),
            monthlyIncomeLabel: $monthlyIncome->format(),
            monthlyExpensesLabel: $monthlyLiving->format(),
            monthlyEnergyCostLabel: $monthlyEnergy->format(),
            monthlyLeftoverLabel: $monthlyLeftover->format(),
            monthlyLeftoverNegative: $monthlyLeftover->isNegative(),
            energyEffortPct: (int) round($effortRate * 100),
            inFuelPoverty: $effortRate > $this->finance->fuelPovertyEffortThreshold()->value,
            propertyValueLabel: $this->property->valueFor($dpe->finalClass)->format(),
            loanActive: $state->loan->isActive(),
            loanMonthlyPaymentLabel: $state->loan->monthlyPayment->format(),
            loanRemainingLabel: $state->loan->remaining->format(),
            loanTermYears: intdiv(Loan::TERM_MONTHS, 12),
            loanRemainingYears: (int) ceil($state->loan->remainingMonths() / 12),
            heatingLabel: $household->heatingSystem->label(),
            boilerBroken: $household->boilerBroken,
            setpointC: (int) round($household->heatingSetpointC),
            setpointCanUp: $household->heatingSetpointC < $this->building->maxHeatingSetpointC()->value,
            setpointCanDown: $household->heatingSetpointC > $this->building->minHeatingSetpointC()->value,
            setpointBelowHealthy: $household->heatingSetpointC < $this->building->healthySetpointFloorC()->value,
            setpointDownEffectLabel: $this->setpointEffect($household, $currentAnnual, -1.0),
            setpointUpEffectLabel: $this->setpointEffect($household, $currentAnnual, 1.0),
            insulationLabel: $household->insulation->label(),
            dpeLetter: $dpe->finalClass->label(),
            dpeEnergyLetter: $dpe->energyClass->label(),
            dpeEnergyIntensity: (int) round($dpe->energyIntensity),
            dpeEnergyBandPct: (int) round($dpe->energyBandFillPct),
            dpeClimateLetter: $dpe->climateClass->label(),
            dpeClimateIntensity: (int) round($dpe->climateIntensity),
            dpeClimateBandPct: (int) round($dpe->climateBandFillPct),
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
            actions: $this->actionsFor($state, $currentAnnual),
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
        $initial = $this->scenario->initialState();
        $initialSavings = $initial->savings;
        $initialDpe = $this->dpeFinalClass($initial->household);
        $finalDpe = $this->dpeFinalClass($state->household);

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
            'ecoPtz' => sprintf(
                'Éco-PTZ : prêt à taux zéro (0 %% d\'intérêt) sur %d ans, jusqu\'à %s (plafond réglementaire). Réservé aux travaux de performance énergétique. La mensualité est faible et étalée — c\'est le levier qui rend la rénovation accessible — mais elle est prélevée chaque mois pendant 20 ans, et la dette restante pèse sur votre patrimoine.',
                intdiv(Loan::TERM_MONTHS, 12),
                Money::fromEuros($this->finance->loanCap()->value)->format(),
            ),
            'setpoint' => sprintf(
                'Température de consigne du chauffage. Chaque °C compte : ~+7 %% de chauffage par degré (ADEME). Repère de confort 19 °C (Code de l\'énergie), plancher santé %.0f °C (OMS) — en dessous, les habitants souffrent du froid. Baisser économise mais dégrade le confort : c\'est le vrai arbitrage de la précarité.',
                $this->building->healthySetpointFloorC()->value,
            ),
            'fuelPoverty' => sprintf(
                'Taux d\'effort énergétique : part du revenu annuel consacrée à l\'énergie du logement (estimée sur une année type). Au-delà de %.0f %% pour un ménage modeste, on parle de précarité énergétique (indicateur ONPE, loi Grenelle II) — ~12 millions de personnes en France. La rénovation en fait sortir.',
                $this->finance->fuelPovertyEffortThreshold()->value * 100,
            ),
            'worksEstimate' => 'Effets estimés en simulant une année météo type complète avec et sans les travaux, via le moteur du jeu lui-même. L\'effet réel dépendra de la météo de VOTRE partie et de la date des travaux.',
            'propertyValue' => sprintf(
                'Prix d\'achat de la maison (Notaires de France) revalorisé de +%.0f %% par classe DPE gagnée. Cette valeur n\'est réalisable qu\'à la revente — elle ne s\'additionne jamais à l\'épargne.',
                $this->finance->dpeClassValueStep()->value * 100,
            ),
        ];
    }

    /**
     * Felt-temperature tiers driving the occupant and living-room tint. Based
     * on operative (felt) temperature, not the air setpoint: OMS minimum 18 °C,
     * adaptive comfort up to ~25 °C (EN 16798). This is why 19 °C is never a
     * universal answer — a passoire's cold walls drop the felt below the air.
     */
    private const float FELT_COLD_BELOW = 14.0;
    private const float FELT_COOL_BELOW = 18.0;
    private const float FELT_HOT_ABOVE = 25.0;

    /**
     * Translates the simulation facts into the semantic scene model — states
     * and buckets only, never geometry (game-design §17).
     */
    private function houseScene(DailySnapshot $snapshot, Household $household): HouseSceneView
    {
        return new HouseSceneView(
            season: $snapshot->date->season()->value,
            cloudPct: (int) round($snapshot->weather->cloudCover * 100),
            frost: $snapshot->weather->temperatureC <= 0.0,
            producing: $snapshot->balance->productionKwh > 0.0,
            chimneySmoking: $snapshot->heating->fuelOilLitres > 0.0,
            roofState: $household->solarKwc > 0.0 ? 'installed' : 'empty',
            roofLabel: $household->solarKwc > 0.0
                ? sprintf('%.0f kWc', $household->solarKwc)
                : 'Pas de panneaux',
            insulationTier: match ($household->insulation) {
                InsulationLevel::Original => 0,
                InsulationLevel::Retrofitted => 1,
                InsulationLevel::Reinforced => 2,
            },
            insulationLabel: $household->insulation->label(),
            heatingState: match (true) {
                $household->boilerBroken => 'fioul-broken',
                HeatingSystem::HeatPump === $household->heatingSystem => 'heat-pump',
                default => 'fioul',
            },
            heatingLabel: $household->heatingSystem->label(),
            garageState: $household->batteryKwh > 0.0 ? 'installed' : 'empty',
            garageLabel: $household->batteryKwh > 0.0
                ? sprintf('%.0f kWh', $household->batteryKwh)
                : 'Pas de batterie',
            comfortState: match (true) {
                $snapshot->comfort->feltC < self::FELT_COLD_BELOW => 'cold',
                $snapshot->comfort->feltC < self::FELT_COOL_BELOW => 'cool',
                $snapshot->comfort->feltC > self::FELT_HOT_ABOVE => 'hot',
                default => 'warm',
            },
        );
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

    /** Final DPE class of a house, from its reference-year energy (energy + climate labels). */
    private function dpeFinalClass(Household $household): DpeClass
    {
        $annual = $this->estimator->estimate($household);

        return $this->dpeCertifier->certify($annual->electricityKwh, $annual->fuelOilLitres)->finalClass;
    }

    /**
     * The estimated effect of nudging the thermostat by one degree, over a
     * reference year — the live pedagogy of "−1 °C ≈ −7 % de chauffage".
     * Empty at the bounds (no button there).
     */
    private function setpointEffect(Household $household, AnnualOutcome $current, float $deltaC): string
    {
        $target = $household->heatingSetpointC + $deltaC;
        if ($target < $this->building->minHeatingSetpointC()->value || $target > $this->building->maxHeatingSetpointC()->value) {
            return '';
        }

        $billDelta = $this->estimator->estimate($household->withHeatingSetpointC($target))
            ->netEnergyCost->minus($current->netEnergyCost);

        // Nearest 10 € — false precision for an estimate.
        $euros = 10 * (int) round($billDelta->cents / 1000);

        return sprintf(
            '≈ %s%s €/an · confort %s',
            $euros < 0 ? '−' : '+',
            number_format(abs($euros), 0, ',', ' '),
            $deltaC < 0 ? 'moindre' : 'meilleur',
        );
    }

    /**
     * @return array<string, ActionView>
     */
    private function actionsFor(GameState $state, AnnualOutcome $before): array
    {
        $loanCap = Money::fromEuros($this->finance->loanCap()->value);
        $actions = [];

        foreach (Renovation::cases() as $work) {
            $quote = $this->quoter->quote($work, $state->household);
            if (null === $quote) {
                continue;
            }

            // The current house's reference year is shared; each work gets its own.
            $after = $this->estimator->estimate($quote->resultingHousehold);

            $net = $quote->netCost();

            $actions[$work->value] = new ActionView(
                work: $work->value,
                title: $quote->title,
                costLabel: $quote->cost->format(),
                subsidyLabel: $quote->subsidy->cents > 0 ? $quote->subsidy->format() : '',
                netCostLabel: $net->format(),
                cashAllowed: $state->savings->cents >= $net->cents,
                loanAllowed: $loanEligible = ($work->isLoanEligible()
                    && $state->loan->borrowedTotal->plus($net)->cents <= $loanCap->cents),
                loanMonthlyLabel: $loanEligible ? Loan::none()->borrow($net)->monthlyPayment->format() : '',
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
