# Tranche 1 — Enveloppe par surfaces + DPE recalculé — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer l'isolation monolithique à 3 paliers (`InsulationLevel`) par une enveloppe décomposée en surfaces (combles / murs ITI|ITE / vitrage), agrégée en un facteur de déperdition continu ; le DPE en découle sans changement (il dérive déjà de l'énergie réelle).

**Architecture:** Un VO `EnvelopeState` (combles bool, murs enum, vitrage enum) porté par `Household`. `BuildingCalibration` expose un retrait de déperdition sourcé par surface et agrège `envelopeLossFactor = 1 − Σ retraits` (plancher 0,15) + une pénalité paroi froide par surfaces. Les calculateurs (`HeatingNeed`, `ThermalComfort`, `EmergencyHeating`) consomment `EnvelopeState`. Les travaux d'isolation deviennent 4 nœuds (combles, murs ITI, murs ITE, vitrage). Le DPE (`DpeCertifier`) est **inchangé**.

**Tech Stack:** PHP 8.4, domaine PHP pur (0 framework), PHPUnit 13, Doctrine (entités anémiques), Symfony UX Twig/LiveComponents.

## Global Constraints

- `declare(strict_types=1)` partout ; classes `final` par défaut ; VOs `final readonly`. (CLAUDE.md §7)
- `src/Domain/**` = PHP pur, **interdit** d'importer Symfony/Doctrine/vendor. (§3)
- Tout coefficient chiffré = un `App\Domain\Calibration\Coefficient` (valeur + min + max + source + reviewedOn), jamais un nombre inline. (§6, §13)
- Identifiants/commentaires en anglais ; libellés joueur en français. (§7)
- Tests unitaires purs pour le domaine (`tests/Unit/...`, miroir de `src/`), sans DB ni kernel, valeurs **exactes** (déterministe). Chaque brique arrive avec ses tests dans le même commit. (§5)
- `make qa` (cs + stan niveau 8 + twig + test) vert avant chaque commit ; jamais de `@phpstan-ignore`/baseline. (§4)
- Commits `type(scope): subject` en anglais, terminés par `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Branche : `docs/arbre-travaux-spec` (décidé).
- Coefficients de retrait validés (spec + brainstorming) : combles −0,24 ; murs ITI −0,16 ; murs ITE −0,18 ; vitrage double −0,065 ; vitrage triple −0,08. Facteur planché à 0,15. Départ (rien fait) = 1,0 → DPE F/G préservé. Plafond Tranche 1 ≈ 0,50 (assumé).

---

## File Structure

**Créés :**
- `src/Domain/Building/WallInsulation.php` — enum `None|Interior|Exterior` (+ `label()`).
- `src/Domain/Building/Glazing.php` — enum `Single|Double|Triple` (+ `label()`).
- `src/Domain/Building/EnvelopeState.php` — VO immuable (combles/murs/vitrage) + `with*`.
- Tests miroirs : `tests/Unit/Domain/Building/EnvelopeStateTest.php`.

**Modifiés (cœur) :**
- `src/Domain/Building/BuildingCalibration.php` — retire `insulationFactor`/`coldWallPenaltyFactor(InsulationLevel)`, ajoute retraits par surface + `envelopeLossFactor(EnvelopeState)` + `coldWallPenaltyFactor(EnvelopeState)`.
- `src/Domain/Building/Household.php` — `insulation: InsulationLevel` → `envelope: EnvelopeState`.
- `src/Domain/Building/HeatingNeedCalculator.php`, `ThermalComfortCalculator.php`, `EmergencyHeatingCalculator.php` — signatures `EnvelopeState`.
- `src/Domain/Simulation/SimulationEngine.php` — passe `$household->envelope`.
- `src/Domain/Scenario/PrimoAccedantScenario.php` — état initial.
- `src/Application/SessionGameStore.php` — sérialisation.
- `src/Command/SimulateDemoCommand.php` — usage.
- `src/Application/GameViewFactory.php` / `GameView.php` — `insulationLabel` + `insulationTier`.
- `src/Domain/Building/InsulationLevel.php` — **supprimé**.

**Modifiés (travaux + IHM) :**
- `src/Domain/Finance/Renovation.php` — `Insulation` → `RoofInsulation`, `WallInsulationInterior`, `WallInsulationExterior`, `Glazing`.
- `src/Domain/Finance/FinanceCalibration.php` — coûts par surface.
- `src/Domain/Finance/RenovationQuoter.php` — devis par surface.
- `templates/game/panel/_slot.html.twig` — `worksOfSlot` du mur.
- `templates/components/scene/HouseShell.html.twig` (via `insulationTier`) — inchangé de signature, alimenté par le nouvel agrégat.

---

## Task 1: Enums de surface + VO `EnvelopeState`

**Files:**
- Create: `src/Domain/Building/WallInsulation.php`
- Create: `src/Domain/Building/Glazing.php`
- Create: `src/Domain/Building/EnvelopeState.php`
- Test: `tests/Unit/Domain/Building/EnvelopeStateTest.php`

**Interfaces:**
- Consumes: rien.
- Produces:
  - `enum WallInsulation: string { case None='none'; case Interior='interior'; case Exterior='exterior'; public function label(): string; }`
  - `enum Glazing: string { case Single='single'; case Double='double'; case Triple='triple'; public function label(): string; }`
  - `final readonly class EnvelopeState { public function __construct(public bool $roofInsulated, public WallInsulation $walls, public Glazing $glazing) {} public function withRoofInsulated(bool): self; public function withWalls(WallInsulation): self; public function withGlazing(Glazing): self; }`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\WallInsulation;
use PHPUnit\Framework\TestCase;

final class EnvelopeStateTest extends TestCase
{
    public function testOriginalHouseIsUninsulatedSingleGlazed(): void
    {
        $envelope = new EnvelopeState(false, WallInsulation::None, Glazing::Single);

        self::assertFalse($envelope->roofInsulated);
        self::assertSame(WallInsulation::None, $envelope->walls);
        self::assertSame(Glazing::Single, $envelope->glazing);
    }

    public function testWithersReturnNewImmutableState(): void
    {
        $original = new EnvelopeState(false, WallInsulation::None, Glazing::Single);

        $insulated = $original->withRoofInsulated(true)
            ->withWalls(WallInsulation::Exterior)
            ->withGlazing(Glazing::Triple);

        self::assertFalse($original->roofInsulated, 'original untouched');
        self::assertSame(WallInsulation::None, $original->walls);
        self::assertTrue($insulated->roofInsulated);
        self::assertSame(WallInsulation::Exterior, $insulated->walls);
        self::assertSame(Glazing::Triple, $insulated->glazing);
    }

    public function testLabelsAreFrench(): void
    {
        self::assertSame('Intérieure (ITI)', WallInsulation::Interior->label());
        self::assertSame('Extérieure (ITE)', WallInsulation::Exterior->label());
        self::assertSame('Double vitrage', Glazing::Double->label());
        self::assertSame('Triple vitrage', Glazing::Triple->label());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Domain/Building/EnvelopeStateTest.php`
