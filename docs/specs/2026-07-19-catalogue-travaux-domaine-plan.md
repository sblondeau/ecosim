# Catalogue de travaux — fermeture du domaine (plan d'implémentation)

> **Pour l'exécutant :** SOUS-COMPÉTENCE REQUISE — utiliser
> `superpowers:subagent-driven-development` (recommandé) ou
> `superpowers:executing-plans` pour dérouler ce plan tâche par tâche. Les
> étapes utilisent la syntaxe `- [ ]` pour le suivi.

**But :** rendre le domaine fermé à la modification pour les travaux de
rénovation — un travail devient une classe implémentant
`RenovationDefinition`, référencée par une liste ordonnée unique.

**Architecture :** l'enum `Renovation` et ses trois `match` exhaustifs
disparaissent au profit d'une interface et d'un `RenovationCatalog`. La
politique de financement (prime, éligibilité éco-PTZ) reste centralisée dans
`RenovationQuoter`, réduit à ce seul rôle. Migration par pont : le catalogue est
consulté d'abord, l'ancien `match` sert de repli, et les travaux basculent par
lots — `make qa` vert à chaque tâche.

**Stack :** PHP 8.4, PHPUnit 13, PHPStan niveau 8, php-cs-fixer.

**Spec de référence :** `docs/specs/2026-07-18-catalogue-travaux-design.md`

## Périmètre

Ce plan couvre les **paliers 1 à 3** de la spec (§7) : le domaine.

**Rien ne change à l'écran.** Aucun test d'intégration ne doit bouger. C'est le
critère de réussite le plus important du plan : si un rendu change, c'est une
régression, pas un progrès.

Les paliers 4 à 6 (scène `activeLayers`, `doneLabelsBySlot`, icônes) font
l'objet d'un plan séparé — ce sont eux qui dégonflent `GameView`. **Ce plan les
prépare** en faisant implémenter dès maintenant `doneLabelFor()`,
`sceneLayerFor()` et `iconAsset()` par les 15 classes, pour ne pas repasser une
seconde fois sur 15 fichiers.

## Contraintes globales

Copiées du `CLAUDE.md`, elles s'appliquent implicitement à chaque tâche.

- `src/Domain/**` : **interdit** d'importer Symfony, Doctrine ou tout vendor de
  framework. PHP natif uniquement.
- `declare(strict_types=1)` partout ; classes `final` par défaut ; VOs
  `final readonly`.
- Identifiants et commentaires de code en **anglais** ; libellés joueur en
  **français**.
- **Aucun nombre magique inline.** Tout coefficient chiffré vient du registre de
  calibration (`FinanceCalibration`, `EnergyCalibration`, `BuildingCalibration`)
  avec sa source. Ce plan ne crée aucun coefficient : il déplace des appels
  existants.
- PHPStan niveau 8, **corriger la cause** : pas de `@phpstan-ignore`, pas de
  baseline, pas d'`assert()`/`@var` pour forcer un type, pas de cast pour faire
  taire une erreur.
- `make qa` vert avant chaque commit.
- Chaque brique de domaine arrive **avec ses tests dans le même commit**.
- Tests de domaine = unitaires purs (`tests/Unit/...`), sans DB ni kernel.

---

## Structure des fichiers

**Créés :**

| Fichier | Responsabilité |
|---|---|
| `src/Domain/Finance/SceneSlot.php` | Les 5 zones cliquables de la scène (enum fermé) |
| `src/Domain/Finance/RenovationOffer.php` | Ce qu'un travail déclare de lui-même : titre, coût, foyer résultant |
| `src/Domain/Finance/RenovationDefinition.php` | L'interface — tout ce que le jeu doit savoir d'un travail |
| `src/Domain/Finance/RenovationCatalog.php` | La liste ordonnée + résolution par slug et par slot |
| `src/Domain/Finance/Work/*.php` | 15 classes, une par travail |
| `tests/Unit/Domain/Finance/RenovationCatalogTest.php` | Unicité, ordre, résolution |
| `tests/Unit/Domain/Finance/Work/*Test.php` | 15 tests, un par travail |

**Modifiés :**

| Fichier | Changement |
|---|---|
| `src/Domain/Finance/RenovationQuoter.php` | Pont, puis réduction à la politique de financement |
| `src/Domain/Finance/RenovationAdvisor.php` | Pont, puis suppression |
| `src/Domain/Finance/RenovationQuote.php` | `Renovation $work` → `string $workSlug` |
| `src/Application/RenovationHandler.php` | Prend un slug, résout via le catalogue |
| `src/Application/GameViewFactory.php` | Itère le catalogue au lieu de `Renovation::cases()` |
| `src/Twig/Components/GameDashboard.php` | Plus de `Renovation::tryFrom()` |

**Supprimés (tâche 6) :** `src/Domain/Finance/Renovation.php`,
`src/Domain/Finance/RenovationAdvisor.php`,
`tests/Unit/Domain/Finance/RenovationAdvisorTest.php`.

---

## Tâche 0 : extraire les 6 icônes inline

`iconAsset()` doit retourner un chemin qui désigne un vrai fichier dès la tâche
3. Six icônes de `QuoteCard` sont aujourd'hui du SVG inline dans le Twig : on
les sort en fichiers d'abord, sans rien changer au rendu.

**Fichiers :**
- Créer : `templates/game/scene/assets/icons/insulation.svg`, `glazing.svg`,
  `ventilation-double-flow.svg`, `low-temp-emitters.svg`,
  `draught-proofing.svg`, `thermal-curtains.svg`
- Modifier : `templates/components/QuoteCard.html.twig`

**Interfaces :**
- Produit : les 6 chemins d'assets que `RenovationDefinition::iconAsset()`
  retournera aux tâches 3 à 5.

- [ ] **Étape 1 : déplacer chaque bloc SVG inline dans son fichier**

Pour chacune des 6 icônes, couper le `<svg>…</svg>` de
`templates/components/QuoteCard.html.twig` et le coller tel quel dans le fichier
correspondant. **Ne rien redessiner** : aucun attribut, aucune coordonnée,
aucune couleur ne change. Conserver les commentaires Twig explicatifs
(`{# VMC double flux : boîtier échangeur… #}`) en les convertissant en
commentaires SVG (`<!-- … -->`) en tête du fichier.

Correspondance : le bloc `roof_insulation, wall_insulation_interior,
wall_insulation_exterior` (le swatch d'isolation, partagé par les trois travaux)
→ `icons/insulation.svg` ; `glazing` → `icons/glazing.svg` ;
`ventilation_double_flow` → `icons/ventilation-double-flow.svg` ;
`low_temp_emitters` → `icons/low-temp-emitters.svg` ; `draught_proofing` →
`icons/draught-proofing.svg` ; `thermal_curtains` → `icons/thermal-curtains.svg`.

- [ ] **Étape 2 : remplacer chaque bloc par son include**

Dans `QuoteCard.html.twig`, chaque branche devient un `include`, sur le modèle
des branches qui en ont déjà un :

```twig
            {% elseif action.work == 'glazing' %}
                {{ include('game/scene/assets/icons/glazing.svg') }}
```

Le `solar_kit` est un cas à part : il a **déjà** un asset de scène
(`game/scene/assets/solar-kit.svg`) alors que `QuoteCard` en dessinait un autre
inline. Supprimer le SVG inline et pointer sur l'asset de scène — c'est la règle
« un seul dessin par équipement », et ça retire un doublon existant.

- [ ] **Étape 3 : vérifier que le rendu n'a pas bougé**

Lancer : `make twig && vendor/bin/phpunit tests/Integration/`
Attendu : PASS.

Puis vérifier visuellement : ouvrir le jeu, ouvrir les tiroirs Isolation,
Chauffage, Séjour et Garage, et confirmer que **les 7 icônes concernées
s'affichent** (les 6 extraites + le kit solaire, qui change de dessin
volontairement — il prend celui de la scène).

