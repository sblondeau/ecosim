# Tranche 2+3 — Conseils (RenovationAdvisor) + tiroir latéral scrollable — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Rendre l'arbre de travaux **lisible et pédagogique sans le rendre directif** : chaque travail disponible porte un conseil (💡 repère neutre / ⚠ déconseillé-maintenant pour les vraies erreurs), et les travaux s'affichent dans un **tiroir latéral scrollable** par zone, avec séparation *lire/agir* et une bande « ✔ déjà fait ».

**Architecture:** Un service domaine pur `RenovationAdvisor` calcule, pour un travail + un `Household`, un `RenovationAdvice` (niveau + message) — **jamais** un « fais ça maintenant » unique (pas de ★, pas de halo de scène : décision de game design, on préserve les vrais choix). Les seuils (facteur d'enveloppe) sont des coefficients de **calibration de jeu**, étiquetés comme tels. `ActionView` porte le conseil ; `GameViewFactory` le remplit. Le panneau de zone devient un tiroir latéral scrollable (`.float-panel.at-drawer`), le template `_slot.html.twig` sépare lire (entête contexte-décision) et agir (devis + conseils + ✔ fait).

**Tech Stack:** PHP 8.4 (domaine pur), Symfony UX Twig/LiveComponents, AssetMapper (CSS via `<link>`, pas de bundler), PHPUnit 13.

## Global Constraints

- `declare(strict_types=1)` partout ; `final` ; VOs `final readonly`. (CLAUDE.md §7)
- `src/Domain/**` = PHP pur, aucun import framework. (§3)
- Tout coefficient chiffré = un `App\Domain\Calibration\Coefficient` (value+unit+min+max+source+reviewedOn) ; les seuils d'advisor sont de la **calibration de jeu**, source honnêtement libellée « calibration » (pas de fausse physique). (§6)
- Identifiants/commentaires en anglais ; messages joueur en **français**.
- Composants d'UI = Twig anonymes / `<twig:QuoteCard>` ; le LiveComponent reste `GameDashboard`. Pas de macro pour un composant. (§7)
- CSS : variables `:root` plutôt que littéraux répétés ; le graphisme via classes. (§7)
- Tests : domaine = unitaires purs `tests/Unit/...` (valeurs exactes, déterministe) ; IHM = intégration LiveComponent `tests/Integration/...`. Chaque brique avec ses tests dans le même commit. (§5)
- `make cs`/`make twig`/`make test` verts avant chaque commit (`make stan` = CI en sandbox web). Pas de `@phpstan-ignore`/baseline. (§4)
- Commits `type(scope): subject` anglais, terminés par `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Branche `docs/arbre-travaux-spec`.
- **Principe de guidage (décision validée)** : les conseils **informent** l'arbitrage et **signalent les vraies erreurs de séquence**, ils ne **prescrivent pas** un parcours. Deux niveaux seulement : `Info` (repère neutre) et `Caution` (déconseillé maintenant). Non bloquant : tout reste cliquable. Le seul « guidage » du ⚠ porte sur *éviter le gaspillage*, pas sur *imposer un ordre*.

## Règles d'advisor (le contenu pédagogique, validé)

Pour chaque travail **disponible** (les autres n'ont pas de conseil). `f` = `BuildingCalibration::envelopeLossFactor(household->envelope)`. Seuil « peu isolée » : `f > 0,70` (= combles+murs pas encore faits ; combles seul = 0,76 → encore peu isolée).

| Travail | Condition | Niveau | Message (fr) |
|---|---|---|---|
| RoofInsulation | — | 💡 Info | « Souvent le meilleur rapport gain/prix : ~24 % des pertes, et peu cher. » |
| WallInsulationInterior | — | 💡 Info | « ITI : moins chère, mais grignote la surface habitable et laisse des ponts thermiques. » |
| WallInsulationExterior | — | 💡 Info | « ITE : plus chère, mais meilleure (pas de pont thermique) et ravale la façade. » |
| Glazing | `f > 0,70` | ⚠ Caution | « Le vitrage pèse peu (~10 % des pertes) : priorisez d'abord combles et murs. » |
| Glazing | `f ≤ 0,70` | 💡 Info | « Complète l'isolation ; gagne surtout du confort (paroi froide) et de l'acoustique. Le triple n'est utile qu'en climat froid. » |
| HeatPump | chaudière en panne | 💡 Info | « L'occasion de sortir du fioul. Vérifiez que la maison est un minimum isolée, sinon la PAC sera bridée. » |
| HeatPump | `f > 0,70` (pas en panne) | ⚠ Caution | « Maison peu isolée → PAC surdimensionnée, factures qui resteront hautes. Isolez d'abord. » |
| HeatPump | `f ≤ 0,70` (pas en panne) | 💡 Info | « Bon rendement attendu : la maison est suffisamment isolée pour une PAC efficace. » |
| BoilerRepair | (toujours : n'existe qu'en panne) | 💡 Info | « Rapide et peu cher, mais vous restez au fioul (facture et CO₂ élevés). » |
| SolarPanels | — | 💡 Info | « Réduit la facture d'électricité. Plus rentable une fois les besoins de chauffage réduits. » |
| HomeBattery | (n'existe qu'avec solaire) | 💡 Info | « Stocke le surplus solaire pour le consommer le soir. » |

Seules **deux** situations produisent un ⚠ (PAC en passoire ; vitrage prioritaire alors que l'enveloppe n'est pas traitée) — les vraies erreurs de séquence. Tout le reste est un repère neutre. **Pas de niveau « recommandé ».**

Note éducative statique (une phrase, dans l'intro — pas un waypoint) : « En rénovation, on isole avant de changer le chauffage. »

---

## File Structure

**Créés :**
- `src/Domain/Finance/AdviceLevel.php` — enum `Info|Caution` (+ `icon()` → 💡/⚠).
- `src/Domain/Finance/RenovationAdvice.php` — VO `final readonly` (level + message).
- `src/Domain/Finance/RenovationAdvisor.php` — `adviceFor(Renovation, Household): ?RenovationAdvice`.
- Tests : `tests/Unit/Domain/Finance/RenovationAdvisorTest.php`.

**Modifiés :**
- `src/Domain/Building/BuildingCalibration.php` — seuil `poorlyInsulatedEnvelopeCeiling()` (0,70, calibration de jeu).
- `src/Application/ActionView.php` — `adviceLevel: string`, `adviceMessage: string`.
- `src/Application/GameView.php` — flags « déjà fait » enveloppe (`roofInsulated`, `wallInsulationLabel`, `glazingLabel`) si absents.
- `src/Application/GameViewFactory.php` — injecte `RenovationAdvisor`, remplit le conseil des `ActionView` ; expose les flags done.
- `templates/components/QuoteCard.html.twig` — badge conseil 💡/⚠ + icônes des 4 travaux d'enveloppe (remplace l'icône `insulation` orpheline).
- `templates/game/panel/_slot.html.twig` — restructuré : entête contexte-décision (lire/agir), devis, bande « ✔ fait ».
- `templates/components/GameDashboard.html.twig` — `panelPos` des slots équipement → `at-drawer` ; note éducative dans l'intro.
- `assets/styles/components/panels.css` — variante `.float-panel.at-drawer` (pleine hauteur, scrollable) + styles badge conseil.

---

## Task 1: VOs de conseil — `AdviceLevel` + `RenovationAdvice`

**Files:**
- Create: `src/Domain/Finance/AdviceLevel.php`, `src/Domain/Finance/RenovationAdvice.php`
- Test: `tests/Unit/Domain/Finance/RenovationAdviceTest.php`

**Interfaces:**
- Produces:
  - `enum AdviceLevel: string { case Info='info'; case Caution='caution'; public function icon(): string; }` (icon → '💡' / '⚠️').
  - `final readonly class RenovationAdvice { public function __construct(public AdviceLevel $level, public string $message) {} }`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\RenovationAdvice;
use PHPUnit\Framework\TestCase;

final class RenovationAdviceTest extends TestCase
{
    public function testCarriesLevelAndMessage(): void
    {
        $advice = new RenovationAdvice(AdviceLevel::Caution, 'Isolez d\'abord.');

        self::assertSame(AdviceLevel::Caution, $advice->level);
        self::assertSame('Isolez d\'abord.', $advice->message);
    }

    public function testLevelsCarryAnIcon(): void
    {
        self::assertSame('💡', AdviceLevel::Info->icon());
        self::assertSame('⚠️', AdviceLevel::Caution->icon());
    }
}
```

- [ ] **Step 2: Run → FAIL.** `vendor/bin/phpunit tests/Unit/Domain/Finance/RenovationAdviceTest.php`

- [ ] **Step 3: Create `AdviceLevel`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/** How strongly a renovation advice reads — never prescriptive, only informative or a caution. */
enum AdviceLevel: string
{
    case Info = 'info';
    case Caution = 'caution';

    public function icon(): string
    {
        return match ($this) {
            self::Info => '💡',
            self::Caution => '⚠️',
        };
    }
}
```

- [ ] **Step 4: Create `RenovationAdvice`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * A non-prescriptive piece of advice about a renovation, given the current
 * house state: an informative repère (Info) or a caution against a genuine
 * sequencing mistake (Caution). Never a "do this now" — the player keeps the
 * choice (game-design: pédagogie par les systèmes, pas de dirigisme).
 */
final readonly class RenovationAdvice
{
    public function __construct(
        public AdviceLevel $level,
        public string $message,
    ) {
    }
}
```

- [ ] **Step 5: Run → PASS.** Then `make cs-fix`, re-run.
- [ ] **Step 6: Commit** — `feat(finance): AdviceLevel + RenovationAdvice VOs (arbre travaux T2)`

---

## Task 2: `RenovationAdvisor` — le moteur pédagogique

**Files:**
- Modify: `src/Domain/Building/BuildingCalibration.php` (ajoute `poorlyInsulatedEnvelopeCeiling()`)
- Create: `src/Domain/Finance/RenovationAdvisor.php`
- Test: `tests/Unit/Domain/Finance/RenovationAdvisorTest.php`

**Interfaces:**
- Consumes: `Renovation`, `Household`, `BuildingCalibration::envelopeLossFactor(EnvelopeState)`, `AdviceLevel`, `RenovationAdvice`.
- Produces: `RenovationAdvisor::adviceFor(Renovation $work, Household $household): ?RenovationAdvice` — advice per the ruleset table above; `null` for works with no advice rule.
- Produces: `BuildingCalibration::poorlyInsulatedEnvelopeCeiling(): Coefficient` (value 0.70, game calibration).

- [ ] **Step 1: Failing advisor tests** (exact factors: original `f=1.0`; combles-only `f=0.76`; combles+ITI `f=0.60`)

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\Renovation;
use App\Domain\Finance\RenovationAdvisor;
use PHPUnit\Framework\TestCase;

final class RenovationAdvisorTest extends TestCase
{
    private RenovationAdvisor $advisor;

    protected function setUp(): void
    {
        $this->advisor = new RenovationAdvisor();
    }

    private function house(EnvelopeState $envelope, HeatingSystem $heating = HeatingSystem::FuelOilBoiler, bool $broken = false): Household
    {
        return new Household(0.0, 0.0, $envelope, $heating, $broken);
    }

    private function bare(): EnvelopeState
    {
        return new EnvelopeState(false, WallInsulation::None, Glazing::Single);
    }

    public function testRoofInsulationIsAlwaysAneutralRepere(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::RoofInsulation, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('gain/prix', $advice->message);
    }

    public function testHeatPumpCautionedInAPoorlyInsulatedHouse(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::HeatPump, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Caution, $advice->level);
        self::assertStringContainsString('surdimensionnée', $advice->message);
    }

    public function testHeatPumpIsInfoOnceInsulated(): void
    {
        $insulated = new EnvelopeState(true, WallInsulation::Interior, Glazing::Single); // f = 0.60
        $advice = $this->advisor->adviceFor(Renovation::HeatPump, $this->house($insulated));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
    }

    public function testHeatPumpDuringBreakdownIsInfoNotCaution(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::HeatPump, $this->house($this->bare(), broken: true));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
        self::assertStringContainsString('fioul', $advice->message);
    }

    public function testGlazingCautionedWhileEnvelopeUntreated(): void
    {
        $advice = $this->advisor->adviceFor(Renovation::Glazing, $this->house($this->bare()));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Caution, $advice->level);
    }

    public function testGlazingIsInfoOnceEnvelopeTreated(): void
    {
        $insulated = new EnvelopeState(true, WallInsulation::Interior, Glazing::Single); // f = 0.60
        $advice = $this->advisor->adviceFor(Renovation::Glazing, $this->house($insulated));

        self::assertNotNull($advice);
        self::assertSame(AdviceLevel::Info, $advice->level);
    }

    public function testWallOptionsDescribeTheirTradeoff(): void
    {
        $iti = $this->advisor->adviceFor(Renovation::WallInsulationInterior, $this->house($this->bare()));
        $ite = $this->advisor->adviceFor(Renovation::WallInsulationExterior, $this->house($this->bare()));

        self::assertNotNull($iti);
        self::assertNotNull($ite);
        self::assertStringContainsString('surface habitable', $iti->message);
        self::assertStringContainsString('façade', $ite->message);
    }
}
```

- [ ] **Step 2: Run → FAIL.** `vendor/bin/phpunit tests/Unit/Domain/Finance/RenovationAdvisorTest.php`

- [ ] **Step 3: Add the calibration threshold to `BuildingCalibration`**

```php
    /**
     * Above this residual loss factor, the house is "peu isolée" for advice
     * purposes: an air/water heat pump would be oversized, and glazing is a
     * low-priority spend. Game calibration (not a physical constant): 0.70
     * means combles + murs not yet done (combles seul = 0,76 → encore peu isolé).
     */
    public function poorlyInsulatedEnvelopeCeiling(): Coefficient
    {
        return new Coefficient(value: 0.70, unit: 'fraction', min: 0.60, max: 0.80, source: 'Calibration de jeu : seuil de conseil « maison peu isolée » (repères ADEME : isoler avant de dimensionner une PAC)', reviewedOn: '2026-07-16');
    }