Expected: FAIL (classes `WallInsulation`, `Glazing`, `EnvelopeState` not found).

- [ ] **Step 3: Create `WallInsulation`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Building;

/** How the exterior walls are insulated (Tranche 1 tech tree). */
enum WallInsulation: string
{
    case None = 'none';
    /** Intérieure (ITI): cheaper, eats living space, residual thermal bridges. */
    case Interior = 'interior';
    /** Extérieure (ITE): dearer, no thermal bridge, keeps living space. */
    case Exterior = 'exterior';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Non isolés',
            self::Interior => 'Intérieure (ITI)',
            self::Exterior => 'Extérieure (ITE)',
        };
    }
}
```

- [ ] **Step 4: Create `Glazing`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Building;

/** Window glazing tier (Tranche 1 tech tree). */
enum Glazing: string
{
    case Single = 'single';
    case Double = 'double';
    case Triple = 'triple';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Simple vitrage',
            self::Double => 'Double vitrage',
            self::Triple => 'Triple vitrage',
        };
    }
}
```

- [ ] **Step 5: Create `EnvelopeState`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Building;

/**
 * The house envelope decomposed into the surfaces the player can renovate
 * (game-design tech tree, Tranche 1): roof/attic, walls, glazing. Immutable
 * VO living inside {@see Household}; a renovation produces a new envelope.
 */
