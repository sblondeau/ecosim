# Tranche 7 (suite) — Cohérence fenêtre : calfeutrage visuel + huisserie par palier — Plan d'implémentation

**Contexte :** la Tranche 7 (`2026-07-17-tranche7-visuels-scene-plan.md`) a assumé
« calfeutrage = pas de visuel (des joints ne se voient pas) ». En revoyant la
scène, deux incohérences sont apparues : (1) on peut en fait représenter un
joint/bandeau sans sur-jouer l'effet, et (2) le cadre de la fenêtre ne change
jamais visuellement quand on passe en double/triple vitrage, alors que dans la
réalité les huisseries sont remplacées avec le vitrage. Ce plan corrige les
deux, **sans toucher au domaine** (aucun changement de `EnvelopeState`,
`BuildingCalibration` ou `RenovationQuoter` — décision actée : calfeutrage et
vitrage restent mécaniquement indépendants, le calfeutrage couvrant aussi les
portes).

**Goal :**
1. Le calfeutrage obtient un visuel (bandeau rouge pointillé autour de la
   fenêtre), mais **seulement tant que le vitrage est simple** — dès
   double/triple, la nouvelle huisserie est réputée déjà étanche et le
   bandeau s'éteint (règle CSS pure, pas de nouvelle condition côté PHP).
2. Le cadre de la fenêtre change de matière/teinte selon le palier de
   vitrage : bois (simple, inchangé) → PVC blanc (double) → alu anthracite
   (triple). Même géométrie SVG qu'aujourd'hui (pas de nouveau dessin), donc
   risque de régression visuelle minimal.

**Architecture :** respect de la règle §17 — `HouseSceneView` reste
sémantique (juste `draughtProofed: bool` en plus, aucune couleur/coordonnée).
Le rendu (classes CVA + règles CSS) vit dans `HouseShell.html.twig` /
`house-cutaway.svg` / `scene.css`, comme le reste de la scène.

## Contraintes globales

- `declare(strict_types=1)` ; pas de nouveau champ Domain.
- Composants de scène = Twig anonymes, classes locales (CVA-manuel), pas de
  Tailwind.
- **Acceptation des tâches de rendu = visuelle** (app lancée, capture) ; la
  tâche de fil de données (Task 1) garde un test unitaire.
- `make cs && make twig && make test` verts avant commit.

---

## Task 1 : Exposer `draughtProofed` à la scène (TESTABLE)

**Files :**
- Modify: `src/Application/HouseSceneView.php` (ajoute `public bool $draughtProofed`)
- Modify: `src/Application/GameViewFactory.php` (`houseScene()` : mappe `$household->envelope->draughtProofed`)
- Modify: `templates/game/scene/_cutaway.html.twig` (passe `:draughtProofed="scene.draughtProofed"` à `<twig:scene:HouseShell>`)
- Modify: `templates/components/scene/HouseShell.html.twig` (prop `draughtProofed = false` → classe `house--draughtproofed`)
- Test: `tests/Unit/Application/GameViewFactoryTest.php`

- [ ] **Step 1 : test qui échoue** — étendre (ou dupliquer) le test existant
  qui construit un foyer avec calfeutrage fait :
  `self::assertTrue($view->scene->draughtProofed);` et un cas foyer nu →
  `assertFalse`.