```

- [ ] **Step 4: Create `RenovationAdvisor`**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\BuildingCalibration;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;

/**
 * Non-prescriptive advice about renovations (game-design: pédagogie par les
 * systèmes, pas de dirigisme). For an available work and the current house,
 * returns an informative repère (Info) or a caution against a genuine
 * sequencing mistake (Caution) — never a "do this next". Only two situations
 * warrant a caution: a heat pump in a poorly-insulated house, and glazing
 * prioritised before the envelope is treated. Pure and deterministic.
 */
final readonly class RenovationAdvisor
{
    public function __construct(
        private BuildingCalibration $building = new BuildingCalibration(),
    ) {
    }

    public function adviceFor(Renovation $work, Household $household): ?RenovationAdvice
    {
        $poorlyInsulated = $this->building->envelopeLossFactor($household->envelope)
            > $this->building->poorlyInsulatedEnvelopeCeiling()->value;

        return match ($work) {
            Renovation::RoofInsulation => new RenovationAdvice(
                AdviceLevel::Info,
                'Souvent le meilleur rapport gain/prix : ~24 % des pertes, et peu cher.',
            ),
            Renovation::WallInsulationInterior => new RenovationAdvice(
                AdviceLevel::Info,
                'ITI : moins chère, mais grignote la surface habitable et laisse des ponts thermiques.',
            ),
            Renovation::WallInsulationExterior => new RenovationAdvice(
                AdviceLevel::Info,
                'ITE : plus chère, mais meilleure (pas de pont thermique) et ravale la façade.',
            ),
            Renovation::Glazing => $poorlyInsulated
                ? new RenovationAdvice(AdviceLevel::Caution, 'Le vitrage pèse peu (~10 % des pertes) : priorisez d\'abord combles et murs.')
                : new RenovationAdvice(AdviceLevel::Info, 'Complète l\'isolation ; gagne surtout du confort (paroi froide) et de l\'acoustique. Le triple n\'est utile qu\'en climat froid.'),
            Renovation::HeatPump => match (true) {
                $household->boilerBroken => new RenovationAdvice(AdviceLevel::Info, 'L\'occasion de sortir du fioul. Vérifiez que la maison est un minimum isolée, sinon la PAC sera bridée.'),
                $poorlyInsulated => new RenovationAdvice(AdviceLevel::Caution, 'Maison peu isolée → PAC surdimensionnée, factures qui resteront hautes. Isolez d\'abord.'),
                default => new RenovationAdvice(AdviceLevel::Info, 'Bon rendement attendu : la maison est suffisamment isolée pour une PAC efficace.'),
            },
            Renovation::BoilerRepair => new RenovationAdvice(
                AdviceLevel::Info,
                'Rapide et peu cher, mais vous restez au fioul (facture et CO₂ élevés).',
            ),
            Renovation::SolarPanels => new RenovationAdvice(
                AdviceLevel::Info,
                'Réduit la facture d\'électricité. Plus rentable une fois les besoins de chauffage réduits.',
            ),
            Renovation::HomeBattery => new RenovationAdvice(
                AdviceLevel::Info,
                'Stocke le surplus solaire pour le consommer le soir.',
            ),
        };
    }
}
```
(`HeatingSystem` import may be unused — remove if PHPStan/cs flag it; kept only if a rule references it. The match above does not, so **do not import it**.)