final readonly class EnvelopeState
{
    public function __construct(
        public bool $roofInsulated,
        public WallInsulation $walls,
        public Glazing $glazing,
    ) {
    }

    public function withRoofInsulated(bool $roofInsulated): self
    {
        return new self($roofInsulated, $this->walls, $this->glazing);
    }

    public function withWalls(WallInsulation $walls): self
    {
        return new self($this->roofInsulated, $walls, $this->glazing);
    }

    public function withGlazing(Glazing $glazing): self
    {
        return new self($this->roofInsulated, $this->walls, $glazing);
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Domain/Building/EnvelopeStateTest.php`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add src/Domain/Building/WallInsulation.php src/Domain/Building/Glazing.php src/Domain/Building/EnvelopeState.php tests/Unit/Domain/Building/EnvelopeStateTest.php
git commit -m "feat(building): EnvelopeState VO + wall/glazing enums (arbre travaux T1)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: Cœur — calibration par surfaces + swap `Household` + calculateurs

> **Note d'exécution :** tâche **atomique par nécessité**. Remplacer `Household::insulation` par `EnvelopeState` casse au compile-time tous les consommateurs (`HeatingNeed`, `ThermalComfort`, `EmergencyHeating`, `SimulationEngine`, `PrimoAccedantScenario`, `SessionGameStore`, `SimulateDemoCommand`, `GameViewFactory`, `RenovationQuoter`). Ils sont donc corrigés **dans le même commit**. Les steps restent fins (TDD par calculateur) mais commitent ensemble. Après cette tâche : le jeu tourne, l'enveloppe par surfaces pilote déperdition/confort/DPE, mais **aucun travail d'isolation n'est encore disponible** (Task 4).

**Files:**
- Modify: `src/Domain/Building/BuildingCalibration.php` (remplace `insulationFactor` + `coldWallPenaltyFactor(InsulationLevel)`, l.148-212)
- Modify: `src/Domain/Building/Household.php` (champ `insulation` → `envelope`)
- Modify: `src/Domain/Building/HeatingNeedCalculator.php:35`
- Modify: `src/Domain/Building/ThermalComfortCalculator.php:40,58,77`
- Modify: `src/Domain/Building/EmergencyHeatingCalculator.php:36`
- Modify: `src/Domain/Simulation/SimulationEngine.php:82,85,95,96`
- Modify: `src/Domain/Scenario/PrimoAccedantScenario.php:56-64`
- Modify: `src/Application/SessionGameStore.php:106-110,161-164`
- Modify: `src/Command/SimulateDemoCommand.php` (usage `insulation`)
- Modify: `src/Application/GameViewFactory.php:150,343-348`
- Modify: `src/Domain/Finance/RenovationQuoter.php:57-84` (neutralise `insulationQuote` → `return null` temporaire)
- Delete: `src/Domain/Building/InsulationLevel.php`
- Tests: `tests/Unit/Domain/Building/BuildingCalibrationTest.php` (créer si absent), `HeatingNeedCalculatorTest.php`, `ThermalComfortCalculatorTest.php`, `EmergencyHeatingCalculatorTest.php`, `HouseholdTest.php`, `tests/Unit/Domain/Scenario/PrimoAccedantScenarioTest.php`, `tests/Unit/Application/SessionGameStoreTest.php`, `RenovationHandlerTest.php`, `RenovationQuoterTest.php`, `SimulationEngineTest.php`, `GameViewFactoryTest.php`, `AnnualOutcomeEstimatorTest.php`

**Interfaces:**
- Consumes (Task 1): `EnvelopeState`, `WallInsulation`, `Glazing`.
- Produces:
  - `BuildingCalibration::envelopeLossFactor(EnvelopeState $e): float` (1 − Σ retraits, planché 0,15).
  - `BuildingCalibration::coldWallPenaltyFactor(EnvelopeState $e): float` (0,15 − contributions, planché 0,02).
  - `Household->envelope: EnvelopeState` (+ `withEnvelope(EnvelopeState): self`).
  - `HeatingNeedCalculator::dailyNeedKwh(EnvelopeState $envelope, float $outdoorC, ?float $setpointC = null): float`
  - `ThermalComfortCalculator::comfortFor(EnvelopeState $envelope, float $outdoorC, ?float $setpointC = null): ThermalComfort`
  - `ThermalComfortCalculator::unheatedComfortFor(EnvelopeState $envelope, float $outdoorC, float $internalGainsKwh): ThermalComfort`
  - `EmergencyHeatingCalculator::consumptionFor(EnvelopeState $envelope, float $outdoorC, float $internalGainsKwh): HeatingConsumption`

- [ ] **Step 1: Write failing calibration tests**

Créer/compléter `tests/Unit/Domain/Building/BuildingCalibrationTest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Building;

use App\Domain\Building\BuildingCalibration;
use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\WallInsulation;
use PHPUnit\Framework\TestCase;

final class BuildingCalibrationTest extends TestCase
{
    private BuildingCalibration $calibration;

    protected function setUp(): void
    {
        $this->calibration = new BuildingCalibration();
    }

    public function testOriginalEnvelopeKeepsFullLoss(): void
    {
        $original = new EnvelopeState(false, WallInsulation::None, Glazing::Single);

        self::assertSame(1.0, $this->calibration->envelopeLossFactor($original));
    }

    public function testEachSurfaceRemovesItsSourcedShare(): void
    {
        $roofOnly = new EnvelopeState(true, WallInsulation::None, Glazing::Single);
        self::assertEqualsWithDelta(0.76, $this->calibration->envelopeLossFactor($roofOnly), 1e-9);

        $roofItiDouble = new EnvelopeState(true, WallInsulation::Interior, Glazing::Double);
        self::assertEqualsWithDelta(0.535, $this->calibration->envelopeLossFactor($roofItiDouble), 1e-9);
    }

    public function testCeilingIsAboutHalfWithoutVmc(): void
    {
        $best = new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple);
        self::assertEqualsWithDelta(0.50, $this->calibration->envelopeLossFactor($best), 1e-9);
    }

    public function testColdWallPenaltyDropsWithWallsAndGlazing(): void
    {
        $original = new EnvelopeState(false, WallInsulation::None, Glazing::Single);
        self::assertEqualsWithDelta(0.15, $this->calibration->coldWallPenaltyFactor($original), 1e-9);

        $wallsOnly = new EnvelopeState(false, WallInsulation::Interior, Glazing::Single);
        self::assertEqualsWithDelta(0.07, $this->calibration->coldWallPenaltyFactor($wallsOnly), 1e-9);

        $best = new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple);
        self::assertEqualsWithDelta(0.02, $this->calibration->coldWallPenaltyFactor($best), 1e-9); // planché
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Domain/Building/BuildingCalibrationTest.php`
Expected: FAIL (`envelopeLossFactor` not defined).

- [ ] **Step 3: Replace insulation coefficients in `BuildingCalibration`**

Retirer `insulationFactor(InsulationLevel)` et `coldWallPenaltyFactor(InsulationLevel)` (l.142-212). Ajouter (imports : `use App\Domain\Building\EnvelopeState;` inutile — même namespace ; garder `use function max;`) :

```php
    // --- Enveloppe par surfaces (Tranche 1) : chaque surface traitée retire une
    // fraction de la déperdition TOTALE (part ADEME du poste × réduction obtenue). ---

    /** Combles isolés : toiture ~28 % des pertes × réduction ~85 %. */
    public function roofInsulationLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.24, unit: 'fraction', min: 0.20, max: 0.28, source: 'ADEME : toiture ~25-30 % des déperditions × gain isolation combles ~80-90 %', reviewedOn: '2026-07-16');
    }

    /** Murs ITI : murs ~23 % des pertes × réduction ~70 % (ponts thermiques résiduels). */
    public function wallInteriorLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.16, unit: 'fraction', min: 0.13, max: 0.19, source: 'ADEME : murs ~20-25 % des déperditions × gain ITI ~65-75 %', reviewedOn: '2026-07-16');
    }

    /** Murs ITE : idem mais réduction ~80 % (pas de pont thermique). */
    public function wallExteriorLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.18, unit: 'fraction', min: 0.15, max: 0.21, source: 'ADEME : murs ~20-25 % des déperditions × gain ITE ~75-85 % (sans pont thermique)', reviewedOn: '2026-07-16');
    }

    /** Double vitrage : fenêtres ~13 % des pertes × réduction ~50 % vs simple. */
    public function doubleGlazingLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.065, unit: 'fraction', min: 0.05, max: 0.08, source: 'ADEME : fenêtres ~10-15 % des déperditions × gain double vitrage ~50 %', reviewedOn: '2026-07-16');
    }

    /** Triple vitrage : réduction ~62 % (rendement décroissant vs double). */
    public function tripleGlazingLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.08, unit: 'fraction', min: 0.06, max: 0.10, source: 'ADEME : fenêtres ~10-15 % × gain triple vitrage ~60 % (rendement décroissant)', reviewedOn: '2026-07-16');
    }

    /** Plancher du facteur de déperdition (au-delà, l'enveloppe seule ne descend pas — VMC/plancher/étanchéité, phases suivantes). */
    public function envelopeLossFloor(): Coefficient
    {
        return new Coefficient(value: 0.15, unit: 'fraction', min: 0.10, max: 0.20, source: 'Calibration de jeu : plancher physique, l\'enveloppe seule ne fait pas un BBC (résiduel ventilation/plancher/ponts)', reviewedOn: '2026-07-16');
    }

    /**
     * Fraction de la déperdition d'origine qui subsiste, agrégée depuis les
     * surfaces traitées. 1,0 = maison d'origine (référence, DPE inchangé).
     */
    public function envelopeLossFactor(EnvelopeState $envelope): float
    {
        $removed = 0.0;

        if ($envelope->roofInsulated) {
            $removed += $this->roofInsulationLossReduction()->value;
        }

        $removed += match ($envelope->walls) {
            WallInsulation::None => 0.0,
            WallInsulation::Interior => $this->wallInteriorLossReduction()->value,
            WallInsulation::Exterior => $this->wallExteriorLossReduction()->value,
        };

        $removed += match ($envelope->glazing) {
            Glazing::Single => 0.0,
            Glazing::Double => $this->doubleGlazingLossReduction()->value,
            Glazing::Triple => $this->tripleGlazingLossReduction()->value,
        };

        return max($this->envelopeLossFloor()->value, 1.0 - $removed);
    }

    // --- Confort : effet paroi froide, dominé par murs + vitrages (pas les combles). ---

    /** Réduction de la pénalité paroi froide quand les murs sont isolés. */
    public function wallColdWallRelief(): Coefficient
    {
        return new Coefficient(value: 0.08, unit: 'fraction', min: 0.05, max: 0.10, source: 'ADEME : effet parois froides, les murs sont la principale surface rayonnante', reviewedOn: '2026-07-16');
    }

    public function doubleGlazingColdWallRelief(): Coefficient
    {
        return new Coefficient(value: 0.04, unit: 'fraction', min: 0.02, max: 0.06, source: 'ADEME : vitrage isolant, réduction du rayonnement froid des fenêtres', reviewedOn: '2026-07-16');
    }

    public function tripleGlazingColdWallRelief(): Coefficient
    {
        return new Coefficient(value: 0.05, unit: 'fraction', min: 0.03, max: 0.07, source: 'ADEME : triple vitrage, rayonnement froid quasi nul', reviewedOn: '2026-07-16');
    }

    public function roofColdWallRelief(): Coefficient
    {
        return new Coefficient(value: 0.01, unit: 'fraction', min: 0.00, max: 0.02, source: 'ADEME : le plafond contribue peu à l\'effet paroi froide ressenti', reviewedOn: '2026-07-16');
    }

    /** Base de la pénalité paroi froide (maison d'origine). Planché de la pénalité résiduelle. */
    public function baseColdWallPenaltyFactor(): Coefficient
    {
        return new Coefficient(value: 0.15, unit: 'fraction', min: 0.10, max: 0.20, source: 'ADEME : effet parois froides, 1 à 3 °C de ressenti en moins dans un logement mal isolé', reviewedOn: '2026-07-16');
    }

    public function coldWallPenaltyFloor(): Coefficient
    {
        return new Coefficient(value: 0.02, unit: 'fraction', min: 0.01, max: 0.03, source: 'ADEME : parois performantes, effet ressenti quasi nul (résiduel)', reviewedOn: '2026-07-16');
    }

    /** Fraction de l'écart intérieur/extérieur retirée au ressenti (parois froides), par surfaces. */
    public function coldWallPenaltyFactor(EnvelopeState $envelope): float
    {
        $penalty = $this->baseColdWallPenaltyFactor()->value;

        if ($envelope->roofInsulated) {
            $penalty -= $this->roofColdWallRelief()->value;
        }

        if (WallInsulation::None !== $envelope->walls) {
            $penalty -= $this->wallColdWallRelief()->value;
        }

        $penalty -= match ($envelope->glazing) {
            Glazing::Single => 0.0,
            Glazing::Double => $this->doubleGlazingColdWallRelief()->value,
            Glazing::Triple => $this->tripleGlazingColdWallRelief()->value,
        };

        return max($this->coldWallPenaltyFloor()->value, $penalty);
    }
