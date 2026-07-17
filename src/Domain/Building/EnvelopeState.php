<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * The house envelope decomposed into the surfaces the player can renovate
 * (game-design tech tree, Tranche 1): roof/attic, walls, glazing, ventilation
 * (Tranche 5). Immutable VO living inside {@see Household}; a renovation
 * produces a new envelope.
 */
final readonly class EnvelopeState
{
    public function __construct(
        public bool $roofInsulated,
        public WallInsulation $walls,
        public Glazing $glazing,
        public Ventilation $ventilation = Ventilation::None,
    ) {
    }

    public function withRoofInsulated(bool $roofInsulated): self
    {
        return new self($roofInsulated, $this->walls, $this->glazing, $this->ventilation);
    }

    public function withWalls(WallInsulation $walls): self
    {
        return new self($this->roofInsulated, $walls, $this->glazing, $this->ventilation);
    }

    public function withGlazing(Glazing $glazing): self
    {
        return new self($this->roofInsulated, $this->walls, $glazing, $this->ventilation);
    }

    public function withVentilation(Ventilation $ventilation): self
    {
        return new self($this->roofInsulated, $this->walls, $this->glazing, $ventilation);
    }
}