- [ ] **Step 5: Run advisor tests → PASS.** Then full suite + `make cs-fix`.
- [ ] **Step 6: Commit** — `feat(finance): RenovationAdvisor — non-prescriptive advice engine (arbre travaux T2)`

---

## Task 3: Câbler le conseil dans la vue

**Files:**
- Modify: `src/Application/ActionView.php` (2 champs)
- Modify: `src/Application/GameView.php` (flags « fait » enveloppe, si absents)
- Modify: `src/Application/GameViewFactory.php` (injecte `RenovationAdvisor`, remplit le conseil + les flags)
- Test: `tests/Unit/Application/GameViewFactoryTest.php`

**Interfaces:**
- Consumes: `RenovationAdvisor::adviceFor`, `AdviceLevel`.
- Produces on `ActionView`: `public string $adviceLevel = ''` ('info'|'caution'|''), `public string $adviceMessage = ''`.
- Produces on `GameView`: `public bool $roofInsulated`, `public string $wallInsulationLabel` ('' si non isolés), `public string $glazingLabel` (ex. 'Double vitrage'), `public bool $glazingMaxed`.

- [ ] **Step 1: Failing factory test** — un devis d'enveloppe porte un conseil ; la PAC en maison nue porte une caution.

```php
    public function testEnvelopeActionsCarryAdvice(): void
    {
        $view = /* build a GameView for a brand-new (uninsulated) game — reuse the test's existing factory setup */;

        self::assertSame('info', $view->actions['roof_insulation']->adviceLevel);
        self::assertNotSame('', $view->actions['roof_insulation']->adviceMessage);
        self::assertSame('caution', $view->actions['heat_pump']->adviceLevel);
    }
```
(Adapter au harnais existant du test : réutiliser la construction de `GameView`/`GameViewFactory` déjà en place dans `GameViewFactoryTest`.)

