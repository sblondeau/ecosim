<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\RenovationQuoter;
use App\Domain\Finance\SceneSlot;
use App\Domain\Finance\Work\HeatPumpWork;
use PHPUnit\Framework\TestCase;

/**
 * What is left to test here once every work carries its own offer/advice: the
 * FINANCING POLICY the quoter alone applies on top — the income-based prime,
 * and that it stays out of the way for work outside its perimeter. Per-work
 * eligibility (already done, mutually exclusive variants...) is covered by
 * each definition's own test under `tests/Unit/Domain/Finance/Work/`.
 */
final class RenovationQuoterTest extends TestCase
{
    private static function barePassoire(): Household
    {
        return new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler);
    }

    public function testSubsidisedWorksGetTheIncomeBracketPrime(): void
    {
        // Scenario income 2800 x 12 = 33 600 €/an -> "intermédiaire" bracket, 40 %.
        $quote = new RenovationQuoter()->quote(new HeatPumpWork(), self::barePassoire());

        self::assertNotNull($quote);
        self::assertSame(13000_00, $quote->cost->cents);
        self::assertSame(5200_00, $quote->subsidy->cents);
        self::assertSame(7800_00, $quote->netCost()->cents);
        self::assertSame(HeatingSystem::HeatPump, $quote->resultingHousehold->heatingSystem);
    }

    /**
     * The prime is the quoter's own policy, applied on top of whatever the
     * definition offers — never the definition's own concern.
     */
    public function testAppliesTheIncomeBracketPrimeToAnyEnergyPerformanceDefinition(): void
    {
        $quote = new RenovationQuoter()->quote(
            new StubDefinition('roof_insulation', Money::fromEuros(1000.0), qualifiesForEnergyAid: true),
            self::barePassoire(),
        );

        self::assertNotNull($quote);
        self::assertSame('Stub', $quote->title);
        self::assertSame(100_000, $quote->cost->cents);
        self::assertGreaterThan(0, $quote->subsidy->cents, 'the quoter applies the prime, not the definition');
    }

    public function testAppliesNoSubsidyToAWorkOutsideTheAidPerimeter(): void
    {
        $quote = new RenovationQuoter()->quote(
            new StubDefinition('home_battery', Money::fromEuros(1000.0), qualifiesForEnergyAid: false),
            self::barePassoire(),
        );

        self::assertNotNull($quote);
        self::assertSame(0, $quote->subsidy->cents);
    }
}

/** A definition whose offer is fixed, so the quoter's own policy is what gets tested. */
final readonly class StubDefinition implements RenovationDefinition
{
    public function __construct(
        private string $slug,
        private Money $cost,
        private bool $qualifiesForEnergyAid,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Walls;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        return new RenovationOffer('Stub', $this->cost, $household);
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(AdviceLevel::Info, 'stub');
    }

    public function qualifiesForEnergyAid(): bool
    {
        return $this->qualifiesForEnergyAid;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/battery.svg';
    }
}
