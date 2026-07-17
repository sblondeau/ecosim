# Tranche 7 — Visuels de scène par travail — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development for the TESTABLE tasks. The rendering tasks are VISUAL — their acceptance is « la scène rend correctement à l'écran » (capture via l'app), pas seulement les tests. Le contrôleur vérifie visuellement entre chaque tâche de rendu.

**Goal:** Rendre chaque travail **visible sur la scène** : passer du `insulationTier` grossier (0/1/2) à un rendu **par surface** (combles / murs ITI-intérieur vs ITE-façade / vitrage / VMC), et donner un visuel aux nouveaux équipements (chaudière granulés + silo, kit solaire, rideaux thermiques).

**Architecture:** On respecte la règle §17 : `HouseSceneView` reste **sémantique** (états, aucune géométrie/couleur). Le factory mappe `Household → HouseSceneView` ; le template `_cutaway` + les composants de scène + `scene.css` + de nouveaux **SVG inline** rendent chaque état. Additif côté rendu ; côté DTO, `insulationTier` est **remplacé** par des champs par surface (rupture contenue : seuls `_cutaway`/`HouseShell`/factory + tests le consomment).

**Tech Stack:** Symfony UX Twig (composants anonymes de scène), SVG inline, CSS (scene.css), PHP (HouseSceneView DTO + factory).

## Global Constraints

- `HouseSceneView` = **sémantique pur** (§17) : des états (`'exterior'`, `'pellet'`), jamais de coordonnées/couleurs/formes. La géométrie vit dans les templates/assets.
- Composants de scène = **Twig anonymes** (`templates/components/scene/*`), pas de macro. Variante = classe locale / prop (CVA-manuel).
- CSS : variables `:root` / classes ; le graphisme de scène vit dans `assets/styles/scene.css`.
- `declare(strict_types=1)` côté PHP ; identifiants anglais, libellés joueur français.
- **Acceptation des tâches de RENDU = visuelle** : `make twig` vert + la scène rend sans erreur + capture d'écran vérifiée par le contrôleur (la logique testable — factory/DTO — garde ses tests unitaires).
- `make cs`/`make twig`/`make test` verts avant commit. Commits `type(scope): subject` + `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Branche `feat/scene-visuals`.
- **Exceptions (pas de visuel de scène, décidé)** : **calfeutrage** (joints invisibles) et **émetteurs basse température** (sous la dalle, hors coupe). Chauffe-eau thermo : visuel *optionnel* (petit ballon dans le local technique) — le faire seulement s'il tient proprement, sinon exception.

## Modèle sémantique cible (`HouseSceneView`)

Remplacer `insulationTier: int` (0/1/2) par :
- `roofInsulated: bool`
- `wallInsulation: string` — `'none' | 'interior' | 'exterior'`
- `glazing: string` — `'single' | 'double' | 'triple'`
- `ventilation: string` — `'none' | 'double-flow'`
- `thermalCurtains: bool`

Étendre :
- `heatingState` — ajouter `'pellet'` (→ `'fioul' | 'fioul-broken' | 'heat-pump' | 'pellet'`).
- `roofState` → `solarState: string` — `'empty' | 'kit' | 'full'` (distinguer kit et complète).

`insulationLabel`/`heatingLabel`/etc. conservés.

---

## Task 1: Modèle sémantique par surface + factory (TESTABLE)

**Files:**
- Modify: `src/Application/HouseSceneView.php` (remplace `insulationTier` par les 5 champs enveloppe ; ajoute pellet/kit/curtains)
- Modify: `src/Application/GameViewFactory.php` (`houseScene()` : mappe le foyer → nouveaux champs ; retire `insulationTier()` helper devenu inutile ou le garde privé si réutilisé)
- Modify: `templates/game/scene/_cutaway.html.twig` + `templates/components/scene/HouseShell.html.twig` (consomment les nouveaux champs ; **rendu inchangé pour l'instant** — HouseShell dérive une classe équivalente pour ne rien casser visuellement)
- Test: `tests/Unit/Application/GameViewFactoryTest.php`

**Interfaces (produces):** `HouseSceneView` avec `roofInsulated`, `wallInsulation`, `glazing`, `ventilation`, `thermalCurtains` (string/bool sémantiques), `heatingState` incluant `'pellet'`, `solarState` (`empty|kit|full`).

- [ ] **Step 1: Failing factory test** — pour un foyer donné, `houseScene` expose les bons états :
```php
    public function testSceneReflectsEnvelopeAndEquipmentPerSurface(): void
    {
        $view = /* GameView d'un foyer : combles isolés, murs ITE, double vitrage, VMC, PAC, kit solaire, rideaux — réutiliser le harnais existant du test */;
        $s = $view->scene;
        self::assertTrue($s->roofInsulated);
        self::assertSame('exterior', $s->wallInsulation);
        self::assertSame('double', $s->glazing);
        self::assertSame('double-flow', $s->ventilation);
        self::assertSame('pellet', $s->heatingState); // si chaudière granulés
        self::assertSame('kit', $s->solarState);
        self::assertTrue($s->thermalCurtains);
    }
