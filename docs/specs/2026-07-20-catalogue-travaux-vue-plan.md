# Catalogue de travaux — fermeture de la couche vue (plan d'implémentation)

> **Pour l'exécutant :** SOUS-COMPÉTENCE REQUISE — utiliser
> `superpowers:subagent-driven-development` (recommandé) ou
> `superpowers:executing-plans` pour dérouler ce plan tâche par tâche. Les
> étapes utilisent la syntaxe `- [ ]` pour le suivi.

**But :** fermer la couche vue à la modification pour les travaux — après ce
plan, ajouter un travail ne touche plus `_slot.html.twig`, `QuoteCard`, ni (pour
l'enveloppe) `HouseShell`. Le catalogue pilote les tiroirs, les puces « fait »,
les icônes, et les calques d'enveloppe de la scène.

**Architecture — décision hybride (validée) :** la scène a deux familles de
visuels aux mécaniques incompatibles. Les **calques d'enveloppe** (isolation,
vitrage, VMC, rideaux, calfeutrage, plancher chauffant) sont des gates CSS
`house--*` : ils passent par le catalogue (`sceneLayerFor()` →
`HouseSceneView::$envelopeLayers` → boucle dans `HouseShell`). Les **équipements**
(PAC, granulés, solaire, batterie, chauffe-eau) sélectionnent l'affichage d'un
composant `<twig:scene:*>` entier : ils **restent** pilotés par
`HouseSceneView ← Household`, inchangés. Cela ferme la scène là où les nouveaux
travaux s'accumulent (l'enveloppe), garde l'équipement sur le chemin `Household`
— cohérent avec une future extraction `Equipment` (cf. backlog) — et restreint
`sceneLayerFor()` à un seul type de consommateur, ce qui **corrige** le défaut
de conception relevé à la revue finale du plan 1.

**Stack :** PHP 8.4, PHPUnit 13, PHPStan niveau 8, php-cs-fixer, twig-cs-fixer.

**Spec de référence :** `docs/specs/2026-07-18-catalogue-travaux-design.md`
(paliers 4-6 du §7, avec la correction du palier 4 enregistrée dans son §3).

## Périmètre

Ce plan couvre les **paliers 4 à 6** de la spec. Le plan 1
(`docs/specs/2026-07-19-catalogue-travaux-domaine-plan.md`) a fermé le domaine ;
ce plan ferme la vue.

**Ce que ce plan NE fait PAS**, délibérément (décision hybride) : fermer la
scène pour les **équipements**. Ajouter un équipement avec un visuel touchera
encore `_cutaway.html.twig` (un `{% if scene.xxxState %}` + un composant). C'est
assumé — c'est le chemin `Household`, futur territoire `Equipment`.

**Le palier 6 (extraction des icônes) est déjà fait** : c'était la tâche 0 du
plan 1. Il ne reste du palier 6 que le branchement d'`iconAsset()` dans
`QuoteCard` (tâche 5 ici).

## Contraintes globales

Copiées du `CLAUDE.md`, elles s'appliquent implicitement à chaque tâche.

- `src/Domain/**` : **interdit** d'importer Symfony, Doctrine ou tout vendor de
  framework. PHP natif uniquement.
- `declare(strict_types=1)` partout ; classes `final` ; VOs `final readonly`.
- Identifiants et commentaires de code en **anglais** ; libellés joueur en
  **français**.
- **Aucun nombre magique inline.** Ce plan ne crée aucun coefficient.
- **Composants d'UI = Twig anonymes** (`{% props %}` + classe de variante), pas
  de macro. `HouseShell` est déjà anonyme.
- **CSS : variables sémantiques**, pas de valeurs en dur dupliquées. Ce plan ne
  touche pas au CSS (les gates existent déjà).
- PHPStan niveau 8, **corriger la cause** : pas de `@phpstan-ignore`, pas de
  baseline, pas d'`assert()`/`@var` pour forcer un type, pas de cast.
- `make qa` vert avant chaque commit.
- Chaque brique arrive **avec ses tests dans le même commit**.

## Critère de réussite

**Le jeu rendu est byte-identique.** Ce plan déplace la *source* de ce qui est
affiché (des champs codés en dur vers le catalogue), jamais ce qui est affiché.
Les mêmes classes `house--*`, les mêmes puces, les mêmes icônes, les mêmes
devis, dans le même ordre. Les tests d'intégration existants
(`tests/Integration/`) doivent passer ; là où une assertion vérifie un rendu qui
change de *source* mais pas de *valeur*, elle doit rester verte sans modification.

