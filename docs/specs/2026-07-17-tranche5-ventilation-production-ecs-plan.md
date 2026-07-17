# Tranche 5 — Ventilation (VMC) + Production (kit solaire) + ECS (chauffe-eau thermo) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Compléter les branches Ventilation et Production/ECS de l'arbre : (1) **VMC double flux** = 4ᵉ surface d'enveloppe (récupère la chaleur, rouvre le chemin sous 0,50) ; (2) **kit solaire plug-and-play** = entrée production pas chère (faible kWc, sans installateur ni aide) ; (3) **chauffe-eau thermodynamique** = levier ECS (réduit la demande d'eau chaude d'un facteur ~COP).

**Architecture:** Additif, aucun swap de type. `EnvelopeState` gagne `ventilation` (défaut None) → `envelopeLossFactor` intègre la récupération de chaleur. Le kit solaire réutilise le modèle solaire existant (petit `solarKwc`). Le chauffe-eau thermo est modélisé comme une **réduction** de la demande de base (le ballon électrique reste la référence à 10 kWh/j → non régressif) ; `Household->waterHeater` module `EnergyDemandCalculator`.

**Tech Stack:** PHP 8.4 (domaine pur), Symfony UX Twig/LiveComponents, PHPUnit 13.

## Global Constraints

