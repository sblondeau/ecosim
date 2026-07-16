# Tranche 4 — Chauffage complet (émetteurs BT + granulés) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Compléter la branche chauffage de l'arbre : (1) **émetteurs basse température** — un modificateur qui fait dépendre le SCOP de la PAC du type d'émetteur (radiateurs fonte ~2,5 vs plancher/BT ~4,3), *seule la PAC est sensible* ; (2) **chaudière à granulés** — un nouveau générateur qui introduit un **3ᵉ vecteur énergétique** (pellets) traversant facture / totaux / CO₂ / DPE.

**Architecture:** `lowTempEmitters` (bool) et le carrier `pelletKg` s'ajoutent **avec valeur par défaut** → additifs, non cassants (pas de swap de type transverse). Le SCOP de la PAC devient fonction des émetteurs ; les générateurs à combustion (fioul, granulés) y sont insensibles. Le pellet est un carrier discret (comme le fioul) : fioul et granulés étant mutuellement exclusifs, le joueur ne voit jamais 3 lignes de combustible.

**Tech Stack:** PHP 8.4 (domaine pur), Symfony UX Twig/LiveComponents, PHPUnit 13.

## Global Constraints

- `declare(strict_types=1)` partout ; `final` ; VOs `final readonly`. `src/Domain/**` PHP pur (aucun import framework).
- Tout coefficient chiffré = `App\Domain\Calibration\Coefficient` (value+unit+min+max+source+reviewedOn) ; sources ADEME/Base Carbone. Valeurs validées :
  - SCOP PAC émetteurs **haute temp. 2,5** (min 2,2 max 2,8) ; **basse temp. 4,3** (min 4,0 max 4,6) — ADEME/NF PAC.
  - Granulés : rendement **0,90** ; **4,6 kWh/kg** ; CO₂ **30 g/kWh** (biomasse, Base Carbone) ; prix **0,34 €/kg** ; facteur énergie primaire DPE **1,0** (biomasse).
  - Coûts : émetteurs BT **6 500 €** ; chaudière granulés + silo **14 000 €**.
- Émetteurs BT et granulés = travaux de performance → **prime + éco-PTZ éligibles**.
- Identifiants/commentaires anglais ; libellés joueur français. Tests `tests/Unit/...` (domaine, valeurs exactes) + `tests/Integration/...` (LiveComponent). Chaque brique avec ses tests dans le même commit.
- `make cs`/`make twig`/`make test` verts avant commit (`make stan` = CI). Pas de `@phpstan-ignore`.
- Commits `type(scope): subject`, terminés par `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Branche `docs/arbre-travaux-spec`.
- **Bascule de SCOP (leçon assumée)** : poser une PAC sur les radiateurs fonte par défaut donne SCOP 2,5 (pas 3,5) ; ajouter les émetteurs BT → 4,3. Change l'équilibre existant — intentionnel (« la PAC est un système »). Les tests de conso PAC existants sont recalculés.
- **« Facture 2 lignes » préservée à l'œil** : le modèle gagne le carrier pellet, mais fioul/granulés sont exclusifs → au plus élec + un combustible affiché.

## Décisions de conception actées

- `Household::lowTempEmitters` (bool, défaut false) — ajouté **en fin de constructeur** (après `heatingSetpointC`) pour ne pas casser les constructions existantes.
- `HeatingConsumption::pelletKg` (float, défaut 0.0) — ajouté en fin de constructeur.
- `DailyBill::pelletCost` (Money, défaut `new Money(0)`) — ajouté en fin.
- `PeriodTotals::$pelletKg`, `$pelletCost` — ajoutés avec défauts.
- `CarbonAccountant::emittedKg(..., float $pelletKg = 0.0)` et `DpeCertifier::certify(..., float $pelletKg = 0.0)` — paramètre optionnel en fin (non cassant, callers mis à jour).
- Le SCOP de la PAC ne dépend QUE de `lowTempEmitters` ; fioul et granulés l'ignorent (spec §5.3).

---

## Task 1: Émetteurs BT — SCOP de la PAC piloté par les émetteurs

**Files:**
- Modify: `src/Domain/Building/Household.php` (+`lowTempEmitters`)
- Modify: `src/Domain/Energy/EnergyCalibration.php` (remplace `heatPumpScop()` par SCOP HT/BT)
- Modify: `src/Domain/Building/HeatingEnergyCalculator.php` (SCOP selon émetteurs)
- Modify: `src/Domain/Simulation/SimulationEngine.php` (passe `lowTempEmitters`)
- Modify: `src/Application/SessionGameStore.php` (sérialise `lowTempEmitters` + bump FORMAT_VERSION)
- Modify: `src/Domain/Scenario/PrimoAccedantScenario.php` (départ : émetteurs HT → rien à changer si défaut false)
- Test: `HeatingEnergyCalculatorTest`, `HouseholdTest`, `EnergyCalibrationTest` (si présent), `SimulationEngineTest`, `SessionGameStoreTest`

**Interfaces:**
- Produces: `Household->lowTempEmitters: bool` + `withLowTempEmitters(bool): self`.
- Produces: `EnergyCalibration::heatPumpScopHighTempEmitters(): Coefficient` (2.5), `heatPumpScopLowTempEmitters(): Coefficient` (4.3). Removes `heatPumpScop()`.
- Produces: `HeatingEnergyCalculator::consumptionFor(HeatingSystem $system, float $needKwh, bool $lowTempEmitters = false): HeatingConsumption`.

- [ ] **Step 1: Failing test — SCOP depends on emitters**

Dans `HeatingEnergyCalculatorTest` :
```php
    public function testHeatPumpOnOldRadiatorsUsesDegradedScop(): void
    {
        // 430 kWh need ÷ SCOP 2,5 = 172,0 kWh elec
        $c = (new HeatingEnergyCalculator())->consumptionFor(HeatingSystem::HeatPump, 430.0, false);
        self::assertSame(172.0, $c->electricityKwh);
    }

    public function testHeatPumpWithLowTempEmittersUsesNominalScop(): void
    {
        // 430 kWh ÷ SCOP 4,3 = 100,0 kWh elec
        $c = (new HeatingEnergyCalculator())->consumptionFor(HeatingSystem::HeatPump, 430.0, true);
        self::assertSame(100.0, $c->electricityKwh);
    }