```

Ajouter en tête : `use App\Domain\Building\EnvelopeState;` n'est pas nécessaire (même namespace `App\Domain\Building`) ; s'assurer que `use function max;` est présent (l'ajouter si absent).

- [ ] **Step 4: Run calibration test → PASS**

Run: `vendor/bin/phpunit tests/Unit/Domain/Building/BuildingCalibrationTest.php`
Expected: PASS (4 tests). *À ce stade `HeatingNeedCalculator` etc. ne compilent plus — suite dans les steps 5+.*

- [ ] **Step 5: Swap `Household` field**

Dans `src/Domain/Building/Household.php` : remplacer l'import `use ... InsulationLevel;` → `use App\Domain\Building\EnvelopeState;` (même namespace : import inutile, retirer la ligne `InsulationLevel`), remplacer le paramètre `public InsulationLevel $insulation` par `public EnvelopeState $envelope`, et dans TOUTES les méthodes `with*` remplacer l'argument positionnel `$this->insulation` par `$this->envelope`. Remplacer `withInsulation()` par :

```php
    public function withEnvelope(EnvelopeState $envelope): self
    {
        return new self($this->solarKwc, $this->batteryKwh, $envelope, $this->heatingSystem, $this->boilerBroken, $this->heatingSetpointC);
    }
```

Mettre à jour l'ordre des arguments dans chaque `with*` (l'`EnvelopeState` prend la place de `$this->insulation`).

- [ ] **Step 6: Update `HouseholdTest`**

Dans `tests/Unit/Domain/Building/HouseholdTest.php` : remplacer toute construction `insulation: InsulationLevel::X` par `envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single)` (imports adéquats), et le test de `withInsulation` par `withEnvelope` :

```php
    public function testWithEnvelopeReplacesEnvelopeOnly(): void
    {
        $household = new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler);

        $renovated = $household->withEnvelope(new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple));

        self::assertSame(WallInsulation::None, $household->envelope->walls, 'original untouched');
        self::assertSame(WallInsulation::Exterior, $renovated->envelope->walls);
        self::assertSame(HeatingSystem::FuelOilBoiler, $renovated->heatingSystem);
    }
```

- [ ] **Step 7: Migrate the three calculators**

`HeatingNeedCalculator::dailyNeedKwh` — signature `EnvelopeState $envelope` en 1er param ; remplacer `insulationFactor($insulation)->value` par `envelopeLossFactor($envelope)` :

```php
    public function dailyNeedKwh(EnvelopeState $envelope, float $outdoorC, ?float $setpointC = null): float
    {
        $setpoint = $setpointC ?? $this->calibration->heatingSetpointC()->value;
        $base = $setpoint - $this->calibration->internalHeatGainOffsetC()->value;
        $degreeDays = max(0.0, $base - $outdoorC);

        $need = $this->calibration->heatLossKwhPerDegreeDay()->value
            * $this->calibration->envelopeLossFactor($envelope)
            * $degreeDays;

        return round($need, 2);
    }