- `declare(strict_types=1)` partout ; `final` ; VOs `final readonly`. `src/Domain/**` PHP pur (aucun import framework).
- Tout coefficient chiffré = `App\Domain\Calibration\Coefficient` (value+unit+min+max+source+reviewedOn). Valeurs validées :
  - VMC double flux : retrait de perte **0,14** (renouvellement d'air ~20 % × récup ~70 %, ADEME) ; coût **6 000 €**.
  - Kit solaire : puissance **0,9 kWc** ; coût **800 €** (produit plug-and-play, sans installateur).
  - ECS : chaleur eau chaude **2,5 kWh/j** (ADEME, ECS élec ~900 kWh/an) ; COP thermo **3,0** (ADEME) ; coût **3 500 €**.
- VMC double flux et chauffe-eau thermo = travaux de performance → **prime + éco-PTZ éligibles**. Kit solaire = production → **ni prime ni prêt** (règle inchangée).
- Identifiants/commentaires anglais ; libellés joueur français. Tests `tests/Unit/...` (valeurs exactes) + `tests/Integration/...`. Chaque brique avec ses tests dans le même commit.
- `make cs`/`make twig`/`make test` verts avant commit (`make stan` = CI). Pas de `@phpstan-ignore`.
- Commits `type(scope): subject`, terminés par `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Branche `docs/arbre-travaux-spec`.

## Décisions de conception actées

- **VMC = 4ᵉ champ de `EnvelopeState`** : `ventilation: Ventilation` (None|DoubleFlow), ajouté **en fin de constructeur, défaut None** (additif). La simple flux est **différée** (aucun effet énergétique modélisable sans modèle d'humidité) — on ne fait que le double flux. La ventilation contribue à `envelopeLossFactor` mais **pas** à la pénalité paroi froide (c'est de l'air, pas une surface).
- **Kit solaire** : travail séparé. Rien → [kit **ou** installation complète] ; kit → upgrade vers complète. Réutilise `solarKwc` (le kit pose ~0,9 kWc, la complète 3,0).
- **Chauffe-eau thermo = réduction de demande** : on NE disséque PAS la base (10 kWh/j reste la référence = ballon électrique). Thermo → `demand -= ecsHeatKwh × (1 − 1/COP)` = 2,5 × (1 − 1/3) ≈ **1,67 kWh/j** d'économie. `Household->waterHeater` (défaut `ElectricTank` → 10 inchangé, non régressif).

---

## File Structure

**Créés :** `src/Domain/Building/Ventilation.php` (enum), `src/Domain/Building/WaterHeater.php` (enum), tests miroirs.
**Modifiés (domaine) :** `EnvelopeState` (+ventilation), `BuildingCalibration` (ventilation loss), `Household` (+waterHeater), `EnergyDemandCalculator` (+waterHeater), `EnergyCalibration` (ecsHeat, thermoCop, solarKit kWc), `SimulationEngine` (passe waterHeater), `Renovation` (+3 cas), `FinanceCalibration` (3 coûts), `RenovationQuoter` (3 devis + upgrade solaire), `RenovationAdvisor` (3 règles).
**Modifiés (app/IHM) :** `SessionGameStore` (ventilation + waterHeater + FORMAT_VERSION), `GameView`/`GameViewFactory` (flags « fait »), `PrimoAccedantScenario` (défauts), `_slot.html.twig` (walls/roof/garage), `QuoteCard` (icônes), `tests/Integration/GameDashboardTest.php`.

---

## Task 1: Ventilation — 4ᵉ surface d'enveloppe

**Files:**
- Create: `src/Domain/Building/Ventilation.php`
- Modify: `src/Domain/Building/EnvelopeState.php` (+`ventilation`)
- Modify: `src/Domain/Building/BuildingCalibration.php` (`ventilationHeatRecoveryLossReduction` + `envelopeLossFactor`)
- Modify: `src/Application/SessionGameStore.php` (sérialise ventilation + bump FORMAT_VERSION)
- Test: `EnvelopeStateTest`, `BuildingCalibrationTest`, `SessionGameStoreTest`

**Interfaces:**
- Produces: `enum Ventilation: string { case None='none'; case DoubleFlow='double_flow'; public function label(): string; }` (labels 'Aucune (naturelle)' / 'VMC double flux').
- Produces: `EnvelopeState->ventilation: Ventilation` (défaut None) + `withVentilation(Ventilation): self`.
- Produces: `BuildingCalibration::ventilationHeatRecoveryLossReduction(): Coefficient` (0.14) ; `envelopeLossFactor` retire 0.14 de plus si `ventilation === DoubleFlow`.

- [ ] **Step 1: Failing tests**
```php
    // BuildingCalibrationTest
    public function testDoubleFlowVentilationRecoversHeat(): void
    {
        $bare = new EnvelopeState(false, WallInsulation::None, Glazing::Single, Ventilation::None);
        $vmc = new EnvelopeState(false, WallInsulation::None, Glazing::Single, Ventilation::DoubleFlow);
        self::assertEqualsWithDelta(1.0, $this->calibration->envelopeLossFactor($bare), 1e-9);
        self::assertEqualsWithDelta(0.86, $this->calibration->envelopeLossFactor($vmc), 1e-9); // 1 − 0,14
    }

    public function testVentilationReopensThePathBelowHalf(): void
    {
        $full = new EnvelopeState(true, WallInsulation::Exterior, Glazing::Triple, Ventilation::DoubleFlow);
        self::assertEqualsWithDelta(0.36, $this->calibration->envelopeLossFactor($full), 1e-9); // 0,50 − 0,14
    }
```
(Note : les constructions `EnvelopeState(false, None, Single)` existantes restent valides via le défaut `Ventilation::None` — mais ces deux tests passent le 4ᵉ argument explicitement.)

- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Create `Ventilation`** (enum + label).
- [ ] **Step 4: Add `ventilation` to `EnvelopeState`** — param **en fin** `public Ventilation $ventilation = Ventilation::None` ; ajouter `withVentilation()` et **threader le 4ᵉ champ dans les 3 `with*` existants** (`withRoofInsulated`/`withWalls`/`withGlazing` doivent passer `$this->ventilation`).
- [ ] **Step 5: Coefficient + aggregation** in `BuildingCalibration` :
```php
    /** VMC double flux : récupère la chaleur de l'air extrait (le renouvellement d'air ~20 % des pertes, récup ~70 %). */
    public function ventilationHeatRecoveryLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.14, unit: 'fraction', min: 0.10, max: 0.18, source: 'ADEME : VMC double flux, récupération ~70-90 % sur le renouvellement d\'air (~20 % des déperditions)', reviewedOn: '2026-07-17');
    }