```

- [ ] **Step 2: Run → FAIL** (`heatPumpScopHighTempEmitters` absent, 3rd param absent).

- [ ] **Step 3: Replace the SCOP coefficient** in `EnergyCalibration` — retirer `heatPumpScop()`, ajouter :
```php
    /** SCOP of the air/water heat pump on high-temperature emitters (old cast-iron radiators, ~65 °C water). */
    public function heatPumpScopHighTempEmitters(): Coefficient
    {
        return new Coefficient(value: 2.5, unit: 'SCOP', min: 2.2, max: 2.8, source: 'ADEME / NF PAC : SCOP dégradé sur émetteurs haute température (~55-65 °C)', reviewedOn: '2026-07-17');
    }

    /** SCOP on low-temperature emitters (underfloor / large BT radiators, ~35 °C water). */
    public function heatPumpScopLowTempEmitters(): Coefficient
    {
        return new Coefficient(value: 4.3, unit: 'SCOP', min: 4.0, max: 4.6, source: 'ADEME / NF PAC : SCOP nominal sur émetteurs basse température (~35 °C)', reviewedOn: '2026-07-17');
    }
```

- [ ] **Step 4: Use it in `HeatingEnergyCalculator`** — signature `+ bool $lowTempEmitters = false` ; branche PAC :
```php
            HeatingSystem::HeatPump => new HeatingConsumption(
                needKwh: $needKwh,
                electricityKwh: round($needKwh / ($lowTempEmitters
                    ? $this->calibration->heatPumpScopLowTempEmitters()->value
                    : $this->calibration->heatPumpScopHighTempEmitters()->value), 2),
                fuelOilLitres: 0.0,
            ),
```

- [ ] **Step 5: Add `lowTempEmitters` to `Household`** — nouveau param **en fin** de constructeur `public bool $lowTempEmitters = false`, propager dans TOUTES les méthodes `with*` (ajouter l'argument), et ajouter :
```php
    public function withLowTempEmitters(bool $lowTempEmitters): self
    {
        return new self($this->solarKwc, $this->batteryKwh, $this->envelope, $this->heatingSystem, $this->boilerBroken, $this->heatingSetpointC, $lowTempEmitters);
    }