---

## État de départ (relevé, à ne pas redécouvrir)

**Les 9 gates CSS d'enveloppe** (dans `assets/styles/scene.css`), qui sont les
seules valeurs légales de `sceneLayerFor()` après la tâche 1 :

```
roof-ins, walls-interior, walls-exterior, glazing-double, glazing-triple,
vmc-double-flow, curtains, draughtproofed, floor-heating
```

Il n'existe **pas** de gate pour les états de base (`glazing-single`,
`vmc-none`, murs `none`) : le SVG par défaut les porte. `sceneLayerFor()` doit
donc retourner `null` pour un état de base.

**Mapping travail → clé de calque** (après tâche 1) :

| Travail | `sceneLayerFor()` |
|---|---|
| `RoofInsulationWork` | `'roof-ins'` si isolé, sinon `null` |
| `WallInsulationInteriorWork` | `'walls-interior'` si murs = Interior, sinon `null` |
| `WallInsulationExteriorWork` | `'walls-exterior'` si murs = Exterior, sinon `null` |
| `GlazingWork` | `'glazing-double'` / `'glazing-triple'` / `null` (simple) |
| `VentilationDoubleFlowWork` | `'vmc-double-flow'` si double flux, sinon `null` |
| `ThermalCurtainsWork` | `'curtains'` si posés, sinon `null` |
| `DraughtProofingWork` | **`'draughtproofed'` si fait, sinon `null`** ← corrigé |
| `LowTempEmittersWork` | `'floor-heating'` si posés, sinon `null` |
| **PAC, granulés, solaire×2, batterie, chauffe-eau** | **`null` toujours** ← équipement, piloté par `Household` |
| `BoilerRepairWork` | `null` (déjà) |

**`HouseShell.html.twig`** émet aujourd'hui les classes depuis 7 props séparées
(`roofInsulated`, `wallInsulation`, `glazing`, `ventilation`, `thermalCurtains`,
`draughtProofed`, `lowTempEmitters`). Seul `_cutaway.html.twig` le consomme.

**`_cutaway.html.twig`** rend l'équipement via `{% if scene.solarState == 'full' %}`,
`{% if scene.heatingState == 'heat-pump' %}`, `{% if scene.waterHeaterThermo %}`,
`{% if scene.garageState == 'installed' %}`, `{% if scene.solarState == 'kit' %}`
— **ces blocs ne changent pas dans ce plan.**

---

## Structure des fichiers

**Modifiés :**

| Fichier | Tâche | Changement |
|---|---|---|
| `src/Domain/Finance/RenovationDefinition.php` | 1 | docblock `sceneLayerFor()` : contrat « calque d'enveloppe uniquement » |
| `src/Domain/Finance/Work/DraughtProofingWork.php` | 1 | `sceneLayerFor()` : `null` → `'draughtproofed'` |
| `src/Domain/Finance/Work/{HeatPump,PelletBoiler,SolarPanels,SolarKit,HomeBattery,WaterHeaterThermo}Work.php` | 1 | `sceneLayerFor()` → `null` |
| `tests/Unit/Domain/Finance/Work/*Test.php` (les 7 ci-dessus) | 1 | assertions `sceneLayerFor` mises à jour |
| `tests/Unit/Domain/Finance/RenovationCatalogTest.php` | 1 | test filet resserré (toute clé non-nulle ∈ 9 gates) |
| `src/Application/HouseSceneView.php` | 2, 3 | `+envelopeLayers`, `−` les 7 champs d'enveloppe |
| `src/Application/GameViewFactory.php` | 2, 3 | construit `envelopeLayers`, retire le mapping des 7 champs |
| `templates/components/scene/HouseShell.html.twig` | 3 | prop `layers = []`, boucle |
| `templates/game/scene/_cutaway.html.twig` | 3 | passe `:layers="scene.envelopeLayers"` |
| `src/Application/GameView.php` | 4 | `−` champs « done », `+doneChipsBySlot` |
| `src/Application/GameViewFactory.php` | 4 | construit `doneChipsBySlot` |
| `templates/game/panel/_slot.html.twig` | 4 | tiroirs pilotés par le catalogue |
| `src/Application/ActionView.php` | 5 | `+iconAsset` |
| `src/Application/GameViewFactory.php` | 5 | mappe `iconAsset` |
| `templates/components/QuoteCard.html.twig` | 5 | `{{ include(action.iconAsset) }}` |

---