```
Dans `envelopeLossFactor`, ajouter au `$removed` : `+= Ventilation::DoubleFlow === $envelope->ventilation ? $this->ventilationHeatRecoveryLossReduction()->value : 0.0;`
- [ ] **Step 6: Serialize** in `SessionGameStore` — `'ventilation' => ...->envelope->ventilation->value` (dehydrate) et `ventilation: Ventilation::from((string) ($data['ventilation'] ?? Ventilation::None->value))` dans la reconstruction de l'`EnvelopeState` ; **bump FORMAT_VERSION**. Ajouter un cas de round-trip.
- [ ] **Step 7: Update `EnvelopeStateTest`** (nouveau champ + withVentilation). Run full suite + `make cs-fix`.
- [ ] **Step 8: Commit** — `feat(building): heat-recovery ventilation as a 4th envelope surface (arbre travaux T5)`

---

## Task 2: VMC — travail + conseil

**Files:** `Renovation` (+`VentilationDoubleFlow`), `FinanceCalibration` (`ventilationDoubleFlowCost`), `RenovationQuoter` (devis), `RenovationAdvisor` (règle). Tests : `RenovationQuoterTest`, `RenovationAdvisorTest`.

**Interfaces:** `Renovation::VentilationDoubleFlow` (`'ventilation_double_flow'`), `isSubsidised()` true. Quote dispo si `ventilation === None` → `withEnvelope($envelope->withVentilation(DoubleFlow))`.

- [ ] **Step 1: Failing tests** — devis dispo si pas de VMC, null sinon ; advisor : ⚠ si enveloppe peu isolée (`f > 0,70`, réutiliser `poorlyInsulatedEnvelopeCeiling`) « à poser après avoir isolé », sinon 💡.
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Enum + subsidy** (arm dans les 3 `match(Renovation)` : quote, advisor, isSubsidised).
- [ ] **Step 4: Cost** `ventilationDoubleFlowCost()` (6000 €, source ADEME).
- [ ] **Step 5: Quote** :
```php
    private function ventilationQuote(Household $household): ?RenovationQuote
    {
        if (Ventilation::None !== $household->envelope->ventilation) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->ventilationDoubleFlowCost()->value);
        return new RenovationQuote(
            work: Renovation::VentilationDoubleFlow,
            title: 'VMC double flux',
            cost: $price,
            subsidy: $this->subsidy->subsidyFor($price),
            resultingHousehold: $household->withEnvelope($household->envelope->withVentilation(Ventilation::DoubleFlow)),
        );
    }
```
- [ ] **Step 6: Advisor rule** :
```php
            Renovation::VentilationDoubleFlow => $poorlyInsulated
                ? new RenovationAdvice(AdviceLevel::Caution, 'À poser plutôt APRÈS l\'isolation : la VMC double flux récupère la chaleur, autant qu\'il y en ait à récupérer.')
                : new RenovationAdvice(AdviceLevel::Info, 'Récupère la chaleur de l\'air extrait et renouvelle l\'air sainement.'),