```

`ThermalComfortCalculator` — `comfortFor(EnvelopeState $envelope, ...)`, `unheatedComfortFor(EnvelopeState $envelope, ...)`, `comfortAt(EnvelopeState $envelope, ...)`. Remplacer `insulationFactor($insulation)->value` (l.66) par `envelopeLossFactor($envelope)` et `coldWallPenaltyFactor($insulation)->value` (l.79) par `coldWallPenaltyFactor($envelope)`. Propager le type `EnvelopeState` dans les 3 signatures.

`EmergencyHeatingCalculator::consumptionFor(EnvelopeState $envelope, ...)` — remplacer `insulationFactor($insulation)->value` (l.39) par `envelopeLossFactor($envelope)`.

- [ ] **Step 8: Update the calculator tests**

Dans `HeatingNeedCalculatorTest.php`, `ThermalComfortCalculatorTest.php`, `EmergencyHeatingCalculatorTest.php` : remplacer les arguments `InsulationLevel::Original` par `new EnvelopeState(false, WallInsulation::None, Glazing::Single)`, `InsulationLevel::Retrofitted` par `new EnvelopeState(true, WallInsulation::Interior, Glazing::Double)` (facteur 0,535), `InsulationLevel::Reinforced` par `new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple)` (facteur 0,50). Recalculer les valeurs attendues. Exemple pour `HeatingNeedCalculatorTest` (outdoor 0 °C, base 18 → 18 DJU) :

```php
    public function testOriginalHouseNeedsFullHeat(): void
    {
        $need = (new HeatingNeedCalculator())->dailyNeedKwh(
            new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            0.0,
        );
        self::assertSame(225.0, $need); // 12,5 × 1,0 × 18
    }

    public function testInsulatedEnvelopeNeedsLess(): void
    {
        $need = (new HeatingNeedCalculator())->dailyNeedKwh(
            new EnvelopeState(true, WallInsulation::Interior, Glazing::Double),
            0.0,
        );
        self::assertSame(120.38, $need); // 12,5 × 0,535 × 18 = 120,375 → round 120,38
    }
```

Adapter les cas hors-saison (need 0) et paroi-froide de ThermalComfort en réutilisant les pénalités du step 3 (original 0,15 ; ITI seul 0,07 ; best 0,02).

- [ ] **Step 9: Fix the remaining compile-time consumers**

- `SimulationEngine.php` (l.82,85,95,96) : remplacer `$household->insulation` par `$household->envelope`.
- `PrimoAccedantScenario.php` (l.56-64) : remplacer l'import `InsulationLevel` par `EnvelopeState`/`WallInsulation`/`Glazing` et le champ :

```php
    public function initialHousehold(): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }
```

- `SessionGameStore.php` : désérialisation (l.106-110) et sérialisation (l.161-164) :

```php
// désérialisation
$household = new Household(
    solarKwc: (float) ($data['solarKwc'] ?? 0.0),
    batteryKwh: (float) ($data['batteryKwh'] ?? 0.0),
    envelope: new EnvelopeState(
        roofInsulated: (bool) ($data['roofInsulated'] ?? false),
        walls: WallInsulation::from((string) ($data['walls'] ?? WallInsulation::None->value)),
        glazing: Glazing::from((string) ($data['glazing'] ?? Glazing::Single->value)),
    ),
    heatingSystem: HeatingSystem::from((string) ($data['heating'] ?? HeatingSystem::FuelOilBoiler->value)),
    // ... champs suivants inchangés (boilerBroken, setpoint) ...
);
```
```php
// sérialisation
'solarKwc' => $game->state->household->solarKwc,
'batteryKwh' => $game->state->household->batteryKwh,
'roofInsulated' => $game->state->household->envelope->roofInsulated,
'walls' => $game->state->household->envelope->walls->value,
'glazing' => $game->state->household->envelope->glazing->value,
'heating' => $game->state->household->heatingSystem->value,
```
Remplacer l'import `InsulationLevel` par `EnvelopeState, WallInsulation, Glazing`. Vérifier les autres clés existantes (batteryKwh, boilerBroken, setpoint) et ne pas les perdre.

- `SimulateDemoCommand.php` : remplacer toute référence `insulation` par la construction `EnvelopeState` équivalente (idem scénario).
- `GameViewFactory.php` (l.150,343-348) : voir Task 3 pour `insulationLabel`/`insulationTier` définitifs ; ici, corriger a minima pour compiler — remplacer `$household->insulation->label()` et le `match` `insulationTier` par une dérivation depuis `$household->envelope` (implémentée proprement en Task 3, step ci-dessous). Provisoire acceptable :

```php
insulationTier: $this->insulationTier($household->envelope),
insulationLabel: $this->envelopeLabel($household->envelope),
```
(et ajouter les deux helpers privés définis en Task 3 — les inclure ici pour compiler.)

- `RenovationQuoter.php` (`insulationQuote`, l.57-84) : neutraliser temporairement (les vrais devis surfaces arrivent en Task 4) —

```php
    private function insulationQuote(Household $household): ?RenovationQuote
    {
        // Remplacé par les devis par surface en Task 4 (combles/murs/vitrage).
        return null;
    }
```

- [ ] **Step 10: Delete `InsulationLevel`**

```bash
git rm src/Domain/Building/InsulationLevel.php
```
Vérifier : `grep -rl 'InsulationLevel' src/ tests/` → doit être **vide**.

- [ ] **Step 11: Add the `GameViewFactory` helpers (surface → label/tier)**

Dans `GameViewFactory.php`, ajouter :

```php
    private function envelopeLabel(EnvelopeState $envelope): string
    {
        $done = [];
        if ($envelope->roofInsulated) {
            $done[] = 'combles';
        }
        if (WallInsulation::None !== $envelope->walls) {
            $done[] = 'murs';
        }
        if (Glazing::Single !== $envelope->glazing) {
            $done[] = 'vitrage';
        }

        return [] === $done ? 'D\'origine' : ucfirst(implode(' + ', $done));
    }

    /** Visual tier (0|1|2) for the scene shell, from the continuous loss factor. */
    private function insulationTier(EnvelopeState $envelope): int
    {
        $factor = $this->buildingCalibration->envelopeLossFactor($envelope);

        return match (true) {
            $factor > 0.85 => 0,
            $factor > 0.60 => 1,
            default => 2,
        };
    }
