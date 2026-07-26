<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Finance\RenovationCatalog;
use App\Domain\Finance\RenovationConcurrency;
use App\Domain\Finance\RenovationDefinition;
use PHPUnit\Framework\TestCase;

/**
 * The "one professional chantier at a time" rule (§ contrainte, levier ①):
 * works needing an RGE pro serialise; DIY gestes run in parallel; the
 * emergency repair is never blocked.
 */
final class RenovationConcurrencyTest extends TestCase
{
    private RenovationCatalog $catalog;
    private RenovationConcurrency $concurrency;

    protected function setUp(): void
    {
        $this->catalog = new RenovationCatalog();
        $this->concurrency = new RenovationConcurrency();
    }

    private function work(string $slug): RenovationDefinition
    {
        return $this->catalog->get($slug);
    }

    public function testRequiresProfessionalIsCarriedByEachWork(): void
    {
        self::assertTrue($this->work('heat_pump')->requiresProfessional());
        self::assertTrue($this->work('roof_insulation')->requiresProfessional());
        self::assertFalse($this->work('thermal_curtains')->requiresProfessional());
        self::assertFalse($this->work('draught_proofing')->requiresProfessional());
        self::assertFalse($this->work('solar_kit')->requiresProfessional());
    }

    public function testAProWorkIsBlockedWhileAnotherProChantierIsInFlight(): void
    {
        self::assertFalse($this->concurrency->allowsOrdering($this->work('heat_pump'), [$this->work('roof_insulation')]));
    }

    public function testDiyGestesRunInParallelWithAProChantier(): void
    {
        $inFlight = [$this->work('roof_insulation')];
        self::assertTrue($this->concurrency->allowsOrdering($this->work('thermal_curtains'), $inFlight));
        self::assertTrue($this->concurrency->allowsOrdering($this->work('draught_proofing'), $inFlight));
        self::assertTrue($this->concurrency->allowsOrdering($this->work('solar_kit'), $inFlight));
    }

    public function testTheEmergencyRepairIsNeverBlocked(): void
    {
        self::assertTrue($this->concurrency->allowsOrdering($this->work('boiler_repair'), [$this->work('roof_insulation')]));
    }

    public function testAProWorkIsAllowedWhenOnlyDiyGestesAreInFlight(): void
    {
        $inFlight = [$this->work('thermal_curtains'), $this->work('solar_kit')];
        self::assertTrue($this->concurrency->allowsOrdering($this->work('heat_pump'), $inFlight));
    }

    public function testGeneratorsStayMutuallyExclusiveUnderTheRule(): void
    {
        self::assertFalse($this->concurrency->allowsOrdering($this->work('pellet_boiler'), [$this->work('heat_pump')]));
    }
}