```
`withHeatingSystem` doit conserver `lowTempEmitters` (les émetteurs restent en place quand on change de générateur).

- [ ] **Step 6: Pass it in `SimulationEngine::snapshot`** — l'appel non-panne :
```php
            : $this->heatingEnergy->consumptionFor(
                $household->heatingSystem,
                $this->heatingNeed->dailyNeedKwh($household->envelope, $weather->temperatureC, $household->heatingSetpointC),
                $household->lowTempEmitters,
            );
```

- [ ] **Step 7: Serialize in `SessionGameStore`** — déshydratation `'lowTempEmitters' => $game->state->household->lowTempEmitters`, réhydratation `lowTempEmitters: (bool) ($data['lowTempEmitters'] ?? false)`, **bump `FORMAT_VERSION`**.

- [ ] **Step 8: Update affected tests** — `HeatingEnergyCalculatorTest` (les cas PAC existants passent de SCOP 3,5 à 2,5 par défaut → recalculer les kWh attendus), `SimulationEngineTest` (idem si un cas PAC vérifie une conso élec — recalculer), `HouseholdTest` (ajouter `withLowTempEmitters`). Run full suite.

- [ ] **Step 9: Run → PASS + `make cs-fix` + demo smoke.** `php bin/console app:simulate:demo --days 30 --from 2025-01-01`.
- [ ] **Step 10: Commit** — `feat(building): heat-pump SCOP depends on emitter type (arbre travaux T4)`

---

## Task 2: Vecteur pellet — conversion domaine (générateur granulés)

**Files:**
- Modify: `src/Domain/Building/HeatingSystem.php` (+`PelletBoiler`)
- Modify: `src/Domain/Building/HeatingConsumption.php` (+`pelletKg`)
- Modify: `src/Domain/Energy/EnergyCalibration.php` (coeffs pellet)
- Modify: `src/Domain/Building/HeatingEnergyCalculator.php` (branche `PelletBoiler`)
- Test: `HeatingEnergyCalculatorTest`, `HeatingConsumptionTest` (si présent)

**Interfaces:**
- Produces: `HeatingSystem::PelletBoiler` (value `'pellet'`, label 'Chaudière à granulés').
- Produces: `HeatingConsumption->pelletKg: float` (défaut 0.0).
- Produces: `EnergyCalibration::pelletBoilerEfficiency()` (0.90), `pelletEnergyKwhPerKg()` (4.6), `pelletCo2GramsPerKwh()` (30), `pelletPrimaryEnergyFactor()` (1.0).

- [ ] **Step 1: Failing test**
```php
    public function testPelletBoilerBurnsKilograms(): void
    {
        // 414 kWh ÷ 0,90 rendement ÷ 4,6 kWh/kg = 100,0 kg
        $c = (new HeatingEnergyCalculator())->consumptionFor(HeatingSystem::PelletBoiler, 414.0);
        self::assertSame(100.0, $c->pelletKg);
        self::assertSame(0.0, $c->fuelOilLitres);
        self::assertSame(0.0, $c->electricityKwh);
    }
```
- [ ] **Step 2: Run → FAIL** (`PelletBoiler` absent).
- [ ] **Step 3: Add the enum case** — `case PelletBoiler = 'pellet';` + `label()` arm `'Chaudière à granulés'`.
- [ ] **Step 4: Add `pelletKg` to `HeatingConsumption`** — param **en fin** `public float $pelletKg = 0.0` ; `none()` inchangé (défaut couvre).
- [ ] **Step 5: Add the pellet coefficients** to `EnergyCalibration` :
```php
    public function pelletBoilerEfficiency(): Coefficient
    {
        return new Coefficient(value: 0.90, unit: 'fraction', min: 0.85, max: 0.95, source: 'ADEME : rendement chaudière automatique à granulés', reviewedOn: '2026-07-17');
    }

    public function pelletEnergyKwhPerKg(): Coefficient
    {
        return new Coefficient(value: 4.6, unit: 'kWh/kg', min: 4.6, max: 5.2, source: 'Norme ENplus / ADEME : PCI granulés bois ~4,6-5 kWh/kg', reviewedOn: '2026-07-17');
    }

    public function pelletCo2GramsPerKwh(): Coefficient
    {
        return new Coefficient(value: 30.0, unit: 'gCO2e/kWh', min: 20.0, max: 40.0, source: 'ADEME Base Carbone : granulés bois (combustion + amont), ~30 g CO2e/kWh', reviewedOn: '2026-07-17');
    }

    /** DPE primary-energy factor for biomass (wood/pellets): 1.0, unlike electricity's 2.3. */
    public function pelletPrimaryEnergyFactor(): Coefficient
    {
        return new Coefficient(value: 1.0, unit: 'factor', min: 1.0, max: 1.0, source: 'Méthode DPE 2021 : coefficient d\'énergie primaire biomasse = 1,0', reviewedOn: '2026-07-17');
    }
