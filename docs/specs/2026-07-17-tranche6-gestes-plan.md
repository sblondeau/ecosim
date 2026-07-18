# Tranche 6 — Gestes du quotidien (rideaux thermiques + calfeutrage) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Ajouter les **gestes du quotidien** — bon marché, honnêtement petits — qui bouclent l'arbre resserré : **calfeutrage / joints** (coupe les courants d'air, petit retrait de déperdition) et **rideaux thermiques** (petit gain de confort ressenti, ~zéro kWh). L'anti-théâtre est **dans les nombres** (volontairement petits vs les gros travaux) **et dans les conseils** (qui nomment le petit levier).

**Architecture:** Additif. `EnvelopeState` gagne `draughtProofed` et `thermalCurtains` (défaut false). Le calfeutrage contribue à `envelopeLossFactor` ; les rideaux réduisent la pénalité paroi froide (`coldWallPenaltyFactor`, plancher inchangé). Deux travaux non subventionnés, conseils Info honnêtes. Zone : le séjour (comble le dernier différé lire/agir du slot `living`).

**Tech Stack:** PHP 8.4 (domaine pur), Symfony UX Twig/LiveComponents, PHPUnit 13.

## Global Constraints

- `declare(strict_types=1)` partout ; `final` ; VOs `final readonly`. `src/Domain/**` PHP pur.
- Tout coefficient chiffré = `App\Domain\Calibration\Coefficient`. Valeurs validées :
  - Calfeutrage : retrait de perte **0,04** (min 0,02 max 0,06) ; coût **80 €**.
  - Rideaux thermiques : baisse paroi froide **0,02** (min 0,01 max 0,03) ; coût **120 €**.
- Les deux gestes = **ni prime ni éco-PTZ** (trop petits, pas des travaux de performance) → `isSubsidised()` false pour les deux.
- Identifiants/commentaires anglais ; libellés joueur français. Tests `tests/Unit/...` + `tests/Integration/...`. Chaque brique avec ses tests dans le même commit.
- `make cs`/`make twig`/`make test` verts avant commit (`make stan` = CI). Pas de `@phpstan-ignore`.
- Commits `type(scope): subject`, terminés par `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Branche `docs/arbre-travaux-spec`.
- **Anti-théâtre (principe pédagogique, décision joueur)** : les magnitudes sont **honnêtement petites** (calfeutrage 0,04 vs combles 0,24 ; rideaux 0,02 vs murs isolés 0,08) ; les conseils **nomment le petit levier** et ne survendent pas. Les effets chiffrés (« ≈ X €/an », Δ confort) s'afficheront naturellement minuscules → le joueur voit le contraste.

## Décisions de conception actées

- `EnvelopeState` : +`draughtProofed` (bool) +`thermalCurtains` (bool), **en fin de constructeur, défaut false** (additif). `EnvelopeState` a déjà 4 champs (roof/walls/glazing/ventilation) et 4 withers → il en aura 6 : **threader les 6 champs dans les 6 withers** (piège des withers).
- Calfeutrage → `envelopeLossFactor` (déperdition, comme une petite surface). Rideaux → `coldWallPenaltyFactor` (paroi froide, confort). Les rideaux ne touchent PAS `envelopeLossFactor` (rideaux = confort de nuit, pas d'isolation mesurable en continu).
- `SessionGameStore` : sérialiser les 2 champs ; **bump FORMAT_VERSION** (une fois pour la tranche).

---

## Task 1: Gestes — champs d'enveloppe + calibration

**Files:**
- Modify: `src/Domain/Building/EnvelopeState.php` (+2 champs, +2 withers, threader les 6)
- Modify: `src/Domain/Building/BuildingCalibration.php` (`draughtProofingLossReduction` + `thermalCurtainsColdWallRelief` ; wiring dans `envelopeLossFactor` + `coldWallPenaltyFactor`)
- Modify: `src/Application/SessionGameStore.php` (sérialise + bump FORMAT_VERSION)
- Test: `EnvelopeStateTest`, `BuildingCalibrationTest`, `SessionGameStoreTest`

**Interfaces:**
- Produces: `EnvelopeState->draughtProofed: bool`, `->thermalCurtains: bool` (défaut false) + `withDraughtProofed(bool)`, `withThermalCurtains(bool)`.
- Produces: `BuildingCalibration::draughtProofingLossReduction(): Coefficient` (0.04) ; `thermalCurtainsColdWallRelief(): Coefficient` (0.02). `envelopeLossFactor` retire 0.04 de plus si `draughtProofed` ; `coldWallPenaltyFactor` retire 0.02 de plus si `thermalCurtains` (planché comme aujourd'hui à `coldWallPenaltyFloor` 0.02).

- [ ] **Step 1: Failing tests**
```php
    // BuildingCalibrationTest
    public function testDraughtProofingRemovesASmallShareOfLoss(): void
    {
        $bare = new EnvelopeState(false, WallInsulation::None, Glazing::Single, Ventilation::None);
        $sealed = $bare->withDraughtProofed(true);
        self::assertEqualsWithDelta(1.0, $this->calibration->envelopeLossFactor($bare), 1e-9);
        self::assertEqualsWithDelta(0.96, $this->calibration->envelopeLossFactor($sealed), 1e-9); // 1 − 0,04
    }

    public function testThermalCurtainsEaseTheColdWallSlightly(): void
    {
        $bare = new EnvelopeState(false, WallInsulation::None, Glazing::Single, Ventilation::None);
        $curtained = $bare->withThermalCurtains(true);
        // base 0,15 → 0,13 (les rideaux retirent 0,02 ; plancher 0,02 non atteint)
        self::assertEqualsWithDelta(0.15, $this->calibration->coldWallPenaltyFactor($bare), 1e-9);
        self::assertEqualsWithDelta(0.13, $this->calibration->coldWallPenaltyFactor($curtained), 1e-9);
    }