- [ ] **Étape 4 : commit**

```bash
git add templates/game/scene/assets/icons/ templates/components/QuoteCard.html.twig
git commit -m "refactor(ui): extract the drawer's inline icons into assets

Six icons lived as inline SVG in QuoteCard, so nothing could reference
them by path. Moves them to files, untouched, and points the template at
them — the shape the catalogue's iconAsset() needs.

The solar kit drops its bespoke inline drawing for the scene asset it
already had: one drawing per equipment.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Tâche 1 : les fondations

**Fichiers :**
- Créer : `src/Domain/Finance/SceneSlot.php`
- Créer : `src/Domain/Finance/RenovationOffer.php`
- Créer : `src/Domain/Finance/RenovationDefinition.php`
- Créer : `src/Domain/Finance/RenovationCatalog.php`
- Test : `tests/Unit/Domain/Finance/RenovationCatalogTest.php`

**Interfaces :**
- Consomme : `Household`, `Money`, `RenovationAdvice` (existants).
- Produit : `RenovationDefinition` (8 méthodes, signatures ci-dessous),
  `RenovationCatalog::tryGet(string): ?RenovationDefinition`,
  `::get(string): RenovationDefinition`, `::all(): list<RenovationDefinition>`,
  `::forSlot(SceneSlot): list<RenovationDefinition>`. Toutes les tâches
  suivantes en dépendent.

**Note de conception —** le catalogue construit sa liste par défaut dans une
méthode statique privée, **pas** dans un défaut de paramètre. Le codebase
utilise `= new X()` en défaut de paramètre (autorisé depuis PHP 8.1), mais un
*tableau* d'instanciations en défaut est un terrain glissant ; une méthode
statique est sans ambiguïté et garde la liste ordonnée lisible d'un bloc.

- [ ] **Étape 1 : écrire le test qui échoue**

`tests/Unit/Domain/Finance/RenovationCatalogTest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance;

use App\Domain\Building\Household;
use App\Domain\Finance\RenovationAdvice;
use App\Domain\Finance\RenovationCatalog;
use App\Domain\Finance\RenovationDefinition;
use App\Domain\Finance\RenovationOffer;
use App\Domain\Finance\SceneSlot;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RenovationCatalogTest extends TestCase
{
    public function testResolvesAWorkBySlug(): void
    {
        $catalog = new RenovationCatalog([new FakeWork('alpha', SceneSlot::Roof)]);

        self::assertSame('alpha', $catalog->get('alpha')->slug());
    }

    public function testTryGetReturnsNullForAnUnknownSlug(): void
    {
        $catalog = new RenovationCatalog([new FakeWork('alpha', SceneSlot::Roof)]);

        self::assertNull($catalog->tryGet('nope'));
    }

    public function testGetThrowsForAnUnknownSlug(): void
    {
        $catalog = new RenovationCatalog([new FakeWork('alpha', SceneSlot::Roof)]);

        $this->expectException(InvalidArgumentException::class);
        $catalog->get('nope');
    }

    /**
     * A duplicate slug is a programming mistake: two works answering to the
     * same form value would silently shadow each other.
     */
    public function testRejectsDuplicateSlugs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RenovationCatalog([
            new FakeWork('alpha', SceneSlot::Roof),
            new FakeWork('alpha', SceneSlot::Walls),
        ]);
    }

    /**
     * Declaration order IS display order: `worksOfSlot` used to encode it in
     * the template (boiler repair before the heat pump, so a breakdown offers
     * the cheap fix first). The catalogue must not lose it.
     */
    public function testForSlotKeepsDeclarationOrder(): void
    {
        $catalog = new RenovationCatalog([
            new FakeWork('first', SceneSlot::Heating),
            new FakeWork('elsewhere', SceneSlot::Roof),
            new FakeWork('second', SceneSlot::Heating),
        ]);

        $slugs = array_map(
            static fn (RenovationDefinition $w): string => $w->slug(),
            $catalog->forSlot(SceneSlot::Heating),
        );

        self::assertSame(['first', 'second'], $slugs);
    }

    /**
     * The default catalogue is empty until task 3 fills it; all this can
     * assert today is that it is constructible. The real assertion — fifteen
     * works, no duplicate — arrives in task 5, when it can be true.
     */
    public function testTheDefaultCatalogueIsConstructible(): void
    {
        self::assertSame([], (new RenovationCatalog())->all());
    }
}