- [ ] **Step 2: Run → FAIL.**

- [ ] **Step 3: Extend `ActionView`** — ajouter en fin de constructeur :

```php
        /** Advice level for this work given the current house: 'info' | 'caution' | '' (none). */
        public string $adviceLevel = '',
        /** Player-facing advice message (French), empty when no advice applies. */
        public string $adviceMessage = '',
```

- [ ] **Step 4: Fill advice in `GameViewFactory::actionsFor`** — injecter `RenovationAdvisor $advisor` au constructeur (défaut `new RenovationAdvisor()`), puis dans la boucle :

```php
            $advice = $this->advisor->adviceFor($work, $state->household);

            $actions[$work->value] = new ActionView(
                // ... champs existants ...
                effectLabels: $this->effectLabels($before, $after),
                adviceLevel: $advice?->level->value ?? '',
                adviceMessage: $advice?->message ?? '',
            );
```

- [ ] **Step 5: Add the envelope done-flags to `GameView` + factory** — sur `GameView`, ajouter `roofInsulated`, `wallInsulationLabel`, `glazingLabel`, `glazingMaxed`. Les remplir dans la factory depuis `$household->envelope` :

```php
roofInsulated: $household->envelope->roofInsulated,
wallInsulationLabel: WallInsulation::None === $household->envelope->walls ? '' : $household->envelope->walls->label(),
glazingLabel: $household->envelope->glazing->label(),
glazingMaxed: Glazing::Triple === $household->envelope->glazing,
```

