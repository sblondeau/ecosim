<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Finance\Work\BoilerRepairWork;
use App\Domain\Finance\Work\GlazingWork;
use App\Domain\Finance\Work\HeatPumpWork;
use App\Domain\Finance\Work\LowTempEmittersWork;
use App\Domain\Finance\Work\PelletBoilerWork;
use App\Domain\Finance\Work\RoofInsulationWork;
use App\Domain\Finance\Work\VentilationDoubleFlowWork;
use App\Domain\Finance\Work\WallInsulationExteriorWork;
use App\Domain\Finance\Work\WallInsulationInteriorWork;
use App\Domain\Finance\Work\WaterHeaterThermoWork;

use function array_values;

use InvalidArgumentException;

use function sprintf;

/**
 * The renovation tree: every work, in display order.
 *
 * The order of {@see self::defaultWorks()} IS the order the quotes appear in a
 * drawer — the template's old `worksOfSlot` array encoded it implicitly (boiler
 * repair before the heat pump, so a breakdown offers the cheap fix first) and a
 * flat catalogue would have lost it silently.
 *
 * Built from an explicit list rather than a Symfony tagged iterator: this class
 * lives in the domain, which may not depend on the container (CLAUDE.md §3).
 */
final readonly class RenovationCatalog
{
    /** @var list<RenovationDefinition> */
    private array $works;

    /** @var array<string, RenovationDefinition> */
    private array $bySlug;

    /** @param list<RenovationDefinition>|null $works */
    public function __construct(?array $works = null)
    {
        $works ??= self::defaultWorks();

        $bySlug = [];
        foreach ($works as $work) {
            $slug = $work->slug();
            if (isset($bySlug[$slug])) {
                throw new InvalidArgumentException(sprintf('Duplicate renovation slug: "%s".', $slug));
            }
            $bySlug[$slug] = $work;
        }

        $this->works = array_values($works);
        $this->bySlug = $bySlug;
    }

    public function tryGet(string $slug): ?RenovationDefinition
    {
        return $this->bySlug[$slug] ?? null;
    }

    /** @throws InvalidArgumentException when no work answers to that slug */
    public function get(string $slug): RenovationDefinition
    {
        return $this->tryGet($slug)
            ?? throw new InvalidArgumentException(sprintf('Unknown renovation: "%s".', $slug));
    }

    /** @return list<RenovationDefinition> */
    public function all(): array
    {
        return $this->works;
    }

    /** @return list<RenovationDefinition> in declaration order */
    public function forSlot(SceneSlot $slot): array
    {
        $matching = [];
        foreach ($this->works as $work) {
            if ($slot === $work->slot()) {
                $matching[] = $work;
            }
        }

        return $matching;
    }

    /**
     * The tree, in display order. ADDING A WORK = ADDING ONE LINE HERE.
     *
     * @return list<RenovationDefinition>
     */
    private static function defaultWorks(): array
    {
        return [
            // Heating — the repair comes first: on a breakdown, the cheap fix
            // must be the first thing offered.
            new BoilerRepairWork(),
            new HeatPumpWork(),
            new PelletBoilerWork(),
            new LowTempEmittersWork(),
            new WaterHeaterThermoWork(),
            // Envelope
            new RoofInsulationWork(),
            new WallInsulationInteriorWork(),
            new WallInsulationExteriorWork(),
            new GlazingWork(),
            new VentilationDoubleFlowWork(),
        ];
    }
}