```
S'assurer que `GameViewFactory` a accès à une `BuildingCalibration` (l'injecter si besoin — vérifier le constructeur ; ajouter `private BuildingCalibration $buildingCalibration = new BuildingCalibration()` au constructeur si absent) et importer `EnvelopeState`, `WallInsulation`, `Glazing`. Fonctions `use function ucfirst; use function implode;`.

- [ ] **Step 12: Fix the remaining tests (scenario, store, engine, factory, outcome, renovation handler/quoter)**

Passer en revue chaque test listé et remplacer les constructions `Household(... insulation: InsulationLevel::X ...)` / assertions `->insulation` par `envelope: new EnvelopeState(...)` / `->envelope`. Pour `PrimoAccedantScenarioTest` : asserter l'état de départ non isolé :

```php
    public function testHouseStartsUninsulated(): void
    {
        $household = (new PrimoAccedantScenario())->initialHousehold();
        self::assertFalse($household->envelope->roofInsulated);
        self::assertSame(WallInsulation::None, $household->envelope->walls);
        self::assertSame(Glazing::Single, $household->envelope->glazing);
    }
```
`RenovationQuoterTest` / `RenovationHandlerTest` : retirer/ignorer temporairement les cas d'isolation (couverts en Task 4) — commenter avec `// réactivé en Task 4 (devis par surface)` ou marquer `->markTestSkipped('devis surface en Task 4')`. Ne pas laisser d'assertion sur l'ancien `Renovation::Insulation` cassée.

- [ ] **Step 13: Run the full suite + qa**

Run: `vendor/bin/phpunit`
Expected: PASS (tous). Puis `make cs-fix && make twig && make test` (stan couvert par CI si sandbox web, cf. CLAUDE.md §4).

- [ ] **Step 14: Manual smoke — the demo still runs**

Run: `php bin/console app:simulate:demo --days 14 --from 2025-01-01`
Expected: tourne sans erreur, factures/DPE cohérents avec un départ non isolé (DPE F/G inchangé).

- [ ] **Step 15: Commit**

```bash
git add -A
git commit -m "refactor(building): envelope by surfaces replaces InsulationLevel (arbre travaux T1)

Household porte un EnvelopeState (combles/murs/vitrage). BuildingCalibration
agrège la déperdition depuis les surfaces (1 − Σ retraits sourcés, planché
0,15) et la pénalité paroi froide. HeatingNeed/ThermalComfort/EmergencyHeating
consomment EnvelopeState. DPE inchangé (dérive de l'énergie réelle). Départ non
isolé = facteur 1,0 → DPE F/G préservé. Travaux d'isolation neutralisés
(rétablis par surface en Task 4).

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: Travaux par surface — enum `Renovation` + coûts + devis

> **Note :** modifier l'enum `Renovation` casse `RenovationQuoter::quote()` (match exhaustif PHP) et les tests référençant `Renovation::Insulation` → corrigés dans le même commit. **`_slot.html.twig` n'est PAS touché ici** : sa map `worksOfSlot` référence l'ancienne clé sous forme de chaîne (`'insulation'`) — le rendu Twig ne casse pas (le garde `is defined` masque simplement la carte), donc le câblage IHM des 4 nouveaux travaux est fait en **Task 4**. Conséquence assumée : entre Task 3 et Task 4, les 4 travaux existent et sont chiffrés mais ne sont pas encore cliquables (intermédiaire transitoire, comme les travaux neutralisés en Task 2).

**Files:**
- Modify: `src/Domain/Finance/Renovation.php` (remplace `Insulation` par 4 cas)
- Modify: `src/Domain/Finance/FinanceCalibration.php` (remplace `insulationRetrofitCost`/`insulationReinforceCost` par coûts par surface)
- Modify: `src/Domain/Finance/RenovationQuoter.php` (`quote()` match + méthodes de devis)
- Test: `tests/Unit/Domain/Finance/RenovationQuoterTest.php`, `tests/Unit/Domain/Finance/FinanceCalibrationTest.php` (si présent)

**Interfaces:**
- Consumes (Task 1/2): `EnvelopeState`, `WallInsulation`, `Glazing`, `Household->envelope`, `withEnvelope`.
- Produces:
  - `Renovation::RoofInsulation`, `Renovation::WallInsulationInterior`, `Renovation::WallInsulationExterior`, `Renovation::Glazing` (remplacent `Insulation`).
  - `FinanceCalibration::roofInsulationCost()`, `wallInsulationInteriorCost()`, `wallInsulationExteriorCost()`, `glazingUpgradeCost()` (Coefficient €).
  - `RenovationQuoter` renvoie un devis par surface, `null` si surface déjà au mieux ; murs ITI et ITE **mutuellement exclusifs** (si murs déjà isolés → les deux devis murs = `null`) ; vitrage échelle simple→double→triple.

- [ ] **Step 1: Failing quoter tests**

```php
    public function testRoofInsulationQuotedWhenAtticBare(): void
    {
        $household = $this->uninsulatedHousehold();
        $quote = (new RenovationQuoter())->quote(Renovation::RoofInsulation, $household);

        self::assertNotNull($quote);
        self::assertTrue($quote->resultingHousehold->envelope->roofInsulated);
    }

    public function testRoofInsulationHiddenOnceDone(): void
    {
        $household = $this->uninsulatedHousehold()
            ->withEnvelope(new EnvelopeState(true, WallInsulation::None, Glazing::Single));

        self::assertNull((new RenovationQuoter())->quote(Renovation::RoofInsulation, $household));
    }

    public function testWallItiAndIteAreMutuallyExclusive(): void
    {
        $withWalls = $this->uninsulatedHousehold()
            ->withEnvelope(new EnvelopeState(false, WallInsulation::Interior, Glazing::Single));

        $quoter = new RenovationQuoter();
        self::assertNull($quoter->quote(Renovation::WallInsulationInterior, $withWalls));
        self::assertNull($quoter->quote(Renovation::WallInsulationExterior, $withWalls));
    }

    public function testGlazingClimbsSingleToDoubleToTriple(): void
    {
        $quoter = new RenovationQuoter();
        $single = $this->uninsulatedHousehold();
        $doubleQuote = $quoter->quote(Renovation::Glazing, $single);
        self::assertNotNull($doubleQuote);
        self::assertSame(Glazing::Double, $doubleQuote->resultingHousehold->envelope->glazing);

        $atDouble = $single->withEnvelope(new EnvelopeState(false, WallInsulation::None, Glazing::Double));
        $tripleQuote = $quoter->quote(Renovation::Glazing, $atDouble);
        self::assertNotNull($tripleQuote);
        self::assertSame(Glazing::Triple, $tripleQuote->resultingHousehold->envelope->glazing);

        $atTriple = $single->withEnvelope(new EnvelopeState(false, WallInsulation::None, Glazing::Triple));
        self::assertNull($quoter->quote(Renovation::Glazing, $atTriple));
    }
