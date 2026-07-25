<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Finance\ExclusivityGroup;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\RenovationCatalog;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * The chantier delay and the exclusivity group are now carried by each work
 * ({@see \App\Domain\Finance\RenovationDefinition}), not a central match — so
 * these assert them through the catalogue. The éco-PTZ funds-release delay,
 * being cross-cutting (not per-work), lives on {@see FinanceCalibration}.
 */
final class RenovationDelaysTest extends TestCase
{
    private RenovationCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new RenovationCatalog();
    }

    private function totalDays(string $slug): int
    {
        return $this->catalog->get($slug)->delay()->totalDays();
    }

    public function testEmergencyRepairIsFarFasterThanAHeatPumpWhichBeatsRooftopSolar(): void
    {
        self::assertLessThan($this->totalDays('heat_pump'), $this->totalDays('boiler_repair'), 'The emergency boiler repair is the fast exception (§ délais).');
        self::assertLessThan($this->totalDays('solar_panels'), $this->totalDays('heat_pump'), 'Rooftop solar carries the longest chain (mairie + Enedis).');
    }

    public function testThePlugAndPlayKitSkipsTheInstallerLeadThatRooftopSolarCarries(): void
    {
        self::assertLessThan(
            $this->catalog->get('solar_panels')->delay()->leadDays,
            $this->catalog->get('solar_kit')->delay()->leadDays,
            'The plug-and-play kit has no installer/admin lead — that is what makes it plug-and-play.',
        );
    }

    public function testTheEcoPtzFundsReleaseAddsWeeksMakingItUnusableForAnEmergency(): void
    {
        self::assertGreaterThan(
            $this->totalDays('boiler_repair'),
            (int) new FinanceCalibration()->ecoPtzFundsReleaseDays()->value,
            'Waiting for éco-PTZ funds takes longer than a whole emergency repair — hence cash for the panne.',
        );
    }

    public function testEveryCatalogueWorkResolvesToAPositiveDelay(): void
    {
        foreach ($this->catalog->all() as $work) {
            self::assertGreaterThan(0, $work->delay()->totalDays(), sprintf('%s must have a chantier delay.', $work->slug()));
        }
    }

    public function testOnlyHeatingGeneratorsShareTheExclusivityGroup(): void
    {
        self::assertSame(ExclusivityGroup::HeatingGenerator, $this->catalog->get('heat_pump')->exclusivityGroup());
        self::assertSame(ExclusivityGroup::HeatingGenerator, $this->catalog->get('pellet_boiler')->exclusivityGroup());
        // The emergency repair must never be blocked by a generator on order.
        self::assertNull($this->catalog->get('boiler_repair')->exclusivityGroup());
        self::assertNull($this->catalog->get('roof_insulation')->exclusivityGroup());
    }
}
