<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * The house envelope decomposed into the surfaces the player can renovate
 * (game-design tech tree, Tranche 1): roof/attic, walls, glazing, ventilation
 * (Tranche 5), plus two cheap "gesture" fields — draught-proofing and thermal
 * curtains (Tranche 6) — deliberately small levers, not renovation works.
 * Immutable VO living inside {@see Household}; a renovation produces a new
 * envelope.
 */
final readonly class EnvelopeState
{
    public function __construct(
        public bool $roofInsulated,
        public WallInsulation $walls,
        public Glazing $glazing,
        public Ventilation $ventilation = Ventilation::None,
        public bool $draughtProofed = false,
        public bool $thermalCurtains = false,
    ) {
    }

    public function withRoofInsulated(bool $roofInsulated): self
    {
        return new self($roofInsulated, $this->walls, $this->glazing, $this->ventilation, $this->draughtProofed, $this->thermalCurtains);
    }

    public function withWalls(WallInsulation $walls): self
    {
        return new self($this->roofInsulated, $walls, $this->glazing, $this->ventilation, $this->draughtProofed, $this->thermalCurtains);
    }

    public function withGlazing(Glazing $glazing): self
    {
        return new self($this->roofInsulated, $this->walls, $glazing, $this->ventilation, $this->draughtProofed, $this->thermalCurtains);
    }

    public function withVentilation(Ventilation $ventilation): self
    {
        return new self($this->roofInsulated, $this->walls, $this->glazing, $ventilation, $this->draughtProofed, $this->thermalCurtains);
    }

    public function withDraughtProofed(bool $draughtProofed): self
    {
        return new self($this->roofInsulated, $this->walls, $this->glazing, $this->ventilation, $draughtProofed, $this->thermalCurtains);
    }

    public function withThermalCurtains(bool $thermalCurtains): self
    {
        return new self($this->roofInsulated, $this->walls, $this->glazing, $this->ventilation, $this->draughtProofed, $thermalCurtains);
    }
}