```
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Add the 2 fields to `EnvelopeState`** — params **en fin** `public bool $draughtProofed = false`, `public bool $thermalCurtains = false` ; ajouter `withDraughtProofed`/`withThermalCurtains` ; **threader les 6 champs dans les 6 withers** (les 4 existants doivent passer `$this->draughtProofed, $this->thermalCurtains`).
- [ ] **Step 4: Coefficients + wiring** in `BuildingCalibration` :
```php
    /** Calfeutrage / joints : coupe les courants d'air. Petit geste — quelques % des pertes (vs combles 24 %). */
    public function draughtProofingLossReduction(): Coefficient
    {
        return new Coefficient(value: 0.04, unit: 'fraction', min: 0.02, max: 0.06, source: 'ADEME : fuites d\'air ~20 % des déperditions cumulées ; calfeutrage/joints de base = quelques %', reviewedOn: '2026-07-17');
    }

    /** Rideaux thermiques : coupent le rayonnement froid des fenêtres la nuit. Petit gain de ressenti. */
    public function thermalCurtainsColdWallRelief(): Coefficient
    {
        return new Coefficient(value: 0.02, unit: 'fraction', min: 0.01, max: 0.03, source: 'ADEME : rideaux thermiques, petit gain de température ressentie près des vitres', reviewedOn: '2026-07-17');
    }