## Tâche 1 : `sceneLayerFor()` devient un accesseur cohérent (enveloppe seule)

Aucune vue ne consomme encore `sceneLayerFor()` — cette tâche est **purement
domaine** et ne change **rien à l'écran**. Elle corrige le `null` périmé du
calfeutrage, met les 6 travaux d'équipement à `null`, et resserre le contrat.
Résultat : toute valeur non-nulle est l'un des 9 gates CSS.

**Fichiers :**
- Modifier : `src/Domain/Finance/RenovationDefinition.php` (docblock)
- Modifier : `src/Domain/Finance/Work/DraughtProofingWork.php`
- Modifier : `src/Domain/Finance/Work/HeatPumpWork.php`, `PelletBoilerWork.php`,
  `SolarPanelsWork.php`, `SolarKitWork.php`, `HomeBatteryWork.php`,
  `WaterHeaterThermoWork.php`
- Test : les 7 `*WorkTest.php` correspondants + `RenovationCatalogTest.php`

**Interfaces :**
- Produit : `sceneLayerFor(Household): ?string` dont toute valeur non-nulle
  appartient à `{roof-ins, walls-interior, walls-exterior, glazing-double,
  glazing-triple, vmc-double-flow, curtains, draughtproofed, floor-heating}`.
  Consommé par la tâche 2.

- [ ] **Étape 1 : corriger le test du calfeutrage (RED)**

Dans `tests/Unit/Domain/Finance/Work/DraughtProofingWorkTest.php`, le test
existant verrouille `sceneLayerFor` à `null`. Le remplacer :

```php
    /**
     * The draught-proofing red band was added to the scene in tranche 7
     * (fenêtre-cohérence), after this work first shipped with no visual — so
     * it DOES activate the 'draughtproofed' layer once done. CSS hides the
     * band once the frames are replaced; the layer key is emitted regardless.
     */
    public function testActivatesTheDraughtproofedLayerOnceDone(): void
    {
        $work = new DraughtProofingWork();
        $bare = self::household();
        $done = $bare->withEnvelope($bare->envelope->withDraughtProofed(true));

        self::assertNull($work->sceneLayerFor($bare));
        self::assertSame('draughtproofed', $work->sceneLayerFor($done));
    }
```

(Adapter `self::household()` au helper déjà présent dans ce fichier de test.)

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `vendor/bin/phpunit tests/Unit/Domain/Finance/Work/DraughtProofingWorkTest.php`
Attendu : ÉCHEC — `sceneLayerFor` retourne `null` là où `'draughtproofed'` est attendu.

- [ ] **Étape 3 : corriger `DraughtProofingWork::sceneLayerFor()`**

```php
    public function sceneLayerFor(Household $household): ?string
    {
        // The red draught band, revealed once done (tranche 7 fenêtre-
        // cohérence added it; CSS hides it again once the frames are
        // replaced, .house--draughtproofed.house--glazing-* in scene.css).
        return $household->envelope->draughtProofed ? 'draughtproofed' : null;
    }
```

- [ ] **Étape 4 : mettre les 6 travaux d'équipement à `null` (RED puis vert, par travail)**

Pour chacun de `HeatPumpWork`, `PelletBoilerWork`, `SolarPanelsWork`,
`SolarKitWork`, `HomeBatteryWork`, `WaterHeaterThermoWork` : d'abord modifier son
test `sceneLayerFor` pour attendre `null` dans tous les états, puis mettre la
méthode à `null`. Exemple pour `HeatPumpWork` — remplacer l'assertion
`sceneLayerFor` du test existant par :

```php
    /**
     * Equipment has no envelope CSS layer: its visual is a whole scene
     * component selected by HouseSceneView from the household's equipment
     * state (heatingState), not by a house--* gate. So sceneLayerFor is null.
     */
    public function testHasNoEnvelopeLayer(): void
    {
        $installed = self::barePassoire()->withHeatingSystem(HeatingSystem::HeatPump);

        self::assertNull(new HeatPumpWork()->sceneLayerFor(self::barePassoire()));
        self::assertNull(new HeatPumpWork()->sceneLayerFor($installed));
    }
```

Puis dans `HeatPumpWork` :

```php
    public function sceneLayerFor(Household $household): ?string
    {
        // Equipment: drawn as a <twig:scene:*> component selected from the
        // household's equipment state, not via an envelope house--* gate.
        return null;
    }
```