```
- [ ] **Step 7: Run → PASS + full suite + `make cs-fix`.**
- [ ] **Step 8: Commit** — `feat(finance): double-flow ventilation work + advice (arbre travaux T5)`

---

## Task 3: Kit solaire plug-and-play

**Files:** `EnergyCalibration` (`solarKitPeakPowerKwc`), `FinanceCalibration` (`solarKitInstallCost`), `Renovation` (+`SolarKit`), `RenovationQuoter` (devis kit + upgrade complète), `RenovationAdvisor` (règle). Tests : `RenovationQuoterTest`, `RenovationAdvisorTest`.

**Interfaces:** `Renovation::SolarKit` (`'solar_kit'`), non subventionné. Kit dispo si `solarKwc === 0.0` → pose `solarKitPeakPowerKwc` (0.9). `SolarPanels` (complète, existant) : passer la garde de `solarKwc > 0` à `solarKwc >= defaultSolarPeakPowerKwc` pour l'offrir en **upgrade** depuis le kit.

- [ ] **Step 1: Failing tests** — kit dispo maison nue (résultat solarKwc 0.9) ; après kit, `SolarKit` null mais `SolarPanels` dispo (upgrade → 3.0) ; après complète, les deux null.
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Coefficients** — `EnergyCalibration::solarKitPeakPowerKwc()` (0.9 kWc, source produit) ; `FinanceCalibration::solarKitInstallCost()` (800 €, source produit).
- [ ] **Step 4: Enum + subsidy** — `SolarKit` : `isSubsidised()` **false** (production).
- [ ] **Step 5: Quote + upgrade** — nouveau `solarKitQuote` (dispo si `solarKwc === 0.0`, pose `solarKitPeakPowerKwc`) ; modifier `solarQuote` (complète) : garde `if ($household->solarKwc >= $this->energy->defaultSolarPeakPowerKwc()->value) return null;` (au lieu de `> 0.0`) pour l'upgrade depuis le kit.
- [ ] **Step 6: Advisor rule** — `SolarKit` : 💡 « Le premier pas accessible : sans installateur ni aide, rendement modeste. » (garder l'`SolarPanels` existant).
- [ ] **Step 7: Run → PASS + full suite + `make cs-fix`.** Vérifier `RenovationHandlerTest`/`GameViewFactoryTest` (le kit apparaît dans les actions d'une maison nue).
- [ ] **Step 8: Commit** — `feat(finance): plug-and-play solar kit as a cheap entry point (arbre travaux T5)`

---

## Task 4: ECS — chauffe-eau, découpe de la demande

**Files:** `src/Domain/Building/WaterHeater.php` (enum), `Household` (+`waterHeater`), `EnergyCalibration` (`householdDailyEcsHeatKwh`, `waterHeaterThermodynamicCop`), `EnergyDemandCalculator` (+`waterHeater`), `SimulationEngine` (passe waterHeater), `SessionGameStore` (sérialise), `PrimoAccedantScenario` (défaut). Tests : `EnergyDemandCalculatorTest`, `HouseholdTest`, `SessionGameStoreTest`, `SimulationEngineTest`.

**Interfaces:**
- Produces: `enum WaterHeater: string { case ElectricTank='electric_tank'; case Thermodynamic='thermodynamic'; public function label(): string; }` (labels 'Ballon électrique' / 'Chauffe-eau thermodynamique').
- Produces: `Household->waterHeater: WaterHeater` (défaut ElectricTank) + `withWaterHeater`.
- Produces: `EnergyDemandCalculator::dailyDemandKwh(int $seed, GameDate $date, WaterHeater $waterHeater = WaterHeater::ElectricTank): float`.
- Produces: `EnergyCalibration::householdDailyEcsHeatKwh()` (2.5), `waterHeaterThermodynamicCop()` (3.0).

- [ ] **Step 1: Failing test** — l'écart de demande entre ballon élec et thermo = économie ECS, à (seed, date) fixés :
```php
    public function testThermodynamicWaterHeaterCutsEcsElectricity(): void
    {
        $calc = new EnergyDemandCalculator();
        $date = GameDate::fromDayIndex(new DateTimeImmutable('2025-06-15'), 100);
        $electric = $calc->dailyDemandKwh(42, $date, WaterHeater::ElectricTank);
        $thermo = $calc->dailyDemandKwh(42, $date, WaterHeater::Thermodynamic);
        // économie = 2,5 × (1 − 1/3) = 1,666… → arrondi 2 décimales sur chaque terme
        self::assertEqualsWithDelta(1.67, $electric - $thermo, 0.02);
    }

    public function testElectricTankKeepsTheBaselineDemand(): void
    {
        // le ballon électrique NE change PAS la demande (référence à 10 kWh) → non régressif
        $calc = new EnergyDemandCalculator();
        $date = GameDate::fromDayIndex(new DateTimeImmutable('2025-06-15'), 100);
        self::assertSame(
            $calc->dailyDemandKwh(42, $date, WaterHeater::ElectricTank),
            $calc->dailyDemandKwh(42, $date), // défaut = ElectricTank
        );
    }