```
Dans `envelopeLossFactor`, ajouter au `$removed` : `+= $envelope->draughtProofed ? $this->draughtProofingLossReduction()->value : 0.0;`
Dans `coldWallPenaltyFactor`, avant le plancher `max(...)`, ajouter : `if ($envelope->thermalCurtains) { $penalty -= $this->thermalCurtainsColdWallRelief()->value; }`
- [ ] **Step 5: Serialize** in `SessionGameStore` — `'draughtProofed'`/`'thermalCurtains'` dans le bloc envelope (dehydrate) + `draughtProofed: (bool) ($data['draughtProofed'] ?? false)`, `thermalCurtains: (bool) ($data['thermalCurtains'] ?? false)` dans la reconstruction de l'`EnvelopeState` ; **bump FORMAT_VERSION** (13 → 14). Round-trip test.
- [ ] **Step 6: Update `EnvelopeStateTest`** — étendre le test de préservation des withers pour couvrir les 2 nouveaux champs (le piège : `withRoofInsulated` etc. doivent préserver draughtProofed/thermalCurtains). Run full suite + `make cs-fix`.
- [ ] **Step 7: Commit** — `feat(building): draught-proofing and thermal-curtains gestures on the envelope (arbre travaux T6)`

---

## Task 2: Les 2 gestes — travaux + conseils honnêtes

**Files:** `Renovation` (+`DraughtProofing`, +`ThermalCurtains`), `FinanceCalibration` (2 coûts), `RenovationQuoter` (2 devis), `RenovationAdvisor` (2 règles). Tests : `RenovationQuoterTest`, `RenovationAdvisorTest`.

**Interfaces:** `Renovation::DraughtProofing` (`'draught_proofing'`), `Renovation::ThermalCurtains` (`'thermal_curtains'`) ; `isSubsidised()` **false** pour les deux. Devis dispo si le geste n'est pas déjà fait.

- [ ] **Step 1: Failing tests** — devis dispo tant que non posé, null sinon ; advisor Info honnête (messages ci-dessous).
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Enum + subsidy** — les 2 cas ; `isSubsidised()` : `DraughtProofing, ThermalCurtains => false` (arm dans les 3 `match(Renovation)`).
- [ ] **Step 4: Costs** :
```php
    public function draughtProofingCost(): Coefficient
    {
        return new Coefficient(value: 80.0, unit: '€', min: 40.0, max: 150.0, source: 'ADEME / produits : joints de fenêtres, boudins de porte, mastic', reviewedOn: '2026-07-17');
    }

    public function thermalCurtainsCost(): Coefficient
    {
        return new Coefficient(value: 120.0, unit: '€', min: 60.0, max: 250.0, source: 'Produits : rideaux thermiques doublés (par fenêtre × quelques ouvertures)', reviewedOn: '2026-07-17');
    }
```
- [ ] **Step 5: Quotes** :
```php
    private function draughtProofingQuote(Household $household): ?RenovationQuote
    {
        if ($household->envelope->draughtProofed) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->draughtProofingCost()->value);
        return new RenovationQuote(
            work: Renovation::DraughtProofing,
            title: 'Calfeutrage / joints',
            cost: $price,
            subsidy: Money::zero(),
            resultingHousehold: $household->withEnvelope($household->envelope->withDraughtProofed(true)),
        );
    }

    private function thermalCurtainsQuote(Household $household): ?RenovationQuote
    {
        if ($household->envelope->thermalCurtains) {
            return null;
        }
        $price = Money::fromEuros($this->calibration->thermalCurtainsCost()->value);
        return new RenovationQuote(
            work: Renovation::ThermalCurtains,
            title: 'Rideaux thermiques',
            cost: $price,
            subsidy: Money::zero(),
            resultingHousehold: $household->withEnvelope($household->envelope->withThermalCurtains(true)),
        );
    }
```
(Non subventionnés → `subsidy: Money::zero()`, pas d'appel à `SubsidyCalculator`.)
- [ ] **Step 6: Advisor rules** (Info honnêtes, verbatim) :
```php
            Renovation::DraughtProofing => new RenovationAdvice(
                AdviceLevel::Info,
                'Geste bon marché : coupe les courants d\'air (quelques % de pertes). Utile en complément — pas un gros levier.',
            ),
            Renovation::ThermalCurtains => new RenovationAdvice(
                AdviceLevel::Info,
                'Geste bon marché : un peu de confort près des fenêtres la nuit. Petit levier, pas un substitut à l\'isolation.',
            ),