/** A minimal definition, so the catalogue is tested without any real work. */
final readonly class FakeWork implements RenovationDefinition
{
    public function __construct(
        private string $slug,
        private SceneSlot $slot,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function slot(): SceneSlot
    {
        return $this->slot;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        return null;
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(\App\Domain\Finance\AdviceLevel::Info, 'test');
    }

    public function isEnergyPerformanceWork(): bool
    {
        return false;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/battery.svg';
    }
}
```

- [ ] **Étape 2 : lancer le test pour vérifier qu'il échoue**

Lancer : `vendor/bin/phpunit tests/Unit/Domain/Finance/RenovationCatalogTest.php`
Attendu : ÉCHEC — `Class "App\Domain\Finance\RenovationCatalog" not found`.

- [ ] **Étape 3 : créer `SceneSlot`**

`src/Domain/Finance/SceneSlot.php` :

```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance;

/**
 * The five clickable zones of the house cutaway, and the drawer each work is
 * offered in.
 *
 * Deliberately an enum, unlike the works themselves: this set is genuinely
 * closed — the zones are fixed by the artwork, and adding one means redrawing
 * the house. Here `match` exhaustiveness is a benefit at no cost.
 */
enum SceneSlot: string
{
    case Roof = 'roof';
    case Walls = 'walls';
    case Heating = 'heating';
    case Garage = 'garage';
    case Living = 'living';
}
```

- [ ] **Étape 4 : créer `RenovationOffer`**

`src/Domain/Finance/RenovationOffer.php` :

```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\Household;

/**
 * What a work declares of ITSELF: its price tag and what the house becomes.
 *
 * Deliberately NOT a {@see RenovationQuote}: the prime and the éco-PTZ
 * perimeter are POLICY, identical for every work, and they stay in
 * {@see RenovationQuoter}. Letting each definition build its own quote would
 * copy the subsidy call into fifteen classes, and the next reform of the real
 * scheme would become a fifteen-file chore.
 */
final readonly class RenovationOffer
{
    public function __construct(
        /** Player-facing description (French), possibly dynamic ("Menuiseries — Triple vitrage"). */
        public string $title,
        /** Sticker price, before any prime. */
        public Money $cost,
        /** The household configuration once the work is done. */
        public Household $resultingHousehold,
    ) {
    }
}
```

- [ ] **Étape 5 : créer `RenovationDefinition`**

`src/Domain/Finance/RenovationDefinition.php` :

```php
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
     * same real-world perimeter, so this names the underlying fact rather than
     * either of its two consequences.
     */
    public function isEnergyPerformanceWork(): bool;

    /**
     * The "done" chip for this house ("Batterie 10 kWh", "Murs — ITE"), or
     * null when the work has not been carried out.
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
```

- [ ] **Étape 6 : créer `RenovationCatalog` avec une liste par défaut vide**

La liste se remplit aux tâches 3 à 5. Elle est vide ici pour que la tâche reste
indépendamment testable.

`src/Domain/Finance/RenovationCatalog.php` :

```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use InvalidArgumentException;

use function array_values;
use function sprintf;

/**
 * The renovation tree: every work, in display order.
 *
 * The order of {@see self::defaultWorks()} IS the order the quotes appear in a
 * drawer — the template's old `worksOfSlot` array encoded it implicitly (boiler
 * repair before the heat pump, so a breakdown offers the cheap fix first) and a
 * flat catalogue would have lost it silently.
 *
 * Built from an explicit list rather than a Symfony tagged iterator: this class
 * lives in the domain, which may not depend on the container (CLAUDE.md §3).
 */
final readonly class RenovationCatalog
{
    /** @var list<RenovationDefinition> */
    private array $works;

    /** @var array<string, RenovationDefinition> */
    private array $bySlug;

    /** @param list<RenovationDefinition>|null $works */
    public function __construct(?array $works = null)
    {
        $works ??= self::defaultWorks();

        $bySlug = [];
        foreach ($works as $work) {
            $slug = $work->slug();
            if (isset($bySlug[$slug])) {
                throw new InvalidArgumentException(sprintf('Duplicate renovation slug: "%s".', $slug));
            }
            $bySlug[$slug] = $work;
        }

        $this->works = array_values($works);
        $this->bySlug = $bySlug;
    }

    public function tryGet(string $slug): ?RenovationDefinition
    {
        return $this->bySlug[$slug] ?? null;
    }

    /** @throws InvalidArgumentException when no work answers to that slug */
    public function get(string $slug): RenovationDefinition
    {
        return $this->tryGet($slug)
            ?? throw new InvalidArgumentException(sprintf('Unknown renovation: "%s".', $slug));
    }

    /** @return list<RenovationDefinition> */
    public function all(): array
    {
        return $this->works;
    }

    /** @return list<RenovationDefinition> in declaration order */
    public function forSlot(SceneSlot $slot): array
    {
        $matching = [];
        foreach ($this->works as $work) {
            if ($slot === $work->slot()) {
                $matching[] = $work;
            }
        }

        return $matching;
    }

    /**
     * The tree, in display order. ADDING A WORK = ADDING ONE LINE HERE.
     *
     * @return list<RenovationDefinition>
     */
    private static function defaultWorks(): array
    {
        return [];
    }
}
```

- [ ] **Étape 7 : lancer les tests**

Lancer : `vendor/bin/phpunit tests/Unit/Domain/Finance/RenovationCatalogTest.php`
Attendu : PASS (6 tests).

- [ ] **Étape 8 : gate qualité complet**

Lancer : `make qa`
Attendu : cs, stan, twig, test tous verts. **301 tests toujours au vert** — rien
d'existant ne doit bouger.

- [ ] **Étape 9 : commit**

```bash
git add src/Domain/Finance/SceneSlot.php src/Domain/Finance/RenovationOffer.php \
        src/Domain/Finance/RenovationDefinition.php src/Domain/Finance/RenovationCatalog.php \
        tests/Unit/Domain/Finance/RenovationCatalogTest.php
git commit -m "feat(finance): RenovationDefinition interface and ordered catalogue

The renovation tree's shape — an enum plus three exhaustive matches —
forced three classes open for every new work while covering none of the
template registrations that actually got forgotten. Introduces the
interface a work implements instead, and the ordered catalogue that
resolves it. Nothing consumes them yet; the bridge lands next.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Tâche 2 : le pont

`RenovationQuoter` et `RenovationAdvisor` consultent le catalogue d'abord et
retombent sur leur `match` sinon. Les travaux peuvent alors basculer un par un
sans jamais casser le jeu.

**Fichiers :**
- Modifier : `src/Domain/Finance/RenovationQuoter.php:28-56`
- Modifier : `src/Domain/Finance/RenovationAdvisor.php:23-38`
- Test : `tests/Unit/Domain/Finance/RenovationQuoterTest.php` (ajouts)

**Interfaces :**
- Consomme : `RenovationCatalog::tryGet()`, `RenovationDefinition::offerFor()`,
  `::isEnergyPerformanceWork()`, `::adviceFor()` (tâche 1).
- Produit : `RenovationQuoter::quote(Renovation, Household): ?RenovationQuote`
  et `RenovationAdvisor::adviceFor(Renovation, Household): RenovationAdvice`
  inchangés en signature — c'est tout l'intérêt du pont.

- [ ] **Étape 1 : écrire le test qui échoue**

Ajouter à `tests/Unit/Domain/Finance/RenovationQuoterTest.php` :

```php
    /**
     * The bridge: once a work has a definition, the quoter must price it from
     * the definition — and still apply the subsidy policy itself, which is the
     * whole reason offers are not quotes.
     */
    public function testPricesAWorkFromItsDefinitionWhenTheCatalogueKnowsIt(): void
    {
        $catalog = new RenovationCatalog([
            new StubDefinition('roof_insulation', Money::fromEuros(1000.0), isEnergyPerformanceWork: true),
        ]);
        $quoter = new RenovationQuoter(catalog: $catalog);

        $quote = $quoter->quote(Renovation::RoofInsulation, self::barePassoire());

        self::assertNotNull($quote);
        self::assertSame('Stub', $quote->title);
        self::assertSame(100_000, $quote->cost->cents);
        self::assertGreaterThan(0, $quote->subsidy->cents, 'the quoter applies the prime, not the definition');
    }

    public function testAppliesNoSubsidyToAWorkOutsideTheAidPerimeter(): void
    {
        $catalog = new RenovationCatalog([
            new StubDefinition('home_battery', Money::fromEuros(1000.0), isEnergyPerformanceWork: false),
        ]);
        $quoter = new RenovationQuoter(catalog: $catalog);

        $quote = $quoter->quote(Renovation::HomeBattery, self::barePassoire());

        self::assertNotNull($quote);
        self::assertSame(0, $quote->subsidy->cents);
    }

    /** A work with no definition yet still goes through the legacy match. */
    public function testFallsBackToTheLegacyMatchForUnmigratedWorks(): void
    {
        $quoter = new RenovationQuoter(catalog: new RenovationCatalog([]));

        self::assertNotNull($quoter->quote(Renovation::RoofInsulation, self::barePassoire()));
    }
```

Et le stub, en bas du fichier de test :

```php
/** A definition whose offer is fixed, so the quoter's own policy is what gets tested. */
final readonly class StubDefinition implements RenovationDefinition
{
    public function __construct(
        private string $slug,
        private Money $cost,
        private bool $isEnergyPerformanceWork,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Walls;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        return new RenovationOffer('Stub', $this->cost, $household);
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        return new RenovationAdvice(AdviceLevel::Info, 'stub');
    }

    public function isEnergyPerformanceWork(): bool
    {
        return $this->isEnergyPerformanceWork;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/battery.svg';
    }
}
```

- [ ] **Étape 2 : lancer le test pour vérifier qu'il échoue**

Lancer : `vendor/bin/phpunit tests/Unit/Domain/Finance/RenovationQuoterTest.php`
Attendu : ÉCHEC — `Unknown named parameter $catalog`.

- [ ] **Étape 3 : brancher le pont dans le quoter**

Dans `src/Domain/Finance/RenovationQuoter.php`, ajouter la dépendance au
constructeur :

```php
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
        private SubsidyCalculator $subsidy = new SubsidyCalculator(),
        private EnergyCalibration $energy = new EnergyCalibration(),
        private RenovationCatalog $catalog = new RenovationCatalog(),
    ) {
    }
```

Puis, en tête de `quote()`, avant le `match` existant :

```php
    public function quote(Renovation $work, Household $household): ?RenovationQuote
    {
        // Bridge, while works migrate one by one: a definition wins over the
        // legacy match. The match shrinks at each batch and dies in task 6.
        $definition = $this->catalog->tryGet($work->value);
        if (null !== $definition) {
            return $this->fromDefinition($work, $definition, $household);
        }

        return match ($work) {
            // …inchangé…
        };
    }

    /**
     * Turns a work's own offer into a signable quote by applying the FINANCING
     * POLICY — the prime perimeter and rate. That policy is identical for every
     * work, which is exactly why definitions declare offers and not quotes.
     */
    private function fromDefinition(Renovation $work, RenovationDefinition $definition, Household $household): ?RenovationQuote
    {
        $offer = $definition->offerFor($household);
        if (null === $offer) {
            return null;
        }

        return new RenovationQuote(
            work: $work,
            title: $offer->title,
            cost: $offer->cost,
            subsidy: $definition->isEnergyPerformanceWork()
                ? $this->subsidy->subsidyFor($offer->cost)
                : Money::zero(),
            resultingHousehold: $offer->resultingHousehold,
        );
    }
```

- [ ] **Étape 4 : brancher le pont dans l'advisor**

Dans `src/Domain/Finance/RenovationAdvisor.php` :

```php
    public function __construct(
        private BuildingCalibration $building = new BuildingCalibration(),
        private RenovationCatalog $catalog = new RenovationCatalog(),
    ) {
    }

    /** Every work carries a word of advice — the match below is exhaustive. */
    public function adviceFor(Renovation $work, Household $household): RenovationAdvice
    {
        $definition = $this->catalog->tryGet($work->value);
        if (null !== $definition) {
            return $definition->adviceFor($household);
        }

        $poorlyInsulated = /* …inchangé… */;

        return match ($work) {
            // …inchangé…
        };
    }
```

- [ ] **Étape 5 : lancer les tests**

Lancer : `vendor/bin/phpunit tests/Unit/Domain/Finance/`
Attendu : PASS. Les tests existants du quoter et de l'advisor passent toujours —
le catalogue par défaut étant vide, ils empruntent tous le repli.

- [ ] **Étape 6 : gate qualité complet**

Lancer : `make qa`
Attendu : tout vert, **301 tests**.

- [ ] **Étape 7 : commit**

```bash
git add src/Domain/Finance/RenovationQuoter.php src/Domain/Finance/RenovationAdvisor.php \
        tests/Unit/Domain/Finance/RenovationQuoterTest.php
git commit -m "refactor(finance): bridge the catalogue into quoter and advisor

A definition now wins over the legacy match, so works can migrate one
batch at a time with the game never broken. The quoter keeps the
financing policy — prime perimeter and rate — which is why definitions
declare offers rather than finished quotes.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Tâches 3 à 5 : migrer les 15 travaux

Les trois tâches suivent **exactement la même forme**, par lot de tiroir. Pour
chaque travail : un test unitaire, une classe, l'ajout d'une ligne dans
`defaultWorks()`, la suppression du bras de `match` correspondant dans le
quoter (et sa méthode privée) puis dans l'advisor.

**Contrat commun à toutes les classes de travail :**

- Namespace `App\Domain\Finance\Work`, fichier
  `src/Domain/Finance/Work/<Nom>Work.php`, classe `final readonly`.
- Les calibrations s'injectent par défaut de constructeur
  (`private FinanceCalibration $calibration = new FinanceCalibration()`), comme
  partout dans le codebase.
- `offerFor()` reprend **mot pour mot** le corps de la méthode privée
  correspondante du quoter, **moins** l'appel à `subsidyFor()` et **moins** le
  `work:` — le titre, le coût et le foyer résultant seulement.
- `adviceFor()` reprend **mot pour mot** les messages français du bras
  correspondant de `RenovationAdvisor`. **Ne pas les reformuler** : ce sont des
  textes pédagogiques relus, et le plan ne change aucun libellé joueur.
- `isEnergyPerformanceWork()` retourne ce que `Renovation::isSubsidised()`
  retournait pour ce travail.
- `iconAsset()` retourne le chemin de l'asset, relatif à `templates/`. **Tous
  ces fichiers existent** depuis la tâche 0 ; le test de chaque travail doit
  vérifier que le fichier pointé existe réellement :

```php
    public function testIconAssetPointsAtARealFile(): void
    {
        self::assertFileExists(__DIR__.'/../../../../../templates/'.(new HeatPumpWork())->iconAsset());
    }
```

**Table de référence — les 15 travaux.** Elle est la source unique pour les
tâches 3 à 5 ; ne pas la deviner travail par travail.

| slug | classe | slot | `isEnergyPerformanceWork` | `sceneLayerFor` | `iconAsset` |
|---|---|---|---|---|---|
| `boiler_repair` | `BoilerRepairWork` | Heating | `false` | `null` | `boiler-fioul.svg` |
| `heat_pump` | `HeatPumpWork` | Heating | `true` | `'heating-heat-pump'` | `heat-pump.svg` |
| `pellet_boiler` | `PelletBoilerWork` | Heating | `true` | `'heating-pellet'` | `boiler-pellet.svg` |
| `low_temp_emitters` | `LowTempEmittersWork` | Heating | `true` | `'floor-heating'` | `icons/low-temp-emitters.svg` |
| `water_heater_thermo` | `WaterHeaterThermoWork` | Heating | `true` | `'water-heater-thermo'` | `water-heater-thermo.svg` |
| `roof_insulation` | `RoofInsulationWork` | Walls | `true` | `'roof-ins'` | `icons/insulation.svg` |
| `wall_insulation_interior` | `WallInsulationInteriorWork` | Walls | `true` | `'walls-interior'` | `icons/insulation.svg` |
| `wall_insulation_exterior` | `WallInsulationExteriorWork` | Walls | `true` | `'walls-exterior'` | `icons/insulation.svg` |
| `glazing` | `GlazingWork` | Walls | `true` | `'glazing-double'` / `'glazing-triple'` | `icons/glazing.svg` |
| `ventilation_double_flow` | `VentilationDoubleFlowWork` | Walls | `true` | `'vmc-double-flow'` | `icons/ventilation-double-flow.svg` |
| `solar_panels` | `SolarPanelsWork` | Roof | `false` | `'solar-full'` | `solar-panels.svg` |
| `solar_kit` | `SolarKitWork` | Garage | `false` | `'solar-kit'` | `solar-kit.svg` |
| `home_battery` | `HomeBatteryWork` | Garage | `false` | `'battery'` | `battery.svg` |
| `draught_proofing` | `DraughtProofingWork` | Living | `false` | `null` | `icons/draught-proofing.svg` |
| `thermal_curtains` | `ThermalCurtainsWork` | Living | `false` | `'curtains'` | `icons/thermal-curtains.svg` |

Chemins relatifs à `templates/game/scene/assets/`. Les six `icons/*.svg` sont créés en tâche 0.

**Deux `null` volontaires dans `sceneLayerFor`**, à ne pas « corriger » :
- `boiler_repair` répare la chaudière fioul — il restaure l'état de départ, il
  n'ajoute rien à dessiner. L'état `fioul-broken` reste porté par
  `HouseSceneView`, pas par un calque de travail.
- `draught_proofing` n'a pas de visuel : des joints de fenêtre à cette échelle
  ne se voient pas. Exception identifiée et assumée en tranche 7.

**L'ordre de `defaultWorks()`** doit reproduire l'ordre des tableaux
`worksOfSlot` d'aujourd'hui (`templates/game/panel/_slot.html.twig:14-20`) :

```php
        return [
            // Heating — the repair comes first: on a breakdown, the cheap fix
            // must be the first thing offered.
            new BoilerRepairWork(),
            new HeatPumpWork(),
            new PelletBoilerWork(),
            new LowTempEmittersWork(),
            new WaterHeaterThermoWork(),
            // Envelope
            new RoofInsulationWork(),
            new WallInsulationInteriorWork(),
            new WallInsulationExteriorWork(),
            new GlazingWork(),
            new VentilationDoubleFlowWork(),
            // Production & storage
            new SolarPanelsWork(),
            new SolarKitWork(),
            new HomeBatteryWork(),
            // Daily gestures
            new DraughtProofingWork(),
            new ThermalCurtainsWork(),
        ];
```

### Tâche 3 : le tiroir chauffage (5 travaux)

**Fichiers :**
- Créer : `src/Domain/Finance/Work/BoilerRepairWork.php`,
  `HeatPumpWork.php`, `PelletBoilerWork.php`, `LowTempEmittersWork.php`,
  `WaterHeaterThermoWork.php`
- Modifier : `src/Domain/Finance/RenovationCatalog.php` (`defaultWorks()`)
- Modifier : `src/Domain/Finance/RenovationQuoter.php` (retirer 5 bras + 5 méthodes privées)
- Modifier : `src/Domain/Finance/RenovationAdvisor.php` (retirer 5 bras)
- Test : `tests/Unit/Domain/Finance/Work/BoilerRepairWorkTest.php` et les 4 autres

**Interfaces :**
- Consomme : `RenovationDefinition`, `RenovationOffer`, `SceneSlot` (tâche 1) ;
  `Household::withHeatingSystem()`, `::withBoilerBroken()`,
  `::withLowTempEmitters()`, `::withWaterHeater()` (existants).
- Produit : 5 classes enregistrées dans `defaultWorks()`.

- [ ] **Étape 1 : écrire le test qui échoue pour `HeatPumpWork`**

`tests/Unit/Domain/Finance/Work/HeatPumpWorkTest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Finance\Work;

use App\Domain\Building\EnvelopeState;
use App\Domain\Building\Glazing;
use App\Domain\Building\HeatingSystem;
use App\Domain\Building\Household;
use App\Domain\Building\WallInsulation;
use App\Domain\Finance\AdviceLevel;
use App\Domain\Finance\SceneSlot;
use App\Domain\Finance\Work\HeatPumpWork;
use PHPUnit\Framework\TestCase;

final class HeatPumpWorkTest extends TestCase
{
    private static function barePassoire(): Household
    {
        return new Household(
            solarKwc: 0.0,
            batteryKwh: 0.0,
            envelope: new EnvelopeState(false, WallInsulation::None, Glazing::Single),
            heatingSystem: HeatingSystem::FuelOilBoiler,
        );
    }

    public function testIdentity(): void
    {
        $work = new HeatPumpWork();

        self::assertSame('heat_pump', $work->slug());
        self::assertSame(SceneSlot::Heating, $work->slot());
        self::assertTrue($work->isEnergyPerformanceWork());
    }

    public function testOffersAHeatPumpToAFuelOilHouse(): void
    {
        $offer = (new HeatPumpWork())->offerFor(self::barePassoire());

        self::assertNotNull($offer);
        self::assertSame('Pompe à chaleur air/eau', $offer->title);
        self::assertGreaterThan(0, $offer->cost->cents);
        self::assertSame(HeatingSystem::HeatPump, $offer->resultingHousehold->heatingSystem);
    }

    public function testOffersNothingOnceInstalled(): void
    {
        $installed = self::barePassoire()->withHeatingSystem(HeatingSystem::HeatPump);

        self::assertNull((new HeatPumpWork())->offerFor($installed));
    }

    /**
     * The one real sequencing mistake this work can hide: a heat pump in a
     * passoire is oversized and the bills stay high. Advice, never a ban.
     */
    public function testCautionsAgainstAHeatPumpInAPoorlyInsulatedHouse(): void
    {
        $advice = (new HeatPumpWork())->adviceFor(self::barePassoire());

        self::assertSame(AdviceLevel::Caution, $advice->level);
    }

    public function testDoneLabelAndSceneLayerAppearOnlyOnceInstalled(): void
    {
        $work = new HeatPumpWork();
        $installed = self::barePassoire()->withHeatingSystem(HeatingSystem::HeatPump);

        self::assertNull($work->doneLabelFor(self::barePassoire()));
        self::assertNull($work->sceneLayerFor(self::barePassoire()));
        self::assertSame('Pompe à chaleur', $work->doneLabelFor($installed));
        self::assertSame('heating-heat-pump', $work->sceneLayerFor($installed));
    }
}
```

- [ ] **Étape 2 : lancer le test pour vérifier qu'il échoue**

Lancer : `vendor/bin/phpunit tests/Unit/Domain/Finance/Work/HeatPumpWorkTest.php`
Attendu : ÉCHEC — `Class "App\Domain\Finance\Work\HeatPumpWork" not found`.

- [ ] **Étape 3 : écrire `HeatPumpWork`**

`src/Domain/Finance/Work/HeatPumpWork.php` :

```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance\Work;