- [ ] **Step 6: Run → PASS.** Full suite + `make cs-fix`.
- [ ] **Step 7: Commit** — `feat(app): actions carry non-prescriptive advice; envelope done-flags (arbre travaux T2)`

---

## Task 4: `QuoteCard` — badge de conseil + icônes des 4 travaux

**Files:**
- Modify: `templates/components/QuoteCard.html.twig`
- Modify: `assets/styles/components/panels.css` (styles `.quote-advice`)
- Test: assertion d'intégration (Task 6) — ici, `make twig` + rendu.

**Interfaces:**
- Consumes: `action.adviceLevel`, `action.adviceMessage`.

- [ ] **Step 1: Add the advice badge to `QuoteCard`** — après le bloc `.effects`, avant `.quote-actions` :

```twig
    {% if action.adviceMessage is not empty %}
        <p class="quote-advice quote-advice--{{ action.adviceLevel }}">
            <span class="quote-advice-icon">{{ action.adviceLevel == 'caution' ? '⚠️' : '💡' }}</span>
            {{ action.adviceMessage }}
        </p>
    {% endif %}
```

- [ ] **Step 2: Fix the icons** — remplacer la branche `action.work == 'insulation'` (orpheline depuis le split) par les 4 clés d'enveloppe, réutilisant le swatch existant pour l'isolation opaque (combles/murs) et un motif « vitrage » pour le glazing :

