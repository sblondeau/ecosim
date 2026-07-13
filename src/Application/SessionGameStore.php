<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Loan;
use App\Domain\Finance\Money;
use App\Domain\Scenario\PrimoAccedantScenario;
use App\Domain\Scenario\Scenario;
use App\Domain\Simulation\GameConfig;
use App\Domain\Simulation\GameState;
use App\Domain\Simulation\PeriodTotals;
use App\Domain\Time\TickSpeed;
use App\Domain\Time\TimeProgression;
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

    /**
     * Bump whenever the stored shape changes: a session written by an older
     * format is thrown away and the game restarts, instead of being silently
     * rebuilt into a valid-looking but absurd state by the hydrate fallbacks.
     */
    private const int FORMAT_VERSION = 10;

    public function __construct(
        private RequestStack $requestStack,
        private Scenario $scenario = new PrimoAccedantScenario(),
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
     * Start a new game on the locked Phase 0-1 scenario: the primo-accédant's
     * old fuel-oil house with original insulation and NO production equipment
     * (game-design §15/§18) — installing solar, a battery, insulation or a
     * heat pump are the game's decisions.
     */
    public function reset(): Game
    {
        $config = new GameConfig(
            seed: random_int(1, 1_000_000),
            epoch: new DateTimeImmutable(self::DEFAULT_EPOCH),
            horizonDays: $this->scenario->horizonDays(),
        );

        $game = new Game(
            $config,
            $this->scenario->initialState(),
            TimeProgression::startingAt(new DateTimeImmutable()),
        );
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
            horizonDays: max(1, (int) ($data['horizonDays'] ?? $this->scenario->horizonDays())),
        );

        $household = new Household(
            solarKwc: (float) ($data['solarKwc'] ?? 0.0),
            batteryKwh: (float) ($data['batteryKwh'] ?? 0.0),
            insulation: InsulationLevel::from((string) ($data['insulation'] ?? InsulationLevel::Original->value)),
            heatingSystem: HeatingSystem::from((string) ($data['heating'] ?? HeatingSystem::FuelOilBoiler->value)),
            boilerBroken: (bool) ($data['boilerBroken'] ?? false),
            heatingSetpointC: (float) ($data['setpointC'] ?? 19.0),
        );

        $state = new GameState(
            max(0, (int) ($data['currentDay'] ?? 0)),
            $household,
            (float) ($data['batteryLevelKwh'] ?? 0.0),
            Money::fromCents((int) ($data['savingsCents'] ?? 0)),
            new Loan(
                remaining: Money::fromCents((int) ($data['loanRemainingCents'] ?? 0)),
                monthlyPayment: Money::fromCents((int) ($data['loanPaymentCents'] ?? 0)),
                borrowedTotal: Money::fromCents((int) ($data['loanBorrowedCents'] ?? 0)),
            ),
            new PeriodTotals(
                productionKwh: (float) ($data['totalProduction'] ?? 0.0),
                demandKwh: (float) ($data['totalDemand'] ?? 0.0),
                importKwh: (float) ($data['totalImport'] ?? 0.0),
                exportKwh: (float) ($data['totalExport'] ?? 0.0),
                fuelOilLitres: (float) ($data['totalFuelOil'] ?? 0.0),
                comfortScoreSum: (float) ($data['comfortScoreSum'] ?? 0.0),
                electricityCost: Money::fromCents((int) ($data['elecCostCents'] ?? 0)),
                fuelOilCost: Money::fromCents((int) ($data['fuelCostCents'] ?? 0)),
                surplusRevenue: Money::fromCents((int) ($data['revenueCents'] ?? 0)),
                days: max(0, (int) ($data['daysLived'] ?? 0)),
            ),
        );

        $lastTickAt = new DateTimeImmutable('@'.(int) ($data['lastTickAt'] ?? 0));
        $progression = new TimeProgression(
            $lastTickAt,
            TickSpeed::tryFrom((int) ($data['speed'] ?? TickSpeed::Normal->value)) ?? TickSpeed::Normal,
        );

        return new Game($config, $state, $progression);
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
            'speed' => $game->progression->speed->value,
            'lastTickAt' => $game->progression->lastTickAt->getTimestamp(),
            'currentDay' => $game->state->currentDay,
            'solarKwc' => $game->state->household->solarKwc,
            'batteryKwh' => $game->state->household->batteryKwh,
            'insulation' => $game->state->household->insulation->value,
            'heating' => $game->state->household->heatingSystem->value,
            'boilerBroken' => $game->state->household->boilerBroken,
            'setpointC' => $game->state->household->heatingSetpointC,
            'batteryLevelKwh' => $game->state->batteryLevelKwh,
            'savingsCents' => $game->state->savings->cents,
            'loanRemainingCents' => $game->state->loan->remaining->cents,
            'loanPaymentCents' => $game->state->loan->monthlyPayment->cents,
            'loanBorrowedCents' => $game->state->loan->borrowedTotal->cents,
            'totalProduction' => $game->state->totals->productionKwh,
            'totalDemand' => $game->state->totals->demandKwh,
            'totalImport' => $game->state->totals->importKwh,
            'totalExport' => $game->state->totals->exportKwh,
            'totalFuelOil' => $game->state->totals->fuelOilLitres,
            'comfortScoreSum' => $game->state->totals->comfortScoreSum,
            'elecCostCents' => $game->state->totals->electricityCost->cents,
            'fuelCostCents' => $game->state->totals->fuelOilCost->cents,
            'revenueCents' => $game->state->totals->surplusRevenue->cents,
            'daysLived' => $game->state->totals->days,
        ];
    }
}