```
- [ ] **Step 7: Run → PASS + full suite + `make cs-fix`.**
- [ ] **Step 8: Commit** — `feat(finance): low-cost gestures (draught-proofing, thermal curtains) + honest advice (arbre travaux T6)`

---

## Task 3: IHM — le séjour propose les gestes

**Files:** `templates/game/panel/_slot.html.twig` (slot `living` : `worksOfSlot` + entête lire/agir + « ✔ fait »), `templates/components/QuoteCard.html.twig` (2 icônes), `GameView`/`GameViewFactory` (flags « fait » si besoin), `tests/Integration/GameDashboardTest.php`.

- [ ] **Step 1: `worksOfSlot` living** — `living: ['draught_proofing', 'thermal_curtains']` (aujourd'hui `living: []`).
- [ ] **Step 2: Living slot — garder le thermostat + confort, ajouter les gestes** — le slot `living` a déjà le thermostat et le confort (contexte de décision légitime, à garder). Ajouter les 2 `QuoteCard` (via la boucle `worksOfSlot`) + une bande « ✔ fait » (calfeutrage / rideaux si posés). Exposer sur `GameView` `hasDraughtProofing`/`hasThermalCurtains` (remplis dans `GameViewFactory` depuis `$household->envelope`) si nécessaire pour la bande ✔.
- [ ] **Step 3: Icons** in `QuoteCard` — `draught_proofing` (fenêtre + joint / flèches d'air stoppées), `thermal_curtains` (fenêtre + rideau). Inline-SVG, style existant.
- [ ] **Step 4: Integration test** — maison nue : sélectionner le slot `living` rend « Calfeutrage / joints » et « Rideaux thermiques ». RED sans le câblage.
- [ ] **Step 5: `make twig` + full suite → PASS + `make cs-fix`.**
- [ ] **Step 6: Commit** — `feat(ui): the living-room panel offers the daily gestures (arbre travaux T6)`

---

## Task 4: Vérification + revue — l'arbre resserré est complet

- [ ] **Step 1: Full qa gate** — `make cs && make twig && make test` vert.
- [ ] **Step 2: Demo** — `php bin/console app:simulate:demo --days 60 --from 2025-01-01` ; cohérence (calfeutrage → besoin de chauffage légèrement réduit ; rideaux → confort légèrement amélioré ; effets **petits** comparés aux gros travaux = l'anti-théâtre visible).
- [ ] **Step 3: Grep** — les 2 nouveaux `Renovation` couverts dans les 3 `match`.
- [ ] **Step 4: Self-review vs spec §3 (E) + principe anti-théâtre** — gestes honnêtement petits ✔ ; conseils qui nomment le petit levier ✔ ; non subventionnés ✔.
- [ ] **Step 5: Backlog** — marquer T6 faite ; **arbre resserré COMPLET** (T1→T6). Rappeler les pistes futures : carbone gris + hiérarchie des leviers (piste « consommation par usage »), lire/agir du slot living désormais fait, visuels de scène (pellet/VMC) restants.
- [ ] **Step 6: Final whole-branch review** (opus) + fixes éventuels. L'arbre resserré étant complet, c'est aussi le moment de router la branche (skill `finishing-a-development-branch`).

---

## Self-Review du plan (couverture)

- **Gestes (§3 E : rideaux, calfeutrage)** → Tasks 1-2. ✔
- **Anti-théâtre (magnitudes honnêtes + conseils non survendeurs)** → coefficients petits (Task 1) + conseils Info qui nomment le petit levier (Task 2) + effets chiffrés naturellement minuscules. ✔
- **Non subventionnés** → Task 2 (`isSubsidised` false, pas de prime). ✔
- **IHM séjour + dernier lire/agir (living)** → Task 3. ✔
- **Coefficients sourcés (§6)** → tous `Coefficient`. ✔

Hors périmètre (pistes futures, backlog) : **carbone gris** (fabrication) + **hiérarchie des leviers explicite** (« consommation par usage ») ; électroménager / veille / usages ; type d'isolant (Phase 5) ; VMC simple flux ; visuels de scène pellet/VMC ; distinction visuelle `glazingMaxed`. **Après T6, l'arbre resserré (T1→T6) est complet.**