Répéter à l'identique pour les cinq autres (adapter le wither/état construit dans
chaque test : `PelletBoiler` → `withHeatingSystem(PelletBoiler)`, `SolarPanels` →
`withSolarKwc(3.0)`, `SolarKit` → `withSolarKwc(0.9)`, `HomeBattery` →
`withBatteryKwh(...)`, `WaterHeaterThermo` → `withWaterHeater(Thermodynamic)`).

- [ ] **Étape 5 : resserrer le docblock du contrat**

Dans `src/Domain/Finance/RenovationDefinition.php`, remplacer le docblock de
`sceneLayerFor()` :

```php
    /**
     * The envelope CSS layer this work reveals in the cutaway — one of the
     * house--* gates in scene.css: roof-ins, walls-interior|exterior,
     * glazing-double|triple, vmc-double-flow, curtains, draughtproofed,
     * floor-heating — or null.
     *
     * Null covers three cases: a base state with no gate (single glazing,
     * bare walls), equipment (heat pump, solar, battery… — drawn as a whole
     * scene component selected from the household's equipment state, NOT via
     * a gate), and repairs. game-design §17: a key, never geometry.
     */
    public function sceneLayerFor(Household $household): ?string;
```

- [ ] **Étape 6 : resserrer le filet du catalogue**

Dans `tests/Unit/Domain/Finance/RenovationCatalogTest.php`, le test filet
(introduit au plan 1) doit maintenant vérifier que **toute** valeur non-nulle est
un gate réel, sur tous les états atteignables. Le remplacer par :

```php
    /**
     * Every non-null sceneLayerFor() value, across every household state a
     * work can produce, is a real house--* gate in scene.css. This is the
     * net that replaces the lost PHPStan exhaustiveness: a typo'd or invented
     * layer key would fail here, before it silently broke the scene.
     */
    public function testEverySceneLayerIsARealCssGate(): void
    {
        // The nine envelope gates declared in assets/styles/scene.css.
        $gates = [
            'roof-ins', 'walls-interior', 'walls-exterior',
            'glazing-double', 'glazing-triple', 'vmc-double-flow',
            'curtains', 'draughtproofed', 'floor-heating',
        ];

        foreach (self::everyHouseholdState() as $household) {
            foreach ((new RenovationCatalog())->all() as $work) {
                $layer = $work->sceneLayerFor($household);
                if (null !== $layer) {
                    self::assertContains($layer, $gates, sprintf('%s → %s', $work->slug(), $layer));
                }
            }
        }
    }
```

Ajouter le fournisseur d'états `everyHouseholdState()` : une poignée de foyers
couvrant chaque palier (nu ; combles ; murs ITI ; murs ITE ; double vitrage ;
triple ; VMC ; rideaux ; calfeutrage ; plancher ; PAC ; granulés ; kit ;
complet ; batterie ; chauffe-eau thermo). Réutiliser les witters de `Household`.

- [ ] **Étape 7 : gate qualité**

Lancer : `make qa`
Attendu : tout vert. **Rien à l'écran ne change** (aucune vue ne lit encore
`sceneLayerFor`). Les tests d'intégration passent sans modification.

- [ ] **Étape 8 : commit**

```bash
git add src/Domain/Finance/ tests/Unit/Domain/Finance/
git commit -m "refactor(finance): sceneLayerFor is envelope-only, coherent again

The method returned two incompatible families of keys — CSS envelope
gates AND equipment component selectors — which the final review of the
domain plan flagged as unimplementable behind one flat loop. It now
returns only the nine house--* envelope gates, or null. Equipment's
visual is selected from the household's equipment state, not from a work.

Fixes a stale null: draught-proofing gained a visual in tranche 7 after
this work first shipped with none. The catalogue net now proves every
non-null layer is a real CSS gate.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Tâche 2 : `HouseSceneView::$envelopeLayers`, construit depuis le catalogue

Ajoute la liste de calques à côté des 7 champs existants (les deux coexistent ;
la vue lit encore les anciens). Testable via `GameViewFactoryTest`, **rien à
l'écran ne change**.

**Fichiers :**
- Modifier : `src/Application/HouseSceneView.php` (ajout `envelopeLayers`)
- Modifier : `src/Application/GameViewFactory.php` (construction + injection catalogue)
- Test : `tests/Unit/Application/GameViewFactoryTest.php`

**Interfaces :**
- Consomme : `RenovationCatalog::all()`, `RenovationDefinition::sceneLayerFor()` (tâche 1).
- Produit : `HouseSceneView::$envelopeLayers` (`list<string>`), consommé par la tâche 3.

- [ ] **Étape 1 : test (RED)**

Ajouter à `tests/Unit/Application/GameViewFactoryTest.php` un test qui construit
une vue pour un foyer combles + double vitrage et vérifie les calques :

```php
    public function testSceneEnvelopeLayersComeFromTheCatalogue(): void
    {
        $household = self::passoire()->withEnvelope(
            self::original()->withRoofInsulated(true)->withGlazing(Glazing::Double),
        );

        $scene = new GameViewFactory()
            ->build(self::config(), GameState::start($household, Money::fromEuros(8000.0)))
            ->scene;

        self::assertContains('roof-ins', $scene->envelopeLayers);
        self::assertContains('glazing-double', $scene->envelopeLayers);
        self::assertNotContains('walls-interior', $scene->envelopeLayers, 'walls untouched → no layer');
    }