use App\Domain\Building\BuildingCalibration;
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
 * Air-to-water heat pump: the way out of fuel oil, and the work whose payoff
 * depends most on what was done before it (an uninsulated house forces an
 * oversized machine that never pays back).
 */
final readonly class HeatPumpWork implements RenovationDefinition
{
    public function __construct(
        private FinanceCalibration $calibration = new FinanceCalibration(),
        private BuildingCalibration $building = new BuildingCalibration(),
    ) {
    }

    public function slug(): string
    {
        return 'heat_pump';
    }

    public function slot(): SceneSlot
    {
        return SceneSlot::Heating;
    }

    public function offerFor(Household $household): ?RenovationOffer
    {
        if (HeatingSystem::HeatPump === $household->heatingSystem) {
            return null;
        }

        return new RenovationOffer(
            title: 'Pompe à chaleur air/eau',
            cost: Money::fromEuros($this->calibration->heatPumpInstallCost()->value),
            resultingHousehold: $household->withHeatingSystem(HeatingSystem::HeatPump),
        );
    }

    public function adviceFor(Household $household): RenovationAdvice
    {
        $poorlyInsulated = $this->building->envelopeLossFactor($household->envelope)
            > $this->building->poorlyInsulatedEnvelopeCeiling()->value;

        return match (true) {
            $household->boilerBroken => new RenovationAdvice(AdviceLevel::Info, 'L\'occasion de sortir du fioul. Vérifiez que la maison est un minimum isolée, sinon la PAC sera bridée.'),
            $poorlyInsulated => new RenovationAdvice(AdviceLevel::Caution, 'Maison peu isolée → PAC surdimensionnée, factures qui resteront hautes. Isolez d\'abord.'),
            default => new RenovationAdvice(AdviceLevel::Info, 'Bon rendement attendu : la maison est suffisamment isolée pour une PAC efficace.'),
        };
    }

    public function isEnergyPerformanceWork(): bool
    {
        return true;
    }

    public function doneLabelFor(Household $household): ?string
    {
        return HeatingSystem::HeatPump === $household->heatingSystem ? 'Pompe à chaleur' : null;
    }

    public function sceneLayerFor(Household $household): ?string
    {
        return HeatingSystem::HeatPump === $household->heatingSystem ? 'heating-heat-pump' : null;
    }

    public function iconAsset(): string
    {
        return 'game/scene/assets/heat-pump.svg';
    }
}
```

- [ ] **Étape 4 : enregistrer le travail et retirer son ancien code**

Dans `RenovationCatalog::defaultWorks()`, remplacer `return [];` par
`return [new HeatPumpWork()];` (les autres s'ajoutent aux étapes suivantes, dans
l'ordre du bloc de référence ci-dessus).

Dans `RenovationQuoter` : supprimer le bras `Renovation::HeatPump => …` et la
méthode privée `heatPumpQuote()`.

Dans `RenovationAdvisor` : supprimer le bras `Renovation::HeatPump => …`.

- [ ] **Étape 5 : lancer les tests**

Lancer : `vendor/bin/phpunit tests/Unit/Domain/Finance/`
Attendu : PASS. Les tests existants du quoter et de l'advisor pour la PAC
passent désormais **par la définition** sans avoir été modifiés — c'est la
preuve que le pont fonctionne.

- [ ] **Étape 6 : répéter les étapes 1 à 5 pour les 4 travaux restants du tiroir**

Dans cet ordre : `BoilerRepairWork`, `PelletBoilerWork`, `LowTempEmittersWork`,
`WaterHeaterThermoWork`.

Pour chacun, se reporter au contrat commun et à la table de référence
ci-dessus, et reprendre **mot pour mot** le corps de la méthode privée du
quoter et les messages de l'advisor. Points d'attention propres à ce lot :

- **`BoilerRepairWork`** — `offerFor()` retourne `null` sauf si
  `$household->boilerBroken`. `isEnergyPerformanceWork()` = `false` (réparer un
  équipement fossile n'est pas de la performance énergétique). `doneLabelFor()`
  retourne toujours `null` : une réparation ne laisse pas de trace à afficher,
  elle restaure l'état normal.
- **`LowTempEmittersWork`** — l'`adviceFor()` a deux branches selon
  `HeatingSystem::HeatPump === $household->heatingSystem`, toutes deux en
  `AdviceLevel::Info` (pas de `Caution` : poser des émetteurs BT sans PAC n'est
  pas une faute, juste sans effet immédiat).
- **`WaterHeaterThermoWork`** — `doneLabelFor()` retourne
  `$household->waterHeater->label()` quand il vaut `WaterHeater::Thermodynamic`,
  `null` sinon. Le ballon électrique de départ n'est pas un travail fait.

- [ ] **Étape 7 : gate qualité complet**

Lancer : `make qa`
Attendu : tout vert, **301 tests + les nouveaux tests unitaires**. Aucun test
d'intégration modifié.

- [ ] **Étape 8 : commit**

```bash
git add src/Domain/Finance/Work/ src/Domain/Finance/RenovationCatalog.php \
        src/Domain/Finance/RenovationQuoter.php src/Domain/Finance/RenovationAdvisor.php \
        tests/Unit/Domain/Finance/Work/
