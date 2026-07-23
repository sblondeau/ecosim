<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Finance\RenovationCatalog;
use App\Domain\Finance\RenovationDelays;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class RenovationDelaysTest extends TestCase
{
    public function testEmergencyRepairIsFarFasterThanAHeatPumpWhichBeatsRooftopSolar(): void
    {
        $delays = new RenovationDelays();

        $repair = $delays->for('boiler_repair')->totalDays();
        $heatPump = $delays->for('heat_pump')->totalDays();
        $rooftopSolar = $delays->for('solar_panels')->totalDays();

        self::assertLessThan($heatPump, $repair, 'The emergency boiler repair is the fast exception (§ délais).');
        self::assertLessThan($rooftopSolar, $heatPump, 'Rooftop solar carries the longest chain (mairie + Enedis).');
    }

    public function testThePlugAndPlayKitSkipsTheInstallerLeadThatRooftopSolarCarries(): void
    {
        $delays = new RenovationDelays();

        self::assertLessThan(
            $delays->for('solar_panels')->leadDays,
            $delays->for('solar_kit')->leadDays,
            'The plug-and-play kit has no installer/admin lead — that is what makes it plug-and-play.',
        );
    }

    public function testTheEcoPtzFundsReleaseAddsWeeksMakingItUnusableForAnEmergency(): void
    {
        $delays = new RenovationDelays();

        self::assertGreaterThan(
            $delays->for('boiler_repair')->totalDays(),
            $delays->ptzFundsReleaseDays(),
            'Waiting for éco-PTZ funds takes longer than a whole emergency repair — hence cash for the panne.',
        );
    }

    public function testEveryCatalogueWorkResolvesToAPositiveDelay(): void
    {
        $delays = new RenovationDelays();

        foreach (new RenovationCatalog()->all() as $work) {
            $delay = $delays->for($work->slug());
            self::assertGreaterThan(0, $delay->totalDays(), sprintf('%s must have a chantier delay.', $work->slug()));
        }
    }
}