```

(Helpers réels du fichier : `config()`, `passoire()` — le foyer de départ —,
`original()` — son enveloppe nue. Construction : `new GameViewFactory()->build(
self::config(), GameState::start($household, Money::fromEuros(8000.0)))`.)

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `vendor/bin/phpunit tests/Unit/Application/GameViewFactoryTest.php`
Attendu : ÉCHEC — `$scene->envelopeLayers` n'existe pas.

- [ ] **Étape 3 : ajouter le champ à `HouseSceneView`**

Dans `src/Application/HouseSceneView.php`, ajouter au constructeur (à la fin, avec
les autres, après les 7 champs d'enveloppe existants qu'on retirera en tâche 3) :

```php
        /**
         * The active envelope CSS layers for the cutaway (game-design §17):
         * the house--* gates HouseShell emits, sourced from the catalogue's
         * sceneLayerFor(). Equipment visuals are NOT here — they are selected
         * from the equipment states above (heatingState, solarState…).
         *
         * @var list<string>
         */
        public array $envelopeLayers,
```

- [ ] **Étape 4 : construire la liste dans `GameViewFactory`**

Injecter le catalogue au constructeur de `GameViewFactory` (à côté des autres
dépendances) :

```php
        private RenovationCatalog $catalog = new RenovationCatalog(),
```

Dans `houseScene()`, calculer les calques et les passer à `HouseSceneView` :

```php
        $envelopeLayers = [];
        foreach ($this->catalog->all() as $work) {
            $layer = $work->sceneLayerFor($household);
            if (null !== $layer) {
                $envelopeLayers[] = $layer;
            }
        }
```

puis, dans le `new HouseSceneView(...)`, ajouter `envelopeLayers: $envelopeLayers`.

- [ ] **Étape 5 : lancer les tests**

Lancer : `vendor/bin/phpunit tests/Unit/Application/`
Attendu : PASS. Les 7 champs d'enveloppe coexistent avec `envelopeLayers`.

- [ ] **Étape 6 : gate qualité + commit**

Lancer : `make qa` (tout vert, rien à l'écran ne change) puis :

```bash
git add src/Application/ tests/Unit/Application/
git commit -m "feat(app): HouseSceneView carries catalogue-built envelopeLayers

The cutaway's envelope gates are now derived by iterating the catalogue
and collecting each work's sceneLayerFor(), alongside the existing
per-surface fields (both live until the template flips next). Nothing
rendered changes yet.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Tâche 3 : `HouseShell` boucle sur les calques ; retrait des champs morts

Bascule la vue sur `envelopeLayers` et supprime les 7 champs d'enveloppe devenus
inutiles. **Tâche visuelle** : le rendu doit rester byte-identique (mêmes classes
`house--*` émises, seule la source change).

**Fichiers :**
- Modifier : `templates/components/scene/HouseShell.html.twig`
- Modifier : `templates/game/scene/_cutaway.html.twig`
- Modifier : `src/Application/HouseSceneView.php` (retrait des 7 champs)
- Modifier : `src/Application/GameViewFactory.php` (retrait de leur mapping)
- Modifier : les tests qui référencent ces 7 champs

**Interfaces :**
- Consomme : `HouseSceneView::$envelopeLayers` (tâche 2).

- [ ] **Étape 1 : réécrire `HouseShell.html.twig`**

Remplacer les 7 props et la ligne de classes par une boucle :

```twig
{# The cutaway house shell. Envelope layers (insulation, glazing, VMC,
   curtains, draught band, underfloor emitters) arrive as a flat list of
   house--* gate keys, sourced from the catalogue (HouseSceneView::
   $envelopeLayers) — adding an envelope visual is a new SVG group + a CSS
   gate + the work's sceneLayerFor(), no prop here. Equipment is drawn by the
   renderer, not here. Room tint, chimney smoke and frost stay scene-wide. #}
{% props layers = [] %}
<g class="house{% for layer in layers %} house--{{ layer }}{% endfor %}"{{ attributes }}>{{ include('game/scene/assets/house-cutaway.svg') }}</g>
```