```
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Create `WaterHeater`** (enum + label).
- [ ] **Step 4: Coefficients** — `householdDailyEcsHeatKwh()` (2.5 kWh/j, source ADEME ECS élec ~900 kWh/an) ; `waterHeaterThermodynamicCop()` (3.0, source ADEME).
- [ ] **Step 5: Reduce demand for thermo** in `EnergyDemandCalculator` — signature `+ WaterHeater $waterHeater = WaterHeater::ElectricTank` ; après le calcul de `$demand` :
```php
        if (WaterHeater::Thermodynamic === $waterHeater) {
            $ecsSaving = $this->calibration->householdDailyEcsHeatKwh()->value
                * (1.0 - 1.0 / $this->calibration->waterHeaterThermodynamicCop()->value);
            $demand -= $ecsSaving;
        }

        return round($demand, 2);
```
- [ ] **Step 6: Add `waterHeater` to `Household`** — param **en fin**, défaut `WaterHeater::ElectricTank` ; threader dans tous les `with*` ; ajouter `withWaterHeater`.
- [ ] **Step 7: Pass it in `SimulationEngine::snapshot`** — `$this->baseDemand->dailyDemandKwh($config->seed, $date, $household->waterHeater)`.
- [ ] **Step 8: Serialize** in `SessionGameStore` (`'waterHeater' => ...`, `waterHeater: WaterHeater::from(...)`) ; FORMAT_VERSION déjà bumpé en Task 1 (un seul bump pour la tranche suffit — vérifier). `PrimoAccedantScenario` : défaut ElectricTank (rien à passer si le défaut du constructeur suffit).
- [ ] **Step 9: Run → PASS + full suite + `make cs-fix` + demo smoke.**
- [ ] **Step 10: Commit** — `feat(building): water-heater type modulates ECS electricity demand (arbre travaux T5)`

---

## Task 5: Chauffe-eau thermo — travail + conseil

**Files:** `Renovation` (+`WaterHeaterThermo`), `FinanceCalibration` (`waterHeaterThermoCost`), `RenovationQuoter` (devis), `RenovationAdvisor` (règle). Tests : `RenovationQuoterTest`, `RenovationAdvisorTest`.

**Interfaces:** `Renovation::WaterHeaterThermo` (`'water_heater_thermo'`), `isSubsidised()` true. Quote dispo si `waterHeater !== Thermodynamic` → `withWaterHeater(Thermodynamic)`.

- [ ] **Step 1: Failing tests** — devis dispo tant que pas thermo, null sinon ; advisor 💡.
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Enum + subsidy** (3 `match(Renovation)`).
- [ ] **Step 4: Cost** `waterHeaterThermoCost()` (3500 €, ADEME).
- [ ] **Step 5: Quote** (dispo si `WaterHeater::Thermodynamic !== $household->waterHeater`, → `withWaterHeater(Thermodynamic)`).
- [ ] **Step 6: Advisor** — 💡 « L'eau chaude = ~15 % de l'énergie, souvent oubliée : le thermodynamique divise sa conso par ~3. »
- [ ] **Step 7: Run → PASS + full suite + `make cs-fix`.**
- [ ] **Step 8: Commit** — `feat(finance): thermodynamic water-heater work + advice (arbre travaux T5)`

---

## Task 6: IHM — câbler les 3 travaux

**Files:** `templates/game/panel/_slot.html.twig` (walls: +VMC ; roof: +kit ; garage: +chauffe-eau), `templates/components/QuoteCard.html.twig` (3 icônes), `GameView`/`GameViewFactory` (flags « fait » : `hasHeatRecoveryVentilation`, `hasSolar`/kWc déjà là, `waterHeaterLabel`), `tests/Integration/GameDashboardTest.php`.

- [ ] **Step 1: `worksOfSlot`** — `walls: [... , 'ventilation_double_flow']` ; `roof: ['solar_kit', 'solar_panels']` ; `garage: ['water_heater_thermo', 'home_battery']`.
- [ ] **Step 2: Read/act headers + done-strips** — walls : ajouter « ✔ VMC double flux » si posée ; roof : entête contexte-décision (production du jour / kWc installés) + « ✔ » selon kit/complète ; garage : entête (autoconsommation) + « ✔ » chauffe-eau/batterie. Combler le différé lire/agir des slots roof/garage. Exposer sur `GameView` les flags manquants (`hasHeatRecoveryVentilation`, `waterHeaterLabel`) remplis dans `GameViewFactory`.
- [ ] **Step 3: Icons** in `QuoteCard` — `ventilation_double_flow` (flux d'air / échangeur), `solar_kit` (petit panneau + prise), `water_heater_thermo` (ballon + ondes). Style inline-SVG existant.
- [ ] **Step 4: Integration test** — maison nue : le slot `roof` montre « Kit solaire », le slot `garage` montre « Chauffe-eau thermodynamique », le slot `walls` montre « VMC double flux ».
- [ ] **Step 5: `make twig` + full suite → PASS + `make cs-fix`.**
- [ ] **Step 6: Commit** — `feat(ui): drawers offer VMC, solar kit and thermodynamic water heater (arbre travaux T5)`

---

## Task 7: Vérification bout-en-bout + revue

- [ ] **Step 1: Full qa gate** — `make cs && make twig && make test` vert.
- [ ] **Step 2: Demo** — `php bin/console app:simulate:demo --days 90 --from 2025-01-01` ; cohérence (VMC → besoin de chauffage réduit ; chauffe-eau thermo → demande élec réduite ; kit → petite production).
- [ ] **Step 3: Grep** — plus de `householdDailyBaseDemandKwh` cassé ; les 3 nouveaux `Renovation` couverts dans les 3 `match`.
- [ ] **Step 4: Self-review vs spec §3 (B, D)** — VMC 4ᵉ surface + rouvre <0,50 ✔ ; kit solaire entrée pas chère ✔ ; ECS thermo réduction ✔ ; aides (VMC/thermo subventionnés, kit non) ✔.
- [ ] **Step 5: Backlog** — marquer T5 faite ; **T6 (gestes) = dernière tranche de l'arbre resserré**.
- [ ] **Step 6: Final whole-branch review** (opus) + fixes éventuels.

---

## Self-Review du plan (couverture)

- **Ventilation (§3 B)** → Tasks 1-2 (VMC double flux, 4ᵉ surface, rouvre <0,50, advisor « après isolation »). Simple flux différée (assumé). ✔
- **Production — kit plug-and-play (§3 D)** → Task 3 (entrée pas chère, upgrade vers complète, non subventionné). ✔
- **ECS — chauffe-eau thermo (§3 D)** → Tasks 4-5 (réduction de demande, défaut ballon élec non régressif). ✔
- **Conseils non prescriptifs** → Tasks 2/3/5 (VMC caution avant isolation ; kit/thermo info). ✔
- **IHM (walls/roof/garage) + lire/agir roof/garage** → Task 6 (comble un différé). ✔
- **Coefficients sourcés (§6)** → tous `Coefficient`. ✔

Hors périmètre : simple flux VMC ; conso par usage (électroménager/veille — backlog « consommation par usage ») ; gestes = T6 ; type d'isolant = Phase 5.
