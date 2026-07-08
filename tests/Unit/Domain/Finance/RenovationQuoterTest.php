<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\InsulationLevel;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationQuoter;
use PHPUnit\Framework\TestCase;

final class RenovationQuoterTest extends TestCase
{
    private static function barePassoire(): Household
    {
        return new Household(0.0, 0.0, InsulationLevel::Original, HeatingSystem::FuelOilBoiler);
    }

    public function testInsulationQuoteDependsOnTheCurrentTier(): void
    {
        $quoter = new RenovationQuoter();

        $first = $quoter->quote(Renovation::Insulation, self::barePassoire());
        self::assertNotNull($first);
        self::assertSame(15000_00, $first->cost->cents);
        self::assertSame(InsulationLevel::Retrofitted, $first->resultingHousehold->insulation);

        $second = $quoter->quote(Renovation::Insulation, $first->resultingHousehold);
        self::assertNotNull($second);
        self::assertSame(25000_00, $second->cost->cents);
        self::assertSame(InsulationLevel::Reinforced, $second->resultingHousehold->insulation);

        self::assertNull(
            $quoter->quote(Renovation::Insulation, $second->resultingHousehold),
            'Nothing left to insulate at the top tier.',
        );
    }

    public function testSubsidisedWorksGetTheIncomeBracketPrime(): void
    {
        // Scenario income 2800 x 12 = 33 600 €/an -> "intermédiaire" bracket, 40 %.
        $quote = new RenovationQuoter()->quote(Renovation::HeatPump, self::barePassoire());

        self::assertNotNull($quote);
        self::assertSame(13000_00, $quote->cost->cents);
        self::assertSame(5200_00, $quote->subsidy->cents);
        self::assertSame(7800_00, $quote->netCost()->cents);
        self::assertSame(HeatingSystem::HeatPump, $quote->resultingHousehold->heatingSystem);
    }

    public function testSolarAndBatteryHaveNoPrime(): void
    {
        $quoter = new RenovationQuoter();

        $solar = $quoter->quote(Renovation::SolarPanels, self::barePassoire());
        $battery = $quoter->quote(Renovation::HomeBattery, self::barePassoire());

        self::assertNotNull($solar);
        self::assertNotNull($battery);
        self::assertSame(0, $solar->subsidy->cents);
        self::assertSame(0, $battery->subsidy->cents);
        self::assertSame(3.0, $solar->resultingHousehold->solarKwc);
        self::assertSame(5.0, $battery->resultingHousehold->batteryKwh);
    }

    public function testAlreadyDoneWorksAreNotQuoted(): void
    {
        $quoter = new RenovationQuoter();
        $equipped = new Household(3.0, 5.0, InsulationLevel::Original, HeatingSystem::HeatPump);

        self::assertNull($quoter->quote(Renovation::SolarPanels, $equipped));
        self::assertNull($quoter->quote(Renovation::HomeBattery, $equipped));
        self::assertNull($quoter->quote(Renovation::HeatPump, $equipped));
    }
}