```
(helper `uninsulatedHousehold()` : `new Household(0.0, 0.0, new EnvelopeState(false, WallInsulation::None, Glazing::Single), HeatingSystem::FuelOilBoiler)`.)

- [ ] **Step 2: Run → FAIL** (cas `Renovation::RoofInsulation` inexistant).

Run: `vendor/bin/phpunit tests/Unit/Domain/Finance/RenovationQuoterTest.php`

- [ ] **Step 3: Split the `Renovation` enum**

```php
    /** Insulate the attic/roof (priority #1, ~24 % of losses). */
    case RoofInsulation = 'roof_insulation';
    /** Walls, interior (ITI) — cheaper, eats living space. Exclusive with ITE. */
    case WallInsulationInterior = 'wall_insulation_interior';
    /** Walls, exterior (ITE) — dearer, better. Exclusive with ITI. */
    case WallInsulationExterior = 'wall_insulation_exterior';
    /** Windows: single → double → triple glazing. */
    case Glazing = 'glazing';
    case HeatPump = 'heat_pump';
    case SolarPanels = 'solar_panels';
    case HomeBattery = 'home_battery';
    case BoilerRepair = 'boiler_repair';
```
`isSubsidised()` : `RoofInsulation, WallInsulationInterior, WallInsulationExterior, Glazing, HeatPump => true` ; `SolarPanels, HomeBattery, BoilerRepair => false`. `isLoanEligible()` inchangé (délègue à `isSubsidised`).

- [ ] **Step 4: Costs in `FinanceCalibration`**

Remplacer `insulationRetrofitCost`/`insulationReinforceCost` par (ordres de grandeur ADEME ~100 m², à affiner en registre) :

```php
    /** Isolation des combles (~100 m² de toiture). */
    public function roofInsulationCost(): Coefficient
    {
        return new Coefficient(value: 4000.0, unit: '€', min: 2500.0, max: 6000.0, source: 'ADEME : isolation combles ~25-60 €/m²', reviewedOn: '2026-07-16');
    }

    /** Isolation des murs par l'intérieur (ITI). */
    public function wallInsulationInteriorCost(): Coefficient
    {
        return new Coefficient(value: 9000.0, unit: '€', min: 6000.0, max: 12000.0, source: 'ADEME : ITI ~50-90 €/m² de mur', reviewedOn: '2026-07-16');
    }

    /** Isolation des murs par l'extérieur (ITE). */
    public function wallInsulationExteriorCost(): Coefficient
    {
        return new Coefficient(value: 18000.0, unit: '€', min: 12000.0, max: 25000.0, source: 'ADEME : ITE ~110-200 €/m² de mur (ravalement inclus)', reviewedOn: '2026-07-16');
    }

    /** Remplacement des menuiseries (montée d'un cran de vitrage). */
    public function glazingUpgradeCost(): Coefficient
    {
        return new Coefficient(value: 8000.0, unit: '€', min: 5000.0, max: 12000.0, source: 'ADEME : remplacement fenêtres ~500-800 €/fenêtre', reviewedOn: '2026-07-16');
    }
```

- [ ] **Step 5: Rewrite `RenovationQuoter`**

`quote()` match : remplacer `Renovation::Insulation => $this->insulationQuote(...)` par les 4 branches ; retirer `insulationQuote()`. Ajouter :

```php
    private function roofQuote(Household $household): ?RenovationQuote
    {
        if ($household->envelope->roofInsulated) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->roofInsulationCost()->value);

        return new RenovationQuote(
            work: Renovation::RoofInsulation,
            title: 'Isolation des combles',
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withEnvelope($household->envelope->withRoofInsulated(true)),
        );
    }

    private function wallQuote(Household $household, WallInsulation $target, Renovation $work, string $title, float $cost): ?RenovationQuote
    {
        // ITI et ITE mutuellement exclusifs : dès que les murs sont isolés, plus d'offre murs.
        if (WallInsulation::None !== $household->envelope->walls) {
            return null;
        }
        $price = Money::fromEuros($cost);

        return new RenovationQuote(
            work: $work,
            title: $title,
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withEnvelope($household->envelope->withWalls($target)),
        );
    }

    private function glazingQuote(Household $household): ?RenovationQuote
    {
        $target = match ($household->envelope->glazing) {
            Glazing::Single => Glazing::Double,
            Glazing::Double => Glazing::Triple,
            Glazing::Triple => null,
        };
        if (null === $target) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->glazingUpgradeCost()->value);

        return new RenovationQuote(
            work: Renovation::Glazing,
            title: sprintf('Menuiseries — %s', $target->label()),
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withEnvelope($household->envelope->withGlazing($target)),
        );
    }