- [ ] **Étape 2 : brancher `_cutaway.html.twig`**

Remplacer le bloc `<twig:scene:HouseShell … 7 props … />` par :

```twig
        <twig:scene:HouseShell :layers="scene.envelopeLayers" />
```

- [ ] **Étape 3 : vérifier l'identité de rendu (RED/observé)**

D'abord un contrôle mécanique : les classes émises doivent être les mêmes
qu'avant. Écrire une vérification jetable (ou un test d'intégration) qui rend la
scène pour un foyer combles + ITE + triple + VMC + rideaux + calfeutrage +
plancher et confirme la présence de chaque `house--*` attendu.

Puis un contrôle visuel headless (le dépôt a déjà servi de ce patron en T7) :
rendre la page, capturer, comparer à un état de référence. Le rendu doit être
**indistinguable**.

Lancer : `make twig && vendor/bin/phpunit tests/Integration/`
Attendu : PASS.

- [ ] **Étape 4 : retirer les 7 champs morts de `HouseSceneView`**

Supprimer du constructeur : `roofInsulated`, `wallInsulation`, `glazing`,
`ventilation`, `thermalCurtains`, `draughtProofed`, `lowTempEmitters`. **Garder**
`insulationLabel`, `roofLabel`, `heatingState`, `heatingLabel`, `solarState`,
`garageState`, `garageLabel`, `waterHeaterThermo`, `comfortState` et le reste
(équipement + ambiance).

- [ ] **Étape 5 : retirer leur mapping de `GameViewFactory::houseScene()`**

Supprimer les lignes `roofInsulated: …`, `wallInsulation: match(...)`,
`glazing: match(...)`, `ventilation: …`, `thermalCurtains: …`,
`draughtProofed: …`, `lowTempEmitters: …` du `new HouseSceneView(...)`. Le
`match` de `wallInsulation`/`glazing` disparaît avec.

- [ ] **Étape 6 : nettoyer les tests qui référençaient ces champs**

Tout test unitaire asseyant `scene->roofInsulated` etc. bascule sur
`scene->envelopeLayers` (ex. `assertContains('roof-ins', $scene->envelopeLayers)`).

- [ ] **Étape 7 : gate qualité + commit**

Lancer : `make qa` (tout vert, rendu inchangé) puis :

```bash
git add src/Application/ templates/ tests/
git commit -m "refactor(scene): the envelope is drawn from the catalogue's layers

HouseShell loops over HouseSceneView::\$envelopeLayers and emits the
house--* gates, replacing seven per-surface props and the view's seven
envelope fields. Adding an envelope visual no longer touches any PHP or
this component — only an SVG group and a CSS gate. Equipment stays
renderer-driven. Byte-identical render.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Tâche 4 : tiroirs pilotés par le catalogue (palier 5)

`_slot.html.twig` code en dur la liste des travaux par slot (`worksOfSlot`) et
recopie un bloc de puces « fait » dans chacune des 5 branches. Le catalogue les
fournit désormais. Les champs « done » de `GameView` deviennent
`doneChipsBySlot`.

**Fichiers :**
- Modifier : `src/Application/GameView.php` (retrait champs « done », `+doneChipsBySlot`)
- Modifier : `src/Application/GameViewFactory.php` (construction)
- Modifier : `templates/game/panel/_slot.html.twig`
- Test : `tests/Unit/Application/GameViewFactoryTest.php`, `tests/Integration/`

**Interfaces :**
- Consomme : `RenovationCatalog::forSlot(SceneSlot)`,
  `RenovationDefinition::doneLabelFor()`, `::slug()` (plan 1).
- Produit : `GameView::$doneChipsBySlot` (`array<string, list<string>>`, clé =
  valeur du `SceneSlot`) ; `GameView::$worksBySlot` (`array<string, list<string>>`,
  clé = slot, valeur = slugs ordonnés) pour l'ordre d'affichage des devis.

- [ ] **Étape 1 : test (RED)**

Ajouter à `GameViewFactoryTest` un test vérifiant que les puces d'un slot
viennent du catalogue :

```php
    public function testDoneChipsComeFromTheCatalogue(): void
    {
        $household = self::passoire()->withEnvelope(self::original()->withRoofInsulated(true));

        $view = new GameViewFactory()->build(self::config(), GameState::start($household, Money::fromEuros(8000.0)));

        self::assertContains('Combles isolés', $view->doneChipsBySlot['walls']);
    }
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `vendor/bin/phpunit tests/Unit/Application/GameViewFactoryTest.php`
Attendu : ÉCHEC — `doneChipsBySlot` n'existe pas.