git commit -m "refactor(finance): the heating drawer's works become definitions

Five works — boiler repair, heat pump, pellet boiler, low-temp emitters,
thermodynamic tank — each move into a single class carrying their offer,
advice, done label, scene layer and icon. Ten match arms and five private
methods go with them. The rendered game is byte-identical.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

### Tâche 4 : le tiroir enveloppe (5 travaux)

Même forme que la tâche 3, pour `RoofInsulationWork`,
`WallInsulationInteriorWork`, `WallInsulationExteriorWork`, `GlazingWork`,
`VentilationDoubleFlowWork`.

**Fichiers :** créer les 5 classes dans `src/Domain/Finance/Work/` et leurs
5 tests dans `tests/Unit/Domain/Finance/Work/` ; modifier `RenovationCatalog`,
`RenovationQuoter`, `RenovationAdvisor`.

**Interfaces :** consomme `EnvelopeState::withRoofInsulated()`, `::withWalls()`,
`::withGlazing()`, `::withVentilation()` (existants).

- [ ] **Étape 1 : `RoofInsulationWork`** — test, puis classe, puis
      enregistrement + retrait des bras de `match`, puis
      `vendor/bin/phpunit tests/Unit/Domain/Finance/`
- [ ] **Étape 2 : `WallInsulationInteriorWork`** — idem
- [ ] **Étape 3 : `WallInsulationExteriorWork`** — idem
- [ ] **Étape 4 : `GlazingWork`** — idem, plus le test de double palier ci-dessous
- [ ] **Étape 5 : `VentilationDoubleFlowWork`** — idem
- [ ] **Étape 6 : `make qa`** — tout vert, aucun test d'intégration modifié
- [ ] **Étape 7 : commit** (message ci-dessous)