```
- [ ] **Step 6: Add the `PelletBoiler` branch** to `HeatingEnergyCalculator::consumptionFor` match :
```php
            HeatingSystem::PelletBoiler => new HeatingConsumption(
                needKwh: $needKwh,
                electricityKwh: 0.0,
                fuelOilLitres: 0.0,
                pelletKg: round(
                    $needKwh
                        / $this->calibration->pelletBoilerEfficiency()->value
                        / $this->calibration->pelletEnergyKwhPerKg()->value,
                    2,
                ),
            ),
```
(Le `match` sur `HeatingSystem` devient exhaustif à 3 cas — pas de `default`.)
- [ ] **Step 7: Run → PASS + full suite + `make cs-fix`.**
- [ ] **Step 8: Commit** — `feat(building): pellet boiler heating carrier (arbre travaux T4)`

---

## Task 3: Pellet dans la facture

**Files:**
- Modify: `src/Domain/Finance/DailyBill.php` (+`pelletCost`)
- Modify: `src/Domain/Finance/BillCalculator.php` (ligne pellet)
- Modify: `src/Domain/Finance/FinanceCalibration.php` (`pelletPricePerKg()`)
- Test: `BillCalculatorTest` (si présent), `DailyBillTest` (si présent)

**Interfaces:**
- Produces: `DailyBill->pelletCost: Money` (défaut `new Money(0)`) ; `netCost()` inclut le pellet.
- Produces: `FinanceCalibration::pelletPricePerKg(): Coefficient` (0.34).

- [ ] **Step 1: Failing test** — 100 kg × 0,34 € = 34,00 €.
```php
    public function testBillPricesPellets(): void
    {
        $bill = (new BillCalculator())->billFor(
            EnergyBalance::... /* zéro import/export, réutiliser le harnais existant */,
            new HeatingConsumption(needKwh: 414.0, electricityKwh: 0.0, fuelOilLitres: 0.0, pelletKg: 100.0),
        );
        self::assertSame('34,00 €', $bill->pelletCost->format());
    }
```
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: `pelletPricePerKg` in `FinanceCalibration`** :
```php
    public function pelletPricePerKg(): Coefficient
    {
        return new Coefficient(value: 0.34, unit: '€/kg', min: 0.28, max: 0.50, source: 'ADEME / Propellet : prix granulés ~280-500 €/tonne (volatil)', reviewedOn: '2026-07-17');
    }
```
- [ ] **Step 4: Add `pelletCost` to `DailyBill`** — param **en fin** `public Money $pelletCost = new Money(0)` ; `zero()` couvre par défaut ; `netCost()` :
```php
    return $this->electricityCost->plus($this->fuelOilCost)->plus($this->pelletCost)->minus($this->surplusRevenue);
```
- [ ] **Step 5: Price it in `BillCalculator::billFor`** — ajouter :
```php
            pelletCost: Money::fromEuros(
                $heating->pelletKg * $this->calibration->pelletPricePerKg()->value,
            ),