```
(Adapter au harnais `GameViewFactoryTest` existant ; couvrir aussi un foyer nu → `false`/`'none'`/`'single'`/`'empty'`.)
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Rewrite `HouseSceneView`** — retirer `insulationTier`, ajouter les 5 champs enveloppe + `thermalCurtains` ; renommer `roofState`→`solarState` (`empty|kit|full`) ; `heatingState` documenté avec `pellet`.
- [ ] **Step 4: Map in `houseScene()`** :
```php
roofInsulated: $household->envelope->roofInsulated,
wallInsulation: match ($household->envelope->walls) {
    WallInsulation::None => 'none',
    WallInsulation::Interior => 'interior',
    WallInsulation::Exterior => 'exterior',
},
glazing: match ($household->envelope->glazing) {
    Glazing::Single => 'single', Glazing::Double => 'double', Glazing::Triple => 'triple',
},
ventilation: Ventilation::DoubleFlow === $household->envelope->ventilation ? 'double-flow' : 'none',
thermalCurtains: $household->envelope->thermalCurtains,
heatingState: match (true) {
    $household->boilerBroken => 'fioul-broken',
    HeatingSystem::HeatPump === $household->heatingSystem => 'heat-pump',
    HeatingSystem::PelletBoiler === $household->heatingSystem => 'pellet',
    default => 'fioul',
},
solarState: match (true) {
    $household->solarKwc <= 0.0 => 'empty',
    $household->solarKwc < $this->energy->defaultSolarPeakPowerKwc()->value => 'kit',
    default => 'full',
},
```
- [ ] **Step 5: Update `_cutaway` + `HouseShell` to consume the new fields WITHOUT changing the visual** — `HouseShell` reçoit désormais les états par surface ; pour ne rien casser, dériver une classe d'isolation équivalente à l'ancien tier (ex. `insulation="{{ scene.wallInsulation != 'none' or scene.roofInsulated ? (… tier …) }}"` ou passer les champs bruts et garder les anciennes classes). Roof/heating/garage : remplacer `roofState == 'installed'` par `solarState != 'empty'`, ajouter la branche `heatingState == 'pellet'` (rendue comme fioul pour l'instant — le vrai visuel arrive Task 3).
- [ ] **Step 6: Run → PASS + `make twig` + full suite + `make cs-fix`.**
- [ ] **Step 7: VISUAL CHECK (contrôleur)** — lancer l'app, vérifier que la scène rend **exactement comme avant** (aucune régression : c'est une bascule de modèle, pas encore de nouveau visuel).
- [ ] **Step 8: Commit** — `refactor(scene): per-surface semantic scene model (arbre travaux T7)`

---

## Task 2: Rendu enveloppe par surface (VISUEL)

**Files:** `templates/game/scene/assets/house-cutaway.svg` (couche isolation combles ; couche ITE façade extérieure ; panes de vitrage), `templates/components/scene/HouseShell.html.twig` (classes par surface), `assets/styles/scene.css` (règles par surface).

**Visuels :**
- **Combles** (`roofInsulated`) : une couche d'isolant sous le plafond/rampant + la neige de toit qui fond (aujourd'hui `.roof-snow-N` liée au tier → la lier à `roofInsulated`).
- **Murs ITI** (`wallInsulation=interior`) : les couches `.ins-wall` **intérieures** existantes (vert, côté séjour).
- **Murs ITE** (`wallInsulation=exterior`) : une couche d'isolant + enduit **à l'extérieur** du mur (x<26 gauche / x>494 droite) — visible depuis dehors, texture « enduit » distincte de l'ITI. **C'est le contraste pédagogique clé** (ITE change l'extérieur, ITI non).
- **Vitrage** (`glazing`) : double = un trait de menuiserie supplémentaire sur la fenêtre du fond ; triple = deux traits (rendement décroissant lisible).

- [ ] **Step 1:** Ajouter dans `house-cutaway.svg` les nouveaux calques, masqués par défaut (classes `.roof-ins`, `.wall-ext-ins`, `.glazing-double`/`.glazing-triple`).
- [ ] **Step 2:** `HouseShell` pose les classes selon les props (`.house--roof-ins`, `.house--walls-iti`/`.house--walls-ite`, `.house--glazing-double`/`-triple`).
- [ ] **Step 3:** `scene.css` : révèle chaque calque selon la classe ; **relier la neige de toit à `roofInsulated`** (plus de neige si combles nus, moins si isolés).
- [ ] **Step 4:** `make twig` + suite verts.
- [ ] **Step 5: VISUAL CHECK** — combles/ITI/ITE/vitrage se distinguent, ITE change bien l'extérieur, rien ne déborde.
- [ ] **Step 6: Commit** — `feat(scene): per-surface envelope visuals (roof, ITI/ITE, glazing) (arbre travaux T7)`

---

## Task 3: Variantes d'équipement — granulés + kit solaire (VISUEL)

**Files:** `templates/components/scene/Boiler.html.twig` (+ variante `pellet` + asset silo), nouvel asset `boiler-pellet.svg` (ou variante du fioul), `templates/components/scene/SolarPanels.html.twig` (variante kit), `_cutaway` (branche `heatingState == 'pellet'` ; `solarState == 'kit'`), `scene.css`.

**Visuels :**
- **Chaudière granulés** (`heatingState=pellet`) : corps de chaudière + **silo/trémie** (bois/granulés), teinte distincte du fioul. Placée dans le local technique comme le fioul.
- **Kit solaire** (`solarState=kit`) : 1-2 petits panneaux (≠ la grille complète), posés sur le versant.

- [ ] **Step 1:** Asset `boiler-pellet` (silo + corps) ; `Boiler` gère `state=pellet`.
- [ ] **Step 2:** `SolarPanels` : prop `variant=kit|full` → petit vs complet ; `_cutaway` passe la variante depuis `solarState`.
- [ ] **Step 3:** `make twig` + suite verts.
- [ ] **Step 4: VISUAL CHECK** — pellet ≠ fioul, kit ≠ installation complète.
- [ ] **Step 5: Commit** — `feat(scene): pellet boiler (with silo) and plug-and-play solar kit visuals (arbre travaux T7)`

---

## Task 4: VMC + rideaux thermiques (VISUEL)

**Files:** nouvel asset/inline VMC (caisson + gaine), overlay rideaux sur la fenêtre, `_cutaway`/`HouseShell`, `scene.css`.

**Visuels :**
- **VMC double flux** (`ventilation=double-flow`) : un petit caisson près du plafond/combles + une bouche — signale la ventilation.
- **Rideaux thermiques** (`thermalCurtains`) : des rideaux de part et d'autre de la fenêtre du fond (repliés), révélés par la classe.
- **(optionnel) Chauffe-eau thermo** : petit ballon dans le local technique s'il tient sans encombrer — sinon exception assumée.

- [ ] **Step 1:** Caisson VMC (masqué par défaut, révélé si `ventilation=double-flow`).
- [ ] **Step 2:** Rideaux (overlay fenêtre, révélé si `thermalCurtains`).
- [ ] **Step 3:** (optionnel) ballon chauffe-eau si placement propre.
- [ ] **Step 4:** `make twig` + suite verts.
- [ ] **Step 5: VISUAL CHECK** — VMC et rideaux lisibles, pas de chevauchement avec le chauffage/occupant.
- [ ] **Step 6: Commit** — `feat(scene): double-flow ventilation and thermal curtains visuals (arbre travaux T7)`

---

## Task 5: Vérification + revue

- [ ] **Step 1: Full qa gate** — `make cs && make twig && make test` vert.
- [ ] **Step 2: VISUAL PASS complet** — parcourir un foyer de « tout nu » à « tout rénové » (via la démo ou l'app) et vérifier que **chaque travail** produit un changement visible (sauf les 2 exceptions), sans chevauchement/débordement ; captures d'écran des états clés.
- [ ] **Step 3: Grep** — plus de `insulationTier` résiduel dans `src/`/`templates/`.
- [ ] **Step 4: Self-review vs l'objectif** — chaque travail a son visuel (sauf calfeutrage / émetteurs BT, exceptions assumées) ; `HouseSceneView` reste sémantique (§17).
- [ ] **Step 5: Backlog** — marquer T7 faite ; noter le raffinement d'assets possible (le joueur pourra fournir des SVG plus soignés).
- [ ] **Step 6: Final whole-branch review** + router la branche (`finishing-a-development-branch`).

---

## Self-Review du plan (couverture)

- **Modèle sémantique par surface (§17)** → Task 1 (testable). ✔
- **Enveloppe : combles / ITI-intérieur / ITE-façade / vitrage** → Task 2. ✔
- **Équipement : granulés+silo / kit solaire** → Task 3. ✔
- **VMC + rideaux (+ chauffe-eau optionnel)** → Task 4. ✔
- **Exceptions assumées** : calfeutrage, émetteurs BT (pas de visuel). ✔
- **Acceptation visuelle** (capture) sur les tâches de rendu, tests sur la logique. ✔

Hors périmètre : assets « soignés » fournis par le joueur (raffinement ultérieur) ; refonte iso/3D (§17, lointain).