```twig
            {% elseif action.work in ['roof_insulation', 'wall_insulation_interior', 'wall_insulation_exterior'] %}
                <svg viewBox="0 0 80 80" overflow="visible">
                    <rect x="8" y="8" width="64" height="64" rx="6" fill="#8a7458"/>
                    <rect x="14" y="14" width="52" height="52" rx="3" fill="#69b585"/>
                    <path d="M14 24h52M14 34h52M14 44h52M14 54h52" stroke="#5f9c74" stroke-width="3" opacity=".6"/>
                    <path d="M22 14v52M32 14v52M42 14v52M52 14v52M62 14v52" stroke="#5f9c74" stroke-width="2" opacity=".35"/>
                </svg>
            {% elseif action.work == 'glazing' %}
                <svg viewBox="0 0 80 80" overflow="visible">
                    <rect x="12" y="10" width="56" height="60" rx="4" fill="#7d8ea0"/>
                    <rect x="18" y="16" width="44" height="48" rx="2" fill="#bfe0ee" opacity=".85"/>
                    <path d="M40 16v48M18 40h44" stroke="#7d8ea0" stroke-width="4"/>
                    <path d="M24 22l12 12" stroke="#fff" stroke-width="2" opacity=".6"/>
                </svg>
```

- [ ] **Step 3: Style the advice badge** in `panels.css` — variables d'état plutôt que littéraux ; réutiliser `--warn` pour la caution :

