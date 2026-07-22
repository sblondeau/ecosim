<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\Household;
use App\Domain\Energy\EnergyCalibration;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;

use function sprintf;

/**
 * Home battery (the single 5 kWh catalogue model). A battery only stores
 * solar production (the MVP's sole source) — offering it before panels are
 * installed would let it sit unused, so it additionally requires solar power
 * already on the roof (arbre travaux, Tranche 5).
 */
final readonly class HomeBatteryWork implements RenovationDefinition
{
    private const string TITLE_FORMAT = 'Batterie domestique %.0f kWh';

    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
        private EnergyCalibration $energy = new EnergyCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'home_battery';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Garage;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if ($household->batteryKwh > 0.0 || $household->solarKwc <= 0.0) {
            return null;
        }

        $kwh = $this->energy->defaultBatteryCapacityKwh()->value;

        return new RenovationOffer(
            title: sprintf(self::TITLE_FORMAT, $kwh),
            cost: Money::fromEuros($this->calibration->batteryInstallCost()->value),
            resultingHousehold: $household->withBatteryKwh($kwh),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(
            AdviceLevel::Info,
            'Stocke le surplus solaire pour le consommer le soir.',
        );
    }

    public function qualifiesForEnergyAid(): bool
    {
        return false;
    }

    public function doneLabelFor(Household $household): ?string
    {
        // Deliberately NOT self::TITLE_FORMAT: the drawer's "done" chip reads
        // "Batterie N kWh" (a state), not the offer's CTA title.
        // Note: sprintf('%.0f') diverges from the old template's number_format(0, ',', ' ') at ≥1000 kWh,
        // but this is unreachable at the current 5 kWh calibration — whoever wires this output should align formats.
        return $household->batteryKwh > 0.0 ? sprintf('Batterie %.0f kWh', $household->batteryKwh) : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        // Equipment: drawn as a <twig:scene:*> component selected from the
        // household's equipment state, not via an envelope house--* gate.
        return null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/battery.svg';
    }
}