- [ ] **Step 2 : run → FAIL** (le champ n'existe pas encore).
- [ ] **Step 3 : ajouter le champ** dans `HouseSceneView` (à côté de
  `thermalCurtains`, même style de docblock).
- [ ] **Step 4 : mapper dans `houseScene()`** :
  `draughtProofed: $household->envelope->draughtProofed,`
- [ ] **Step 5 : `_cutaway.html.twig`** — ajouter
  `:draughtProofed="scene.draughtProofed"` à côté de `:thermalCurtains`.
- [ ] **Step 6 : `HouseShell.html.twig`** — ajouter le prop et
  `{{ draughtProofed ? ' house--draughtproofed' }}` à la liste de classes
  (mettre à jour le commentaire d'en-tête qui énumère les props → classes).
- [ ] **Step 7 : run → PASS** + `make cs-fix` + `make twig` + suite complète.
- [ ] **Step 8 : Commit** — `feat(scene): expose draughtProofed on HouseSceneView`

---

## Task 2 : Cadre de fenêtre par palier de vitrage (VISUEL)

**Files :** `templates/game/scene/assets/house-cutaway.svg`, `assets/styles/scene.css`.

**Détail :** dans le groupe fenêtre de `house-cutaway.svg`, les éléments de
cadre actuels sont :
- casing extérieur : `<rect x="82" y="194" ... fill="#6b5a45"/>`
- casing intérieur : `<rect x="84" y="196" ... fill="#8a7458"/>`
- meneaux (croix) : `<path d="M130 202v72 M90 238h80" stroke="#8a7458" .../>`
- liseré de bordure : `<rect x="90" y="202" ... stroke="#6b5a45" .../>`

Leur donner des classes (`window-frame-outer`, `window-frame-inner`,
`window-muntins`, `window-frame-border`) **sans changer les couleurs par
défaut** (le simple vitrage garde le bois actuel — aucune régression pour
l'état de départ du scénario).

- [ ] **Step 1 :** ajouter les 4 classes dans `house-cutaway.svg`, garder les
  `fill`/`stroke` inline actuels comme défaut (= simple vitrage).
- [ ] **Step 2 :** dans `scene.css`, ajouter (à côté des règles
  `.house--glazing-double .glazing-pane-2 { display: initial }` existantes) :
  ```css
  .house--glazing-double .window-frame-outer,
  .house--glazing-double .window-frame-border { fill: #e9e7e0; stroke: #e9e7e0; }
  .house--glazing-double .window-frame-inner,
  .house--glazing-double .window-muntins { fill: #d5d2c8; stroke: #d5d2c8; }
  .house--glazing-triple .window-frame-outer,
  .house--glazing-triple .window-frame-border { fill: #3a3d40; stroke: #3a3d40; }
  .house--glazing-triple .window-frame-inner,
  .house--glazing-triple .window-muntins { fill: #55585c; stroke: #55585c; }
  ```
  (teintes indicatives — ajuster au rendu pour rester lisible sur le fond du
  séjour ; le contraste outer/inner plus sombre/clair doit rester perceptible
  comme pour le bois actuel.)
- [ ] **Step 3 :** `make twig` + suite verts (pas de logique PHP touchée ici).
- [ ] **Step 4 : VISUAL CHECK** — parcourir simple → double → triple sur la
  démo/app : cadre bois → blanc PVC → anthracite alu, le halo de vitrage
  (panes) toujours cohérent avec la couleur de cadre, rien ne déborde du
  cadre existant.
- [ ] **Step 5 : Commit** — `feat(scene): window frame material follows glazing tier (wood/PVC/aluminium)`

---

## Task 3 : Bandeau de calfeutrage, éteint dès vitrage amélioré (VISUEL)

**Files :** `templates/game/scene/assets/house-cutaway.svg`, `assets/styles/scene.css`.

- [ ] **Step 1 :** ajouter dans `house-cutaway.svg`, dans le groupe fenêtre,
  un nouvel élément masqué par défaut :
  ```svg
  <rect class="draught-band" x="79" y="191" width="102" height="94" rx="5"
        fill="none" stroke="#e05341" stroke-width="3" stroke-dasharray="5 4"/>
  ```
  (légèrement à l'extérieur du casing 82/194/96/88, même esprit que les
  flèches d'air rouges déjà utilisées ailleurs dans le jeu pour signaler un
  courant d'air/une perte).
- [ ] **Step 2 :** dans `scene.css`, l'ajouter à la liste des calques masqués
  par défaut (à côté de `.glazing-pane`, `.curtains`) :
  ```css
  .scene .glazing-pane,
  .scene .draught-band,
  .scene .curtains, ... { display: none; }
  ```
  puis la règle de révélation, **combinée** pour respecter la règle « éteint
  dès vitrage amélioré » sans toucher au domaine :
  ```css
  .house--draughtproofed:not(.house--glazing-double):not(.house--glazing-triple) .draught-band {
      display: initial;
  }
  ```
- [ ] **Step 3 :** `make twig` + suite verts.
- [ ] **Step 4 : VISUAL CHECK** — 4 combinaisons à vérifier sur la
  démo/app : (a) calfeutrage seul + simple vitrage → bandeau visible ; (b)
  calfeutrage + double/triple vitrage → bandeau absent (cadre déjà recoloré
  par Task 2) ; (c) pas de calfeutrage, simple vitrage → rien ; (d) ni
  calfeutrage ni upgrade → état de départ inchangé.
- [ ] **Step 5 : Commit** — `feat(scene): draught-proofing shows as a red band on the window, only while single-glazed`

---

## Task 4 : Vérification + backlog

- [ ] **Step 1 : Full qa gate** — `make cs && make twig && make test` vert.
- [ ] **Step 2 : Grep** — confirmer qu'aucune autre référence au cadre
  fenêtre (ex. futur composant) n'a été oubliée.
- [ ] **Step 3 : Backlog** — dans `docs/backlog.md`, corriger la ligne T7
  « Exceptions assumées (aucun visuel) : calfeutrage… » pour noter qu'elle a
  été levée par ce plan (renvoi vers ce fichier), afin que la doc ne reste
  pas en contradiction avec le code.
- [ ] **Step 4 : Commit final** si Step 3 n'a pas été inclus dans Task 3.

---

## Self-Review du plan (couverture)

- **Calfeutrage obtient un visuel** → Task 3. ✔
- **Visuel de calfeutrage cohérent avec un changement de vitrage** (éteint
  dès double/triple) → Task 3, règle CSS combinée. ✔
- **Huisserie changée avec le vitrage** → Task 2 (recoloration du cadre,
  même géométrie). ✔
- **Aucun changement domaine/mécanique** (décision actée : calfeutrage reste
  achetable et effectif indépendamment du vitrage, couvre aussi les portes)
  → confirmé, aucune Task ne touche `EnvelopeState`/`BuildingCalibration`/`RenovationQuoter`. ✔
- **`HouseSceneView` reste sémantique** (§17) → seul ajout : `draughtProofed: bool`. ✔

Hors périmètre : redessiner entièrement la géométrie de la fenêtre par
palier (retenu : recoloration seule, cf. décision) ; toucher au visuel des
rideaux thermiques ou du calfeutrage côté portes (aucun visuel de porte dans
la scène actuellement).
