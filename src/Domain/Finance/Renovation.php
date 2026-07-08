<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * The renovation works a player can order (game-design §15 scope).
 *
 * Eligibility mirrors the real French schemes: the prime and the éco-PTZ
 * cover energy-performance renovation (insulation, heat pump) but NOT solar
 * panels or batteries — production equipment pays for itself through the
 * bills, renovation is what public money supports.
 */
enum Renovation: string
{
    /** Upgrade the insulation to the next tier. */
    case Insulation = 'insulation';
    case HeatPump = 'heat_pump';
    case SolarPanels = 'solar_panels';
    case HomeBattery = 'home_battery';

    /**
     * Covered by the income-based prime (MaPrimeRénov'-like)?
     */
    public function isSubsidised(): bool
    {
        return match ($this) {
            self::Insulation, self::HeatPump => true,
            self::SolarPanels, self::HomeBattery => false,
        };
    }

    /**
     * Financeable with the zero-interest loan (éco-PTZ-like)?
     */
    public function isLoanEligible(): bool
    {
        // Same perimeter as the prime: energy-performance works only.
        return $this->isSubsidised();
    }
}