```
Dans `quote()` :
```php
Renovation::RoofInsulation => $this->roofQuote($household),
Renovation::WallInsulationInterior => $this->wallQuote($household, WallInsulation::Interior, Renovation::WallInsulationInterior, 'Isolation des murs — intérieure (ITI)', $this->calibration->wallInsulationInteriorCost()->value),
Renovation::WallInsulationExterior => $this->wallQuote($household, WallInsulation::Exterior, Renovation::WallInsulationExterior, 'Isolation des murs — extérieure (ITE)', $this->calibration->wallInsulationExteriorCost()->value),
Renovation::Glazing => $this->glazingQuote($household),
```
Importer `Glazing`, `WallInsulation`.

- [ ] **Step 6: Run quoter tests → PASS**

Run: `vendor/bin/phpunit tests/Unit/Domain/Finance/RenovationQuoterTest.php`
Expected: PASS. Réactiver les cas isolation commentés/skippés en Task 2 (step 12) et les convertir aux 4 nouveaux travaux.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(finance): insulation works split by surface (combles/murs ITI-ITE/vitrage)

Renovation::Insulation → 4 nœuds. ITI/ITE mutuellement exclusifs, vitrage en
échelle simple→double→triple. Coûts par surface sourcés ADEME, éligibles
prime + éco-PTZ comme les travaux de performance.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: IHM — le panneau « Isolation » propose les 4 travaux

**Files:**
- Modify: `templates/game/panel/_slot.html.twig` (`worksOfSlot` du slot `walls`, entête DPE)
- Modify: `src/Application/GameViewFactory.php` (s'assurer que `actions` expose les 4 travaux — cf. build des `ActionView`)
- Test: `tests/Integration/...` (LiveComponent) ou `tests/Unit/Application/GameViewFactoryTest.php` selon l'existant

**Interfaces:**
- Consumes: `game.actions['roof_insulation'|'wall_insulation_interior'|'wall_insulation_exterior'|'glazing']` (clés = `Renovation->value`), `game.insulationLabel`, `game.dpeLetter`.
- Produces: le slot `walls` liste les 4 `QuoteCard` disponibles.

- [ ] **Step 1: Verify the actions map exposes the 4 works**

Inspecter comment `GameViewFactory` construit `actions` (map `Renovation->value => ActionView`). Il itère probablement sur `Renovation::cases()` en appelant `RenovationQuoter::quote()` ; sinon, l'étendre pour couvrir les 4 nouveaux cas. Vérifier :

Run: `grep -n "actions\|ActionView\|Renovation::cases\|quote(" src/Application/GameViewFactory.php`

- [ ] **Step 2: Update `worksOfSlot` in `_slot.html.twig`**

Remplacer (l.14-20) la clé `walls: ['insulation']` par :
```twig
    walls: ['roof_insulation', 'wall_insulation_interior', 'wall_insulation_exterior', 'glazing'],
```
Et l'entête du slot `walls` (l.29-32) : garder DPE + valeur du bien + confort ressenti (déjà orienté décision) ; remplacer l'éventuel libellé d'isolation par `game.insulationLabel`. La séparation lire/agir complète (entêtes minimaux, tiroir) reste **Tranche 3** — ici on se contente d'afficher les 4 cartes dans le panneau existant.

- [ ] **Step 3: Add/adjust an integration or view test**

Si un test d'intégration LiveComponent existe pour le panneau travaux, ajouter l'assertion que les 4 titres apparaissent quand la maison est nue :

```php
self::assertStringContainsString('Isolation des combles', $html);
self::assertStringContainsString('intérieure (ITI)', $html);
self::assertStringContainsString('extérieure (ITE)', $html);
self::assertStringContainsString('Menuiseries', $html);
```
Sinon, un test unitaire sur `GameViewFactory` vérifiant que `actions` contient les 4 clés pour une maison nue.

- [ ] **Step 4: Lint twig + tests**

Run: `make twig && vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 5: Manual smoke — playable**

Lancer l'app (`/run` skill ou serveur Symfony), cliquer le mur : les 4 travaux s'affichent ; en poser un met à jour le DPE/confort ; ITI et ITE disparaissent une fois les murs faits ; le vitrage monte double→triple.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(ui): the walls panel offers the four envelope works

Le slot mur liste combles/murs ITI/ITE/vitrage (panneau existant ; le tiroir
latéral et la séparation lire/agir complète = Tranche 3).

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: Vérification de bout en bout + revue

- [ ] **Step 1: Full qa gate**

Run: `make cs && make twig && make test` (+ `make stan` en local/CI — CLAUDE.md §4).
Expected: vert. Corriger toute cause (jamais de `@phpstan-ignore`).

- [ ] **Step 2: Demo across a winter**

Run: `php bin/console app:simulate:demo --days 60 --from 2025-01-01`
Expected: cohérent ; départ DPE F/G identique à avant la Tranche 1 (non-régression).

- [ ] **Step 3: Grep for leftovers**

Run: `grep -rn 'InsulationLevel\|insulationFactor\|Renovation::Insulation\b\|->insulation\b' src/ tests/ templates/`
Expected: **vide**.

- [ ] **Step 4: Self-review vs spec §5.1/§5.2**

Vérifier : enveloppe par surfaces ✔, DPE dérivé inchangé ✔, murs ITI/ITE exclusifs ✔, vitrage échelle ✔, départ préservé ✔, plafond ~0,50 ✔. Noter dans `docs/backlog.md` que Tranche 1 est faite et que Tranche 2 (RenovationAdvisor/conseils) suit.

- [ ] **Step 5: Optional — request code review**

`superpowers:requesting-code-review` sur le diff des tâches 1-4 avant d'enchaîner sur la Tranche 2.

---

## Self-Review du plan (couverture spec)

- **Enveloppe par surfaces (§5.1)** → Tasks 1-2 (EnvelopeState, Household, calibration). ✔
- **DPE recalculé (§5.2)** → confirmé *sans changement* (dérive de l'énergie réelle via `DpeCertifier`) ; Task 2 step 14 vérifie la non-régression du départ. ✔
- **Nœuds enveloppe (§3 branche A : combles, murs ITI/ITE, vitrage)** → Task 3. ✔
- **Aides périmètre inchangé étendu (§6)** → Task 3 step 3 (`isSubsidised`). ✔
- **IHM (affichage des travaux)** → Task 4 ; la séparation lire/agir + tiroir latéral (§7) = **Tranche 3**, hors ce plan (dépendance notée). ✔
- **Coefficients sourcés (§8)** → tous en `Coefficient` (Task 2 step 3, Task 3 step 4). ✔
- **Hors périmètre** : type d'isolant (Phase 5), ventilation/chauffage/production/gestes (Tranches 2+). ✔

Écarts assumés : le plafond ~0,50 (validé) ; `RenovationAdvisor`/conseils 💡⚠ = Tranche 2 (pas dans ce plan) ; le tiroir latéral = Tranche 3.
