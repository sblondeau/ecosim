<?php

declare(strict_types=1);

namespace App\Domain\Building;

use InvalidArgumentException;

/**
 * The full configuration of the player's house: production equipment, storage,
 * envelope and heating system.
 *
 * Everything here can change as the game is played (installing panels,
 * renovating, replacing the boiler are the game's decisions — game-design §8,
 * §18), so the household lives inside the game state, not the game config.
 * Immutable value object: a renovation produces a new Household.
 */
final readonly class Household
{
    public function __construct(
        /** Installed solar peak power, in kWc (0 = none). */
        public float $solarKwc,
        /** Battery usable capacity, in kWh (0 = none). */
        public float $batteryKwh,
        public EnvelopeState $envelope,
        public HeatingSystem $heatingSystem,
        /** The fuel-oil boiler died (scripted event) and delivers no heat until repaired or replaced. */
        public bool $boilerBroken = false,
        /** The thermostat target the player dials (°C). Default 19 (Code de l'énergie R241-26). */
        public float $heatingSetpointC = 19.0,
        /**
         * Low-temperature emitters (underfloor heating / oversized BT radiators,
         * ~35 °C water) instead of the original high-temperature cast-iron
         * radiators (~65 °C water). Only the heat pump's SCOP is sensitive to
         * this — a fuel-oil boiler burns the same regardless of emitter.
         */
        public bool $lowTempEmitters = false,
    ) {
        if ($solarKwc < 0.0) {
            throw new InvalidArgumentException("Solar power cannot be negative: {$solarKwc}.");
        }

        if ($batteryKwh < 0.0) {
            throw new InvalidArgumentException("Battery capacity cannot be negative: {$batteryKwh}.");
        }

        if ($boilerBroken && HeatingSystem::FuelOilBoiler !== $heatingSystem) {
            throw new InvalidArgumentException('Only the fuel-oil boiler can be broken.');
        }
    }

    public function withSolarKwc(float $solarKwc): self
    {
        return new self($solarKwc, $this->batteryKwh, $this->envelope, $this->heatingSystem, $this->boilerBroken, $this->heatingSetpointC, $this->lowTempEmitters);
    }

    public function withBatteryKwh(float $batteryKwh): self
    {
        return new self($this->solarKwc, $batteryKwh, $this->envelope, $this->heatingSystem, $this->boilerBroken, $this->heatingSetpointC, $this->lowTempEmitters);
    }

    public function withEnvelope(EnvelopeState $envelope): self
    {
        return new self($this->solarKwc, $this->batteryKwh, $envelope, $this->heatingSystem, $this->boilerBroken, $this->heatingSetpointC, $this->lowTempEmitters);
    }

    /**
     * Replacing the heating system removes the old boiler — broken or not.
     * The emitters stay in place: they're plumbing, not the generator.
     */
    public function withHeatingSystem(HeatingSystem $heatingSystem): self
    {
        return new self($this->solarKwc, $this->batteryKwh, $this->envelope, $heatingSystem, boilerBroken: false, heatingSetpointC: $this->heatingSetpointC, lowTempEmitters: $this->lowTempEmitters);
    }

    public function withBoilerBroken(bool $boilerBroken): self
    {
        return new self($this->solarKwc, $this->batteryKwh, $this->envelope, $this->heatingSystem, $boilerBroken, $this->heatingSetpointC, $this->lowTempEmitters);
    }

    public function withHeatingSetpointC(float $heatingSetpointC): self
    {
        return new self($this->solarKwc, $this->batteryKwh, $this->envelope, $this->heatingSystem, $this->boilerBroken, $heatingSetpointC, $this->lowTempEmitters);
    }

    public function withLowTempEmitters(bool $lowTempEmitters): self
    {
        return new self($this->solarKwc, $this->batteryKwh, $this->envelope, $this->heatingSystem, $this->boilerBroken, $this->heatingSetpointC, $lowTempEmitters);
    }
}