```
- [ ] **Step 6: Run → PASS + full suite + `make cs-fix`.**
- [ ] **Step 7: Commit** — `feat(finance): pellet bill line (arbre travaux T4)`

---

## Task 4: Pellet dans les totaux cumulés

**Files:**
- Modify: `src/Domain/Simulation/PeriodTotals.php` (+`pelletKg`, +`pelletCost`)
- Test: `PeriodTotalsTest` (si présent) ou couverture via `SimulationEngineTest`

**Interfaces:**
- Produces: `PeriodTotals->pelletKg: float`, `->pelletCost: Money` (défauts) ; `add()` les cumule ; `netEnergyCost()` inclut `pelletCost`.

- [ ] **Step 1: Failing test** — après avoir folder un jour avec `pelletKg`/`pelletCost`, les totaux les portent et `netEnergyCost()` les compte.
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Add the fields + fold** — params **en fin** `public float $pelletKg = 0.0`, `public Money $pelletCost = new Money(0)` ; dans `add()` : `pelletKg: $this->pelletKg + $day->heating->pelletKg`, `pelletCost: $this->pelletCost->plus($day->bill->pelletCost)` ; `netEnergyCost()` : `->plus($this->pelletCost)`.
- [ ] **Step 4: Run → PASS + full suite + `make cs-fix`.**
- [ ] **Step 5: Commit** — `feat(sim): pellet totals (arbre travaux T4)`

---

## Task 5: Pellet dans le CO₂ et le DPE

**Files:**
- Modify: `src/Domain/Energy/CarbonAccountant.php` (+ pellet)
- Modify: `src/Domain/Building/DpeCertifier.php` (+ pellet)
- Modify: `src/Domain/Simulation/AnnualOutcome.php` + `AnnualOutcomeEstimator.php` (+ `pelletKg`)
- Modify: `src/Application/GameViewFactory.php` (appels CO₂/DPE avec pellet ; affichage)
- Modify: `src/Application/EndReportView.php` (+ granulés consommés)
- Test: `CarbonAccountantTest`, `DpeClassTest`/DPE tests, `AnnualOutcomeEstimatorTest`, `GameViewFactoryTest`

**Interfaces:**
- Produces: `CarbonAccountant::emittedKg(float $fuelOilLitres, float $gridImportKwh, float $pelletKg = 0.0): float`.
- Produces: `DpeCertifier::certify(float $electricityKwh, float $fuelOilLitres, float $pelletKg = 0.0): DpeAssessment`.
- Produces: `AnnualOutcome->pelletKg` (lu par l'estimateur qui somme `$day->heating->pelletKg`).

- [ ] **Step 1: Failing tests** — 100 kg pellets = 460 kWh × 30 g = 13,8 kg CO₂ (`CarbonAccountant`) ; le DPE d'une maison chauffée aux granulés a une bien meilleure étiquette climat qu'au fioul.
```php
    public function testPelletsEmitLittleCo2(): void
    {
        // 100 kg × 4,6 kWh/kg = 460 kWh × 30 g = 13 800 g = 13,8 kg
        self::assertEqualsWithDelta(13.8, (new CarbonAccountant())->emittedKg(0.0, 0.0, 100.0), 1e-9);
    }
```
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: `CarbonAccountant`** — param `float $pelletKg = 0.0` ; ajouter aux grammes :
```php
        $pelletKwh = $pelletKg * $this->energy->pelletEnergyKwhPerKg()->value;
        $grams = $fuelKwh * $this->energy->fuelOilCo2GramsPerKwh()->value
            + $gridImportKwh * $this->energy->electricityCo2GramsPerKwh()->value
            + $pelletKwh * $this->energy->pelletCo2GramsPerKwh()->value;
```
- [ ] **Step 4: `DpeCertifier::certify`** — param `float $pelletKg = 0.0` ; `$pelletKwh = $pelletKg * pelletEnergyKwhPerKg` ; énergie primaire `+ $pelletKwh * pelletPrimaryEnergyFactor` (=×1,0) ; climat `+ $pelletKwh * pelletCo2GramsPerKwh`.
- [ ] **Step 5: Thread `pelletKg` through `AnnualOutcome` + estimator** — l'estimateur somme `$day->heating->pelletKg` sur l'année de référence et le pose sur `AnnualOutcome->pelletKg` ; `GameViewFactory` passe `pelletKg` à `certify(...)` et `emittedKg(...)` (chercher tous les appels — l'estimateur et les totaux vivants).
- [ ] **Step 6: `EndReportView`** — ajouter les granulés consommés sur l'année (`totalPelletKg`) à côté du fioul ; l'afficher dans la carte « Énergie & climat » du bilan (Twig `GameDashboard.html.twig` end-report).
- [ ] **Step 7: Update tests** — `AnnualOutcomeEstimatorTest`, `GameViewFactoryTest` (une maison granulés : DPE climat amélioré vs fioul), `CarbonAccountantTest`.
- [ ] **Step 8: Run → PASS + full suite + `make cs-fix` + demo smoke.**
- [ ] **Step 9: Commit** — `feat(energy): pellets in CO2 footprint and DPE (arbre travaux T4)`

---

## Task 6: Les deux travaux — devis + conseils

**Files:**
- Modify: `src/Domain/Finance/Renovation.php` (+`LowTempEmitters`, +`PelletBoiler`)
- Modify: `src/Domain/Finance/FinanceCalibration.php` (`lowTempEmittersCost`, `pelletBoilerCost`)
- Modify: `src/Domain/Finance/RenovationQuoter.php` (2 devis)
- Modify: `src/Domain/Finance/RenovationAdvisor.php` (2 règles)
- Test: `RenovationQuoterTest`, `RenovationAdvisorTest`, `RenovationHandlerTest`

**Interfaces:**
- Produces: `Renovation::LowTempEmitters` (`'low_temp_emitters'`), `Renovation::PelletBoiler` (`'pellet_boiler'`) ; `isSubsidised()` → true pour les deux.
- Produces: quotes — émetteurs BT dispo si `!lowTempEmitters` ; granulés dispo si `heatingSystem !== PelletBoiler`.

- [ ] **Step 1: Failing quoter + advisor tests**
```php
    public function testLowTempEmittersQuotedUntilInstalled(): void
    {
        $q = (new RenovationQuoter())->quote(Renovation::LowTempEmitters, $this->uninsulatedHousehold());
        self::assertNotNull($q);
        self::assertTrue($q->resultingHousehold->lowTempEmitters);
    }

    public function testPelletBoilerReplacesTheGenerator(): void
    {
        $q = (new RenovationQuoter())->quote(Renovation::PelletBoiler, $this->uninsulatedHousehold());
        self::assertNotNull($q);
        self::assertSame(HeatingSystem::PelletBoiler, $q->resultingHousehold->heatingSystem);
    }
