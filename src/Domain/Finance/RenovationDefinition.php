<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\Household;

/**
 * One work of the renovation tree: everything the game needs to know about it,
 * in a single file. Adding a work = adding an implementation.
 *
 * This replaces the `Renovation` enum, whose exhaustive `match` forced three
 * classes to be reopened for every new work while covering none of the
 * template-side registrations that actually got forgotten (game-design §15,
 * arbre travaux). The interface is the tighter net: you cannot implement it
 * without answering all eight questions.
 */
interface RenovationDefinition
{
    /** Stable identity: form value, action parameter, array key. Unique across the catalogue. */
    public function slug(): string;

    /** Which drawer offers this work. */
    public function slot(): SceneSlot;

    /**
     * The offer for this house, or null when the work does not apply — already
     * done, prerequisite missing, top tier reached. The UI simply hides it.
     */
    public function offerFor(Household $household): ?RenovationOffer;

    /**
     * Non-prescriptive advice given the current house (game-design: pédagogie
     * par les systèmes, pas de dirigisme). Never a "do this next".
     */
    public function adviceFor(Household $household): RenovationAdvice;

    /**
     * Does this work fall inside the perimeter of the energy-performance aid
     * schemes? Drives BOTH the prime and éco-PTZ eligibility — they share the
     * same real-world perimeter, so this names the underlying eligibility
     * rather than either of its two consequences.
     */
    public function qualifiesForEnergyAid(): bool;

    /**
     * The "done" chip for this house ("Batterie 10 kWh", "Murs — ITE"), or
     * null when the work has not been carried out.
     *
     * This is the STATE phrase the drawer shows once the work is done — NOT
     * the same string as {@see self::offerFor()}'s offer title (a
     * call-to-action). Several works share wording between the two by
     * coincidence (an enum label used verbatim in both places); others do
     * not (e.g. the offer "Isolation des combles" vs. the done chip "Combles
     * isolés") — never assume the two match without checking the template.
     *
     * Independent from {@see self::offerFor()}: double glazing is BOTH done
     * (a chip) and upgradeable to triple (an offer). Both answer non-null.
     */
    public function doneLabelFor(Household $household): ?string;

    /**
     * The semantic scene layer this work activates for this house, or null
     * when it has no visual (game-design §17: a key, never geometry — no
     * coordinates, colours or shapes here).
     */
    public function sceneLayerFor(Household $household): ?string;

    /** Template path of the drawer icon — the scene's own asset (one drawing per equipment). */
    public function iconAsset(): string;
}