- [ ] **Étape 3 : ajouter les deux champs à `GameView`**

```php
        /**
         * Per-slot "done" chips, keyed by SceneSlot value ('walls', 'heating'…),
         * each a list of French state phrases from the catalogue's
         * doneLabelFor(). Replaces the scattered per-surface "done" flags.
         *
         * @var array<string, list<string>>
         */
        public array $doneChipsBySlot = [],
        /**
         * Ordered work slugs per slot ('walls' => ['roof_insulation', …]),
         * the drawer's quote display order — from the catalogue, replacing the
         * template's hardcoded worksOfSlot.
         *
         * @var array<string, list<string>>
         */
        public array $worksBySlot = [],
```

Retirer les champs « done » devenus morts une fois `_slot` basculé (étape 6) :
`roofInsulated`, `wallInsulationLabel`, `glazingLabel`, `hasDraughtProofing`,
`hasThermalCurtains`, `hasLowTempEmitters`, `hasHeatRecoveryVentilation`,
`waterHeaterLabel`. **Vérifier chaque champ par grep avant retrait** : certains
servent aussi de contexte-décision (ex. `heatPumpScopLabel`, `solarKindLabel`,
`glazingMaxed`, `boilerBroken`) et **restent**. Ne retirer que ceux dont le seul
consommateur était un bloc done-chip de `_slot`.

- [ ] **Étape 4 : construire les deux tableaux dans `GameViewFactory`**

```php
        $doneChipsBySlot = [];
        $worksBySlot = [];
        foreach (SceneSlot::cases() as $slot) {
            $chips = [];
            $slugs = [];
            foreach ($this->catalog->forSlot($slot) as $work) {
                $slugs[] = $work->slug();
                $chip = $work->doneLabelFor($household);
                if (null !== $chip) {
                    $chips[] = $chip;
                }
            }
            $doneChipsBySlot[$slot->value] = $chips;
            $worksBySlot[$slot->value] = $slugs;
        }
```

et passer `doneChipsBySlot: $doneChipsBySlot, worksBySlot: $worksBySlot` au
`new GameView(...)`.

- [ ] **Étape 5 : lancer les tests unitaires**

Lancer : `vendor/bin/phpunit tests/Unit/Application/`
Attendu : PASS.

- [ ] **Étape 6 : basculer `_slot.html.twig`**

Remplacer le tableau `worksOfSlot` codé en dur par `game.worksBySlot`, et les
5 blocs done-chip recopiés par une seule boucle sur `game.doneChipsBySlot[selected]` :

```twig
{% set works = game.worksBySlot[selected] ?? [] %}
```
```twig
{% set doneChips = game.doneChipsBySlot[selected] ?? [] %}
{% if doneChips is not empty %}
    <div class="done-strip">{% for chip in doneChips %}<span class="done-chip">✔ {{ chip }}</span>{% endfor %}</div>
{% endif %}
```

Conserver les blocs de **contexte-décision** propres à chaque slot (DPE actuel,
générateur + SCOP, installation solaire, batterie + décharge du soir, confort…)
— ce ne sont pas des puces « fait », ils restent. Conserver aussi la boucle de
devis en bas, désormais alimentée par `game.worksBySlot[selected]`.

- [ ] **Étape 7 : vérifier l'identité de rendu**

Un test d'intégration doit confirmer que les mêmes puces et les mêmes devis, dans
le même ordre, apparaissent qu'avant. Lancer :
`make twig && vendor/bin/phpunit tests/Integration/`
Attendu : PASS, sans modification d'assertion sur une valeur affichée.

- [ ] **Étape 8 : gate qualité + commit**

Lancer : `make qa` puis :