```css
.quote-advice {
    display: flex;
    gap: .4rem;
    align-items: flex-start;
    margin: .5rem 0 0;
    padding: .4rem .55rem;
    border-radius: 8px;
    font-size: .78rem;
    line-height: 1.35;
    background: color-mix(in srgb, var(--muted) 12%, var(--card));
    color: var(--ink);
}

.quote-advice--caution {
    background: color-mix(in srgb, var(--warn) 14%, var(--card));
}

.quote-advice-icon {
    flex: 0 0 auto;
}
```
(Vérifier que `--warn` existe dans `game.css :root` ; sinon utiliser la variable d'alerte présente.)

- [ ] **Step 4: `make twig`** → OK. (Le rendu est vérifié en Task 6.)
- [ ] **Step 5: Commit** — `feat(ui): quote cards show advice badge + envelope work icons (arbre travaux T3)`

---

## Task 5: Le tiroir latéral scrollable + séparation lire/agir

**Files:**
- Modify: `assets/styles/components/panels.css` (`.float-panel.at-drawer`)
- Modify: `templates/components/GameDashboard.html.twig` (`panelPos` slots → `at-drawer` ; note éducative intro)
- Modify: `templates/game/panel/_slot.html.twig` (entête contexte-décision + bande « ✔ fait »)

**Interfaces:**
- Consumes: `game.roofInsulated`, `game.wallInsulationLabel`, `game.glazingLabel`, `game.glazingMaxed`, `game.actions[...]`.

- [ ] **Step 1: Add the drawer variant** in `panels.css` :

```css
/* Tiroir latéral : les zones-équipement (toit, murs, chauffage, garage, séjour)
   ouvrent un panneau pleine hauteur à défilement interne — assez de place pour
   plusieurs devis + conseils sans déborder de l'écran (les axes des 4 coins
   restent des popovers de coin). */
.float-panel.at-drawer {
    top: .7rem;
    right: .7rem;
    bottom: .7rem;
    width: min(420px, 94%);
    max-height: none;
    overflow-y: auto;
    overflow-x: visible;
}
```
(Note : `.float-panel` global est `overflow: visible` ; la variante drawer passe en `overflow-y: auto` — les infobulles internes défileront avec le contenu, acceptable.)

- [ ] **Step 2: Route the equipment slots to the drawer** — dans `GameDashboard.html.twig`, `panelPos` :

```twig
{% set panelPos = {
    finances: 'at-tl', comfort: 'at-tr', energy: 'at-bl', patrimoine: 'at-br',
    weather: 'at-top', options: 'at-bottom',
    roof: 'at-drawer', walls: 'at-drawer', heating: 'at-drawer', garage: 'at-drawer', living: 'at-drawer',
} %}
```

- [ ] **Step 3: Educational note in the intro** — dans le bloc `.intro-how` (ou juste après), une phrase, non directive :

```twig
                    <p class="intro-how">Cliquez le <strong>toit</strong>, la <strong>chaudière</strong>, le <strong>garage</strong> ou le <strong>séjour</strong> pour agir, et les <strong>quatre coins</strong> pour vos indicateurs. Le temps avance seul — <strong>⏸</strong> pour réfléchir. <em>Repère : en rénovation, on isole avant de changer le chauffage.</em></p>
```

- [ ] **Step 4: Lire/agir + « ✔ fait » in `_slot.html.twig`** — pour le slot `walls`, remplacer l'entête actuel (qui duplique DPE/valeur/confort des coins) par un **entête de contexte-décision** court + une bande « ✔ fait » avant les devis :

```twig
    {% elseif selected == 'walls' %}
        <div class="row"><span class="key">DPE actuel</span><span><strong>{{ game.dpeLetter }}</strong> · déperditions dominantes : toiture, murs</span></div>
        {% set envelopeDone = [] %}
        {% if game.roofInsulated %}{% set envelopeDone = envelopeDone|merge(['Combles isolés']) %}{% endif %}
        {% if game.wallInsulationLabel is not empty %}{% set envelopeDone = envelopeDone|merge(['Murs — ' ~ game.wallInsulationLabel]) %}{% endif %}
        {% if game.glazingLabel != 'Simple vitrage' %}{% set envelopeDone = envelopeDone|merge([game.glazingLabel]) %}{% endif %}
        {% if envelopeDone is not empty %}
            <div class="done-strip">{% for d in envelopeDone %}<span class="done-chip">✔ {{ d }}</span>{% endfor %}</div>
        {% endif %}
```
Conserver le rendu des devis existant (`{% for work in worksOfSlot[selected] %}`) inchangé — les cartes portent déjà le conseil (Task 4). Retirer de cet entête les lignes « Valeur du bien » et « Confort ressenti » (doublon des coins Patrimoine/Confort ; séparation lire/agir).

Style `.done-strip`/`.done-chip` dans `panels.css` :

```css
.done-strip { display: flex; flex-wrap: wrap; gap: .3rem; margin: .1rem 0 .2rem; }
.done-chip { font-size: .72rem; padding: .12rem .5rem; border-radius: 999px; background: color-mix(in srgb, var(--accent) 14%, var(--card)); color: var(--muted); }
```

- [ ] **Step 5: `make twig` + full suite** → OK. Vérifier visuellement (Task 6) : le tiroir défile, les devis tiennent, les conseils s'affichent, la bande ✔ se remplit.
- [ ] **Step 6: Commit** — `feat(ui): scrollable side drawer + read/act split for works panels (arbre travaux T3)`

---

## Task 6: Vérification bout-en-bout + revue

- [ ] **Step 1: Integration test** — dans `tests/Integration/GameDashboardTest.php`, étendre le test du slot `walls` : après `selectSlot('walls')`, asserter que le HTML rendu contient un message de conseil (ex. « gain/prix » pour les combles) et, pour une PAC en maison nue (slot `heating`), le mot « surdimensionnée ». Vérifier aussi qu'aucune erreur de rendu du tiroir.

```php
self::assertStringContainsString('gain/prix', $html);           // conseil combles présent
// slot heating : caution PAC
self::assertStringContainsString('surdimensionnée', $heatingHtml);
```

- [ ] **Step 2: Full qa gate** — `make cs && make twig && make test` (+ `make stan` en local/CI). Vert.

- [ ] **Step 3: Manual smoke** — lancer l'app (`/run`), cliquer le mur : le tiroir latéral s'ouvre, défile, montre les 4 devis avec leur badge 💡/⚠, la bande ✔ apparaît après un travail ; cliquer la chaudière en maison nue : la PAC porte le ⚠ « surdimensionnée ». Vérifier qu'on voit tout à l'écran (plus de débordement).

- [ ] **Step 4: Backlog** — marquer T2+T3 faites dans `docs/backlog.md` (entrée arbre de travaux) ; noter T4 (chauffage : granulés, émetteurs BT→SCOP) comme suivante.

- [ ] **Step 5: Optional** — `superpowers:requesting-code-review` sur le diff des tâches 1-5.

---

## Self-Review du plan (couverture)

- **Advisor 2 niveaux non prescriptif (décision validée)** → Tasks 1-2 ; règles exactes dans la table + code. ✔
- **Conseils affichés par carte (💡/⚠)** → Tasks 3-4. ✔
- **Tiroir latéral scrollable (règle le débordement)** → Task 5 (`.at-drawer`). ✔
- **Séparation lire/agir (fin des doublons de coin) + « ✔ fait »** → Task 5. ✔
- **Icônes des 4 travaux d'enveloppe (fix orphelin post-split)** → Task 4. ✔
- **Note éducative statique, non directive** → Task 5 step 3. ✔
- **Pas de halo de scène, pas de ★ « conseillé maintenant »** (décisions validées) → volontairement absents. ✔
- **Seuils = calibration de jeu sourcée/étiquetée** → Task 2 step 3. ✔

Hors périmètre (tranches suivantes) : chauffage (granulés, émetteurs BT→SCOP) T4 ; production/ECS T5 ; gestes T6 ; le contexte-décision par zone au-delà du slot `walls` (heating/garage) peut être affiné en même temps que T4/T5.
