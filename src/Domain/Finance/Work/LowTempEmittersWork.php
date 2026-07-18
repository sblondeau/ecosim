<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\FinanceCalibration;
use App\Domain\Finance\Money;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;

/**
 * Low-temperature emitters (underfloor/oversized radiators): no effect on
 * their own, but they boost a heat pump's SCOP substantially — a work whose
 * payoff depends entirely on what heating system sits next to it.
 */
final readonly class LowTempEmittersWork implements RenovationDefinition
{
    private const string LABEL = 'Émetteurs basse température';

    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'low_temp_emitters';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Heating;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if ($household->lowTempEmitters) {
            return null;
        }

        return new RenovationOffer(
            title: self::LABEL,
            cost: Money::fromEuros($this->calibration->lowTempEmittersCost()->value),
            resultingHousehold: $household->withLowTempEmitters(true),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return HeatingSystem::HeatPump === $household->heatingSystem
            ? new RenovationAdvice(AdviceLevel::Info, 'Fait passer le SCOP de votre PAC de ~2,5 à ~4,3 : moins d\'électricité pour la même chaleur.')
            : new RenovationAdvice(AdviceLevel::Info, 'Utile surtout avec une pompe à chaleur (améliore fortement son rendement) ; sans effet sur une chaudière.');
    }

    public function isEnergyPerformanceWork(): bool
    {
        return true;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return $household->lowTempEmitters ? self::LABEL : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return $household->lowTempEmitters ? 'floor-heating' : null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/icons/low-temp-emitters.svg';
    }
}