```bash
git add src/Application/ templates/game/panel/_slot.html.twig tests/
git commit -m "refactor(ui): the drawers are driven by the catalogue

worksOfSlot and the five copied done-chip blocks in _slot.html.twig are
gone: quote order and done chips now come from the catalogue via
worksBySlot and doneChipsBySlot. Adding a work no longer touches this
template — omitting a worksOfSlot entry could silently hide a quote card,
which was one of the two silent-failure sites the review named.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Tâche 5 : icônes de devis pilotées par le catalogue (palier 6)

`QuoteCard` choisit l'icône par une chaîne de `elseif` sur `action.work`. Le
catalogue fournit le chemin via `iconAsset()`. C'était la seconde source
d'échec silencieux (oublier l'`elseif` → devis sans icône).

**Fichiers :**
- Modifier : `src/Application/ActionView.php` (`+iconAsset`)
- Modifier : `src/Application/GameViewFactory.php` (mappe `iconAsset`)
- Modifier : `templates/components/QuoteCard.html.twig`
- Test : `tests/Unit/Application/GameViewFactoryTest.php`

**Interfaces :**
- Consomme : `RenovationDefinition::iconAsset()` (plan 1). Tous les chemins
  désignent des fichiers réels depuis la tâche 0 du plan 1.

- [ ] **Étape 1 : test (RED)**

```php
    public function testEachActionCarriesItsCatalogueIcon(): void
    {
        $view = new GameViewFactory()->build(self::config(), GameState::start(self::passoire(), Money::fromEuros(8000.0)));

        self::assertSame('game/scene/assets/heat-pump.svg', $view->actions['heat_pump']->iconAsset);
    }
```

- [ ] **Étape 2 : lancer, vérifier l'échec**

Lancer : `vendor/bin/phpunit tests/Unit/Application/GameViewFactoryTest.php`
Attendu : ÉCHEC — `iconAsset` absent d'`ActionView`.

- [ ] **Étape 3 : ajouter le champ à `ActionView`**

```php
        /** Template path of the drawer icon (the scene asset). @see RenovationDefinition::iconAsset() */
        public string $iconAsset = '',
```

- [ ] **Étape 4 : mapper dans `GameViewFactory::actionsFor()`**

Dans la construction de chaque `ActionView`, ajouter `iconAsset: $work->iconAsset()`.

- [ ] **Étape 5 : lancer les tests**

Lancer : `vendor/bin/phpunit tests/Unit/Application/`
Attendu : PASS.

- [ ] **Étape 6 : basculer `QuoteCard.html.twig`**

Remplacer toute la chaîne de `{% if action.work == … %}…{% elseif … %}` du bloc
`.quote-icon` par :

```twig
        <div class="quote-icon">{{ include(action.iconAsset) }}</div>
```

- [ ] **Étape 7 : vérifier le rendu**

Lancer : `make twig && vendor/bin/phpunit tests/Integration/`
Attendu : PASS — chaque devis affiche la même icône qu'avant (l'asset de scène,
déjà extrait au plan 1).

- [ ] **Étape 8 : gate qualité + commit**

Lancer : `make qa` puis :

```bash
git add src/Application/ templates/components/QuoteCard.html.twig tests/
git commit -m "refactor(ui): QuoteCard icon comes from the catalogue

The ten-branch elseif chain in QuoteCard is replaced by a single include
of action.iconAsset. Adding a work no longer touches this template — a
forgotten elseif used to mean a silent iconless card. The view layer is
now closed to modification for works.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Vérification finale

- [ ] `make qa` vert.
- [ ] `grep -rn "worksOfSlot" templates/` ne renvoie rien.
- [ ] `grep -rn "action.work ==" templates/components/QuoteCard.html.twig` ne renvoie rien.
- [ ] `HouseShell.html.twig` n'a plus qu'une prop (`layers`).
- [ ] Aucune assertion de `tests/Integration/` n'a changé de **valeur** attendue.
- [ ] `php bin/console app:simulate:demo --days 14 --from 2025-01-01` tourne sans erreur.
- [ ] Contrôle visuel : ouvrir le jeu, poser un travail d'enveloppe (isolation),
      un d'équipement (PAC), un du garage (batterie) ; vérifier que la scène, les
      puces et les icônes sont identiques à `main`.
- [ ] **Fait maison à valider — la scène est-elle fermée pour l'enveloppe ?**
      Ajouter un faux calque d'enveloppe (SVG group + gate CSS + un
      `sceneLayerFor` de test) doit apparaître **sans toucher un fichier PHP de
      vue**. Retirer le faux après vérification.

## Suite

Après ce plan, la couche vue est fermée pour les travaux, sauf le visuel
**d'équipement** dans `_cutaway` (décision hybride assumée). La couture
Work/Equipment reste au backlog, avec son déclencheur : le premier cycle de vie
sur un 2ᵉ équipement (usure/entretien), ou un équipement pré-placé sans travail.