```
Advisor : émetteurs BT en présence d'une PAC → message SCOP ; sans PAC → « utile surtout avec une PAC » ; granulés → info bas-carbone/manuel.
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Enum + subsidy** — ajouter les 2 cas ; `isSubsidised()` : `LowTempEmitters, PelletBoiler => true`.
- [ ] **Step 4: Costs** in `FinanceCalibration` :
```php
    public function lowTempEmittersCost(): Coefficient
    {
        return new Coefficient(value: 6500.0, unit: '€', min: 4000.0, max: 9000.0, source: 'ADEME : plancher chauffant / émetteurs basse température (~100 m²)', reviewedOn: '2026-07-17');
    }

    public function pelletBoilerCost(): Coefficient
    {
        return new Coefficient(value: 14000.0, unit: '€', min: 10000.0, max: 20000.0, source: 'ADEME : chaudière automatique à granulés + silo', reviewedOn: '2026-07-17');
    }
```
- [ ] **Step 5: Quotes** in `RenovationQuoter` — `quote()` match ajoute les 2 cas :
```php
    private function lowTempEmittersQuote(Household $household): ?RenovationQuote
    {
        if ($household->lowTempEmitters) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->lowTempEmittersCost()->value);
        return new RenovationQuote(
            work: Renovation::LowTempEmitters,
            title: 'Émetteurs basse température',
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withLowTempEmitters(true),
        );
    }

    private function pelletBoilerQuote(Household $household): ?RenovationQuote
    {
        if (HeatingSystem::PelletBoiler === $household->heatingSystem) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->pelletBoilerCost()->value);
        return new RenovationQuote(
            work: Renovation::PelletBoiler,
            title: 'Chaudière à granulés',
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withHeatingSystem(HeatingSystem::PelletBoiler),
        );
    }
```
- [ ] **Step 6: Advisor rules** in `RenovationAdvisor::adviceFor` match :
```php
            Renovation::LowTempEmitters => HeatingSystem::HeatPump === $household->heatingSystem
                ? new RenovationAdvice(AdviceLevel::Info, 'Fait passer le SCOP de votre PAC de ~2,5 à ~4,3 : moins d\'électricité pour la même chaleur.')
                : new RenovationAdvice(AdviceLevel::Info, 'Utile surtout avec une pompe à chaleur (améliore fortement son rendement) ; sans effet sur une chaudière.'),
            Renovation::PelletBoiler => new RenovationAdvice(
                AdviceLevel::Info,
                'Combustible bon marché et bas carbone (~30 g/kWh), mais manuel : stockage et chargement du silo.',
            ),