Points d'attention propres à ce lot :

- **ITI et ITE sont mutuellement exclusifs.** Les deux classes retournent `null`
  dès que `WallInsulation::None !== $household->envelope->walls` — y compris
  pour l'autre variante. Le commentaire existant du quoter explique pourquoi ;
  le reprendre.
- **`GlazingWork` est le seul travail à paliers.** `offerFor()` calcule sa
  cible (`Single → Double`, `Double → Triple`, `Triple → null`) et son titre
  est dynamique : `sprintf('Menuiseries — %s', $target->label())`. C'est aussi
  le seul travail où `doneLabelFor()` **et** `offerFor()` répondent non-null en
  même temps (en double vitrage : fait, et améliorable). Écrire un test qui
  verrouille explicitement ce cas :

```php
    public function testDoubleGlazingIsBothDoneAndUpgradeable(): void
    {
        $work = new GlazingWork();
        $house = self::barePassoire()->withEnvelope(
            self::barePassoire()->envelope->withGlazing(Glazing::Double),
        );

        self::assertNotNull($work->doneLabelFor($house), 'double glazing shows a done chip');
        self::assertNotNull($work->offerFor($house), 'and still offers the triple upgrade');
        self::assertSame('glazing-double', $work->sceneLayerFor($house));
    }
```

