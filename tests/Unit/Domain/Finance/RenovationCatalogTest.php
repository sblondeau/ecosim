<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Building\Household;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationCatalog;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RenovationCatalogTest extends TestCase
{
    public function testResolvesAWorkBySlug(): void
    {
        $catalog = new RenovationCatalog([new FakeWork('alpha', SceneSlot::Roof)]);

        self::assertSame('alpha', $catalog->get('alpha')->slug());
    }

    public function testTryGetReturnsNullForAnUnknownSlug(): void
    {
        $catalog = new RenovationCatalog([new FakeWork('alpha', SceneSlot::Roof)]);

        self::assertNull($catalog->tryGet('nope'));
    }

    public function testGetThrowsForAnUnknownSlug(): void
    {
        $catalog = new RenovationCatalog([new FakeWork('alpha', SceneSlot::Roof)]);

        $this->expectException(InvalidArgumentException::class);
        $catalog->get('nope');
    }

    /**
     * A duplicate slug is a programming mistake: two works answering to the
     * same form value would silently shadow each other.
     */
    public function testRejectsDuplicateSlugs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RenovationCatalog([
            new FakeWork('alpha', SceneSlot::Roof),
            new FakeWork('alpha', SceneSlot::Walls),
        ]);
    }

    /**
     * Declaration order IS display order: `worksOfSlot` used to encode it in
     * the template (boiler repair before the heat pump, so a breakdown offers
     * the cheap fix first). The catalogue must not lose it.
     */
    public function testForSlotKeepsDeclarationOrder(): void
    {
        $catalog = new RenovationCatalog([
            new FakeWork('first', SceneSlot::Heating),
            new FakeWork('elsewhere', SceneSlot::Roof),
            new FakeWork('second', SceneSlot::Heating),
        ]);

        $slugs = array_map(
            static fn (RenovationDefinition $w): string => $w->slug(),
            $catalog->forSlot(SceneSlot::Heating),
        );

        self::assertSame(['first', 'second'], $slugs);
    }

    /**
     * The default catalogue, filled up drawer by drawer across tasks 3-5: all
     * fifteen works, in offer/display order — the order `worksOfSlot` used to
     * encode in the template.
     */
    public function testTheDefaultCatalogueListsAllFifteenWorksInOfferOrder(): void
    {
        $slugs = array_map(
            static fn (RenovationDefinition $w): string => $w->slug(),
            new RenovationCatalog()->all(),
        );

        self::assertSame([
            'boiler_repair', 'heat_pump', 'pellet_boiler', 'low_temp_emitters', 'water_heater_thermo',
            'roof_insulation', 'wall_insulation_interior', 'wall_insulation_exterior', 'glazing', 'ventilation_double_flow',
            'solar_panels', 'solar_kit', 'home_battery',
            'draught_proofing', 'thermal_curtains',
        ], $slugs);
    }

    /**
     * The catalogue is now the only source of truth for the works (task 6
     * dropped the `Renovation` enum, whose exhaustive matches used to catch
     * this at the type level) — this count (and the order test above) is what
     * now catches a work whose class exists but was never registered in
     * `defaultWorks()`.
     */
    public function testDefaultCatalogueExposesEveryWorkExactlyOnce(): void
    {
        $catalog = new RenovationCatalog();

        $slugs = array_map(
            static fn (RenovationDefinition $w): string => $w->slug(),
            $catalog->all(),
        );

        self::assertCount(15, $slugs);
        self::assertSame($slugs, array_unique($slugs));
    }
}

/** A minimal definition, so the catalogue is tested without any real work. */
final readonly class FakeWork implements RenovationDefinition
{
    public function __construct(
        private string $slug,
        private SceneSlot $slot,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function slot(): SceneSlot
    {
        return $this->slot;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        return null;
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(\App\Domain\Finance\AdviceLevel::Info, 'test');
    }

    public function isEnergyPerformanceWork(): bool
    {
        return false;
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