```
(Le `match` de l'advisor devient exhaustif à 10 cas.)
- [ ] **Step 7: Run → PASS + full suite + `make cs-fix`.** Vérifier `RenovationHandlerTest` (le prêt couvre les 2 nouveaux travaux subventionnés).
- [ ] **Step 8: Commit** — `feat(finance): low-temp-emitters and pellet-boiler works + advice (arbre travaux T4)`

---

## Task 7: IHM — le tiroir chauffage

**Files:**
- Modify: `templates/game/panel/_slot.html.twig` (slot `heating` : `worksOfSlot` + entête contexte-décision lire/agir + « ✔ fait »)
- Modify: `templates/components/QuoteCard.html.twig` (icônes `low_temp_emitters`, `pellet_boiler`)
- Modify: `src/Application/GameView.php` + `GameViewFactory.php` (flags « fait » chauffage si besoin : `hasLowTempEmitters`, `heatingSystemLabel`)
- Modify: `templates/components/scene/Boiler.html.twig` (variante granulés, minimal) — optionnel
- Test: `tests/Integration/GameDashboardTest.php`

**Interfaces:**
- Consumes: `game.actions['pellet_boiler'|'low_temp_emitters'|'heat_pump'|'boiler_repair']`.

- [ ] **Step 1: `worksOfSlot` heating** — `heating: ['boiler_repair', 'heat_pump', 'pellet_boiler', 'low_temp_emitters']`.
- [ ] **Step 2: Heating slot lire/agir header** — remplacer l'entête heating (aujourd'hui : fioul/élec/air) par un **entête contexte-décision** court : générateur actuel + (si PAC) SCOP effectif, + bande « ✔ fait » (`heatingSystemLabel`, émetteurs BT si posés). Retirer les indicateurs dupliqués des coins.
- [ ] **Step 3: Icons** in `QuoteCard` — ajouter `low_temp_emitters` (motif serpentin plancher chauffant) et `pellet_boiler` (réutiliser l'asset chaudière, teinte bois) aux branches d'icône.
- [ ] **Step 4: (optionnel) scene** — variante granulés du `Boiler` (classe locale), sinon réutiliser l'asset fioul sans bloquer.
- [ ] **Step 5: Integration test** — sélectionner le slot `heating` d'une partie neuve : le HTML contient « Chaudière à granulés » et « Émetteurs basse température », et le conseil SCOP quand une PAC est posée.
- [ ] **Step 6: `make twig` + full suite → PASS + `make cs-fix`.**
- [ ] **Step 7: Commit** — `feat(ui): heating drawer offers pellet boiler + low-temp emitters (arbre travaux T4)`

---

## Task 8: Vérification bout-en-bout + revue

- [ ] **Step 1: Full qa gate** — `make cs && make twig && make test` vert.
- [ ] **Step 2: Demo across a winter** — `php bin/console app:simulate:demo --days 90 --from 2025-01-01` ; cohérence (granulés : facture combustible + CO₂ faible ; PAC sans émetteurs BT : conso élec plus haute qu'avec).
- [ ] **Step 3: Grep** — plus aucune référence à `heatPumpScop()` (sans suffixe) dans `src/`.
- [ ] **Step 4: Self-review vs spec §3 branche C / §5.3** — émetteurs → SCOP PAC seule ✔, granulés 3ᵉ vecteur (facture/CO₂/DPE) ✔, insensibilité combustion ✔.
- [ ] **Step 5: Backlog** — marquer T4 faite ; T5 (ventilation + production/ECS) suivante.
- [ ] **Step 6: Optional** — `superpowers:requesting-code-review`.

---

## Self-Review du plan (couverture)

- **Émetteurs BT → SCOP PAC (§3 C, §5.3)** → Task 1 ; seule la PAC sensible (combustion insensible : granulés/fioul n'ont pas de branche SCOP). ✔
- **Granulés = 3ᵉ vecteur** → Tasks 2-5 (conversion, facture, totaux, CO₂/DPE). ✔
- **Devis + conseils non prescriptifs** → Task 6 (advisor : émetteurs = payoff SCOP / « surtout avec PAC » ; granulés = bas-carbone/manuel). ✔
- **IHM tiroir chauffage + lire/agir** → Task 7 (comble aussi le différé « entête heating »). ✔
- **Coefficients sourcés (§6)** → Tasks 1-3, 6 (tous `Coefficient`). ✔
- **Bascule SCOP assumée / facture 2 lignes préservée à l'œil** → notes Global Constraints. ✔

Hors périmètre : ventilation (VMC) = T5 ; production/ECS = T5 ; gestes = T6 ; type d'isolant = Phase 5 ; distinction visuelle `glazingMaxed` (Minor différé).