- **`GlazingWork::sceneLayerFor()`** dépend du palier atteint :
  `'glazing-double'`, `'glazing-triple'`, ou `null` en simple vitrage.
- **`GlazingWork::adviceFor()` et `VentilationDoubleFlowWork::adviceFor()`**
  portent chacun un `AdviceLevel::Caution` conditionné à `$poorlyInsulated` —
  ce sont deux des trois seules cautions du jeu. Reprendre les messages mot
  pour mot et tester la branche `Caution`.

Terminer par `make qa` (tout vert) puis :

```bash
git commit -m "refactor(finance): the envelope drawer's works become definitions

Roof, ITI, ITE, glazing and double-flow ventilation each move into their
own class. Glazing carries the only tiered offer — double is both a done
chip and an upgrade path to triple — and keeps the two sequencing
cautions verbatim.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

### Tâche 5 : production, stockage et gestes (5 travaux)

Même forme, pour `SolarPanelsWork`, `SolarKitWork`, `HomeBatteryWork`,
`DraughtProofingWork`, `ThermalCurtainsWork`.

**Interfaces :** consomme `Household::withSolarKwc()`, `::withBatteryKwh()`,
`EnvelopeState::withDraughtProofed()`, `::withThermalCurtains()`,
`EnergyCalibration::solarKitPeakPowerKwc()`, `::defaultSolarPeakPowerKwc()`,
`::defaultBatteryCapacityKwh()` (existants).

- [ ] **Étape 1 : `SolarPanelsWork`** — test, puis classe, puis enregistrement
      + retrait des bras de `match`, puis `vendor/bin/phpunit tests/Unit/Domain/Finance/`
- [ ] **Étape 2 : `SolarKitWork`** — idem
- [ ] **Étape 3 : `HomeBatteryWork`** — idem, plus le test de prérequis ci-dessous
- [ ] **Étape 4 : `DraughtProofingWork`** — idem, plus le test d'exception « pas de calque »
- [ ] **Étape 5 : `ThermalCurtainsWork`** — idem
- [ ] **Étape 6 : rétablir le test neutralisé de la tâche 1** (voir plus bas)
- [ ] **Étape 7 : `make qa`** — tout vert
- [ ] **Étape 8 : commit** (message ci-dessous)

Points d'attention propres à ce lot — ce sont trois règles de disponibilité
subtiles, chacune avec sa raison ; les reprendre avec leur commentaire :

- **`SolarKitWork`** n'est offert que si `0.0 === $household->solarKwc` : le kit
  est le point d'entrée pas cher, supplanté par l'installation complète.
- **`SolarPanelsWork`** est offert tant que
  `$household->solarKwc < defaultSolarPeakPowerKwc()` — le seuil est la
  puissance de l'installation complète, **pas zéro**, ce qui en fait aussi la
  montée en gamme depuis le kit.
- **`HomeBatteryWork`** exige `$household->solarKwc > 0.0` en plus de
  `0.0 === $household->batteryKwh` : une batterie sans production ne stockerait
  rien. Écrire un test qui verrouille ce prérequis :

```php
    public function testOffersNoBatteryBeforeAnySolarIsInstalled(): void
    {
        self::assertNull((new HomeBatteryWork())->offerFor(self::barePassoire()));
    }
```

- **`DraughtProofingWork::sceneLayerFor()` retourne toujours `null`.** Écrire le
  test qui verrouille l'exception, avec sa raison, pour qu'une relecture future
  ne la prenne pas pour un oubli :

```php
    /**
     * The only work with no visual at all: window seals are invisible at this
     * scale. A deliberate exception, identified in tranche 7 — not a gap.
     */
    public function testHasNoSceneLayer(): void
    {
        $done = self::barePassoire()->withEnvelope(
            self::barePassoire()->envelope->withDraughtProofed(true),
        );

        self::assertNull((new DraughtProofingWork())->sceneLayerFor($done));
        self::assertNotNull((new DraughtProofingWork())->doneLabelFor($done), 'but the drawer still shows the chip');
    }
```

À la fin de cette tâche, `defaultWorks()` contient les 15 travaux, et le `match`
de `RenovationQuoter` comme celui de `RenovationAdvisor` sont **vides**.

- [ ] **Renforcer le test du catalogue par défaut**

Le catalogue étant désormais plein, remplacer
`testTheDefaultCatalogueIsConstructible` par l'assertion réelle :

```php
    public function testDefaultCatalogueExposesEveryWorkExactlyOnce(): void
    {
        $catalog = new RenovationCatalog();

        $slugs = array_map(
            static fn (RenovationDefinition $w): string => $w->slug(),
            $catalog->all(),
        );

        self::assertCount(15, $slugs);
        self::assertSame($slugs, array_unique($slugs));
    }
```

Terminer par `make qa` (tout vert) puis :

```bash
git commit -m "refactor(finance): production, storage and gestures become definitions

The last five works move across, so every match arm is now empty. The
three subtle availability rules travel with their reasons: the kit is
superseded by the full install, the full install upgrades the kit, and a
battery without panels would store nothing.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Tâche 6 : supprimer l'enum

**Fichiers :**
- Supprimer : `src/Domain/Finance/Renovation.php`,
  `src/Domain/Finance/RenovationAdvisor.php`,
  `tests/Unit/Domain/Finance/RenovationAdvisorTest.php`
- Modifier : `src/Domain/Finance/RenovationQuote.php:17`
- Modifier : `src/Domain/Finance/RenovationQuoter.php`
- Modifier : `src/Application/RenovationHandler.php:44-71`
- Modifier : `src/Application/GameViewFactory.php:547-581`
- Modifier : `src/Twig/Components/GameDashboard.php:169-181`
- Modifier : `tests/Unit/Domain/Finance/RenovationQuoterTest.php`,
  `tests/Unit/Application/RenovationHandlerTest.php`

**Interfaces :**
- Produit : `RenovationQuoter::quote(RenovationDefinition, Household): ?RenovationQuote`,
  `RenovationHandler::order(GameState, string $slug, string $financing): GameState|string`,
  `RenovationQuote::$workSlug` (string).

- [ ] **Étape 1 : écrire le test qui échoue**

Ajouter à `tests/Unit/Application/RenovationHandlerTest.php` :

```php
    public function testRefusesAnUnknownWorkSlug(): void
    {
        $result = $this->handler()->order($this->state(), 'nope', RenovationHandler::FINANCING_CASH);

        self::assertIsString($result, 'an unknown slug is refused, never fatal');
    }
```

- [ ] **Étape 2 : lancer le test pour vérifier qu'il échoue**

Lancer : `vendor/bin/phpunit tests/Unit/Application/RenovationHandlerTest.php`
Attendu : ÉCHEC — `order()` attend `Renovation`, pas `string`.

- [ ] **Étape 3 : basculer `RenovationQuote` sur le slug**

Dans `src/Domain/Finance/RenovationQuote.php`, remplacer le champ `work` :

```php
        /** Slug of the work this quote prices ({@see RenovationDefinition::slug()}). */
        public string $workSlug,
```

- [ ] **Étape 4 : réduire le quoter à la politique de financement**

`src/Domain/Finance/RenovationQuoter.php` devient, en entier :

```php
<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Building\Household;

/**
 * Turns a work's own offer into a signable quote by applying the FINANCING
 * POLICY: the prime perimeter and its income-based rate.
 *
 * That policy is identical for every work, which is exactly why definitions
 * declare offers ({@see RenovationOffer}) and not finished quotes — the next
 * reform of the real scheme is a change here, not across fifteen classes.
 *
 * Works are instantaneous in Phase 0-1 (no permitting/construction delays yet
 * — the real « délai » access-cost lever arrives with later phases).
 */
final readonly class RenovationQuoter
{
    public function __construct(
        private SubsidyCalculator $subsidy = new SubsidyCalculator(),
    ) {
    }

    /** Null when the work does not apply to this house — the UI hides it. */
    public function quote(RenovationDefinition $work, Household $household): ?RenovationQuote
    {
        $offer = $work->offerFor($household);
        if (null === $offer) {
            return null;
        }

        return new RenovationQuote(
            workSlug: $work->slug(),
            title: $offer->title,
            cost: $offer->cost,
            subsidy: $work->isEnergyPerformanceWork()
                ? $this->subsidy->subsidyFor($offer->cost)
                : Money::zero(),
            resultingHousehold: $offer->resultingHousehold,
        );
    }
}
```

- [ ] **Étape 5 : basculer `RenovationHandler` sur le slug**

Dans `src/Application/RenovationHandler.php`, ajouter
`private RenovationCatalog $catalog = new RenovationCatalog()` au constructeur,
et remplacer l'ouverture d'`order()` :

```php
    /**
     * @return GameState|string the renovated state, or a French refusal message
     */
    public function order(GameState $state, string $workSlug, string $financing): GameState|string
    {
        $work = $this->catalog->tryGet($workSlug);
        if (null === $work) {
            return 'Ces travaux ne sont pas (ou plus) disponibles.';
        }

        $quote = $this->quoter->quote($work, $state->household);
        if (null === $quote) {
            return 'Ces travaux ne sont pas (ou plus) disponibles.';
        }
```

et, plus bas, `!$work->isLoanEligible()` devient
`!$work->isEnergyPerformanceWork()`.

- [ ] **Étape 6 : basculer `GameViewFactory`**

Dans `src/Application/GameViewFactory.php`, injecter
`private RenovationCatalog $catalog = new RenovationCatalog()`, retirer
`private RenovationAdvisor $advisor`, et réécrire `actionsFor()` :

```php
        foreach ($this->catalog->all() as $work) {
            $quote = $this->quoter->quote($work, $state->household);
            if (null === $quote) {
                continue;
            }

            // The current house's reference year is shared; each work gets its own.
            $after = $this->estimator->estimate($quote->resultingHousehold);

            $net = $quote->netCost();
            $advice = $work->adviceFor($state->household);

            $actions[$work->slug()] = new ActionView(
                work: $work->slug(),
                title: $quote->title,
                costLabel: $quote->cost->format(),
                subsidyLabel: $quote->subsidy->cents > 0 ? $quote->subsidy->format() : '',
                netCostLabel: $net->format(),
                cashAllowed: $state->savings->cents >= $net->cents,
                loanAllowed: $loanEligible = ($work->isEnergyPerformanceWork()
                    && $state->loan->borrowedTotal->plus($net)->cents <= $loanCap->cents),
                loanMonthlyLabel: $loanEligible ? Loan::none()->borrow($net)->monthlyPayment->format() : '',
                effectLabels: $this->effectLabels($before, $after),
                adviceLevel: $advice->level->value,
                adviceMessage: $advice->message,
            );
        }
```

- [ ] **Étape 7 : basculer `GameDashboard`**

Dans `src/Twig/Components/GameDashboard.php:169-181`, retirer l'import de
`Renovation` et la résolution `Renovation::tryFrom($work)` : passer `$work`
(déjà un `string`) directement à `$this->renovations->order()`. Le handler
refuse désormais lui-même les slugs inconnus, donc la garde locale disparaît.

- [ ] **Étape 8 : supprimer l'enum et l'advisor**

```bash
git rm src/Domain/Finance/Renovation.php \
       src/Domain/Finance/RenovationAdvisor.php \
       tests/Unit/Domain/Finance/RenovationAdvisorTest.php
```

Les cas de `RenovationQuoterTest` qui testaient les 15 travaux un par un sont
désormais couverts par les tests de définition : retirer les doublons, garder
les tests de **politique** (prime appliquée / non appliquée, plafond,
écrêtement) qui sont la responsabilité restante du quoter.

- [ ] **Étape 9 : vérifier qu'aucune référence ne subsiste**

Lancer : `grep -rn "Renovation::\|RenovationAdvisor\|isLoanEligible\|isSubsidised" src tests`
Attendu : **aucun résultat**.

- [ ] **Étape 10 : gate qualité complet**

Lancer : `make qa`
Attendu : tout vert. **Les tests d'intégration passent sans avoir été
modifiés** — c'est le critère de réussite du plan entier.

- [ ] **Étape 11 : commit**

```bash
git add -A
git commit -m "refactor(finance): drop the Renovation enum and the advisor

The three exhaustive matches are gone: works are resolved by slug through
the catalogue, and the quoter keeps only the financing policy it alone is
responsible for. The domain is now closed to modification — adding a work
is a new class plus one line.

Integration tests pass untouched: nothing about the rendered game changed.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Vérification finale

- [ ] `make qa` vert.
- [ ] `grep -rn "Renovation::" src tests` ne renvoie rien.
- [ ] `src/Domain/Finance/RenovationQuoter.php` fait moins de 60 lignes (contre 295).
- [ ] Aucun fichier de `tests/Integration/` n'apparaît dans le diff cumulé du plan.
- [ ] `php bin/console app:simulate:demo --days 14 --from 2025-01-01` tourne sans erreur.
- [ ] Ouvrir le jeu, commander un travail au comptant et un à l'éco-PTZ dans deux
      tiroirs différents : mêmes devis, mêmes montants, même ordre d'affichage
      qu'avant le plan.

## Suite

Plan 2 (paliers 4-6) : `activeLayers` dans `HouseSceneView` et boucle dans
`HouseShell` ; `doneLabelsBySlot` dans `GameView` et `_slot.html.twig` piloté par
le catalogue ; extraction des 6 icônes SVG inline et branchement d'`iconAsset()`.
C'est lui qui retire les ~10 champs « done » de `GameView` et ferme la couche
vue — ce plan-ci n'y touche pas.
