# Onboarding en deux modales immersives — design

**Date** : 2026-07-22
**Statut** : design validé, prêt pour le plan d'implémentation.
**Périmètre** : dernière brique MVP Phase 0-1 — le *cadrage de l'objectif* à
l'arrivée dans la partie. Aucune mécanique de jeu nouvelle : on retravaille
l'écran d'accueil existant.

## 1. Contexte et problème

Le jeu possède déjà une modale d'accueil (`ScenarioIntroEvent` +
`templates/game/scenario_event/_intro.html.twig`, généralisée dans le commit
`fd5ebc1`). La note « le joueur atterrit sans contexte ni but » de `CLAUDE.md`
est donc **périmée** : un écran existe. Le vrai défaut est **le contenu de cet
écran**, sur trois plans :

1. **Ton / immersion (défaut principal retenu)** : la modale unique fait trop de
   métiers d'un coup — récit + définition des 3 axes + manuel d'UI complet (4
   zones, 4 coins, temps réel, pause, imprévus, repère rénovation). Le joueur
   survole et ne retient rien ; elle « dit sans montrer » (décrit toit/chaudière
   /coins pendant qu'elle les masque). Registre trop scolaire.
2. **Erreur factuelle DPE** : la modale annonce « classée DPE **F** », or le DPE
   de départ calculé est **G** (figé par les tests :
   `GameViewFactoryTest` asserte `dpeLetter === 'G'` et
   `dpeStartLetter === 'G'`). Inacceptable dans un jeu *sur* le DPE.
3. **Axe manquant** : la modale liste **3 axes** (Finances / Patrimoine /
   Confort) mais le HUD en a **4** — elle **omet ⚡ Énergie & climat** (CO₂
   émis), pourtant l'axe le plus emblématique d'un jeu sur l'énergie, et présent
   au HUD comme au bilan de fin.

## 2. Décision de design

**Deux modales enchaînées** (approche B), séparant les deux métiers que l'écran
actuel confond :

- **Modale 1 — « Bienvenue chez vous »** : récit pur, immersif, illustré. La
  *situation*, aucun bouton de mécanique.
- **Modale 2 — « Vos leviers »** : les 4 axes + le mode d'emploi allégé.

Rejeté pour maintenant : l'approche C (coaching contextuel *sur la scène*,
« montrer plutôt que dire ») — excellente mais c'est un mécanisme de guidage
nouveau, hors du périmètre « améliorer la modale ». À garder pour plus tard.

## 3. Mécanisme — aucune plomberie nouvelle

On réutilise l'enchaînement `ExplainedEvent` déjà en place :
`Scenario::explainedEvents()` renvoie une **liste ordonnée** ; la factory de vue
mappe chaque événement `hasOccurred()` en `ScenarioEventView` ; le composant
`GameDashboard` affiche **le premier non-acquitté**
(`getPendingScenarioEvent()`), et en fermer un réaffiche immédiatement le
suivant. Le `data-poll` est coupé tant qu'une modale est en attente.

`PrimoAccedantScenario::explainedEvents()` passe de 2 à 3 entrées, dans l'ordre :

```
[ ScenarioIntroEvent (id: intro),
  ScenarioBriefingEvent (id: briefing),   // nouveau
  BoilerBreakdownEvent ]
```

Séquence vécue : **intro → (clic) → briefing → (clic) → jeu**. Les deux nouvelles
modales ont `hasOccurred() => true` (rien à *fire*, pertinentes dès le jour 0,
comme l'intro actuelle).

### Horloge temps réel

Seule la **dernière** modale de la chaîne (`briefing`) redémarre l'horloge
(`restartsClockOnAcknowledge = true`) : le temps passé à lire les *deux* écrans
n'est jamais décompté en jours de jeu, puisque l'ancre de progression saute au
moment où le joueur clique « Commencer ». `ScenarioIntroEvent` repasse donc de
`true` à **`false`** (pendant les modales le `data-poll` est déjà coupé, donc
rien n'avance entre intro et briefing — seul le dernier acquittement doit
réancrer l'horloge).

## 4. Modale 1 — « Bienvenue chez vous » (immersive, large)

- **Taille élargie** : nouvelle variante de carte `.intro-card--wide`
  (CVA-manuel, convention projet — pas de `html_cva`), pour laisser respirer
  l'illustration. Les modales d'intro gardent leur markup dédié
  (`.intro-overlay` / `.intro-card`), distinct du `<twig:Modal>` partagé.
- **Illustration** : composant anonyme `<twig:scene:IntroHero
  image="images/scenario/intro.jpg" />`.
  - L'image `assets/images/scenario/intro.jpg` (1024×768, servie par
    AssetMapper via `asset('images/scenario/intro.jpg')`) est le visuel
    principal.
  - **Point de bascule conservé** : la prop `image` rend un `<img>` quand elle
    est fournie ; une future image se dépose dans `assets/images/scenario/` et
    se référence en une ligne. Convention : nom de fichier = id de l'événement
    (`intro.jpg` ↔ `_intro.html.twig` ↔ event id `intro`).
  - **Fallback léger** quand `image` est absente : un cadre neutre thémable
    (dégradé + `alt`), **pas** un hero SVG composé depuis les assets de scène
    (YAGNI maintenant qu'une vraie image existe).
- **Copy située, incarnée, zéro mécanique** : vous venez d'emménager, l'hiver
  approche ; une maison ancienne **chauffée au fioul, classée DPE G**, une
  épargne modeste. On installe les enjeux *ressentis* (les factures qui tombent,
  le froid qu'on sent aux murs, la valeur du bien) sans parler de boutons.
- **Bouton « Continuer → »** : acquitte `intro` (donc enchaîne sur `briefing`),
  ne redémarre pas l'horloge.

## 5. Modale 2 — « Vos leviers » (axes + gameplay)

- **Les 4 axes exacts**, une ligne chacun (icônes alignées sur le HUD) :
  - 💶 **Finances** — épargne, factures d'énergie, aides et prêt.
  - 🛋️ **Confort** — la température *ressentie* chez vous.
  - ⚡ **Énergie & climat** — la conso et le **CO₂ réellement émis** *(l'axe qui
    manquait)*.
  - 🏠 **Patrimoine** — la classe DPE et la valeur du bien.
- **Cadrage** : « Pas de partie perdue — à vous d'équilibrer, à votre main. »
  (Ton factuel, multi-critères, pas de score — cohérent §1 du game-design.)
- **Mode d'emploi allégé** (rapatrié ici, hors de l'immersion) : zones
  cliquables (toit / chaudière / garage / séjour) ; les 4 coins = les 4 axes ;
  le temps avance seul, ⏸ pour réfléchir ; « des imprévus peuvent survenir » ;
  repère *on isole avant de changer le chauffage*.
- **Bouton « Commencer → »** : acquitte `briefing`, **redémarre l'horloge**,
  entre dans le jeu.

## 6. Corrections factuelles embarquées

- **DPE F → G** dans la copy de la modale 1.
- **4ᵉ axe ⚡ Énergie & climat** ajouté dans la modale 2 (aligné HUD + bilan de
  fin).

## 7. Composants / fichiers

| Fichier | Action |
|---|---|
| `src/Domain/Scenario/ScenarioBriefingEvent.php` | **nouveau** — `id() => 'briefing'`, `hasOccurred() => true`, `restartsClockOnAcknowledge() => true`. |
| `src/Domain/Scenario/ScenarioIntroEvent.php` | `restartsClockOnAcknowledge()` : `true → false`. |
| `src/Domain/Scenario/PrimoAccedantScenario.php` | `explainedEvents()` insère `ScenarioBriefingEvent` entre intro et panne. |
| `templates/game/scenario_event/_intro.html.twig` | réécriture immersive : carte large + `<twig:scene:IntroHero>` + copy récit (DPE G), bouton « Continuer → ». |
| `templates/game/scenario_event/_briefing.html.twig` | **nouveau** — 4 axes + mode d'emploi allégé, bouton « Commencer → ». |
| `templates/components/scene/IntroHero.html.twig` | **nouveau** — prop `image` (rend `<img>`) + fallback cadre neutre. |
| `assets/styles/components/panels.css` | variante `.intro-card--wide` + styles région hero / axes de la modale 2. |
| `assets/images/scenario/intro.jpg` | déjà en place (déplacée depuis `assets/images/intro.jpg`). |

Le domaine (`src/Domain/Scenario`) reste PHP pur, zéro dépendance framework
(règle §3). `ScenarioBriefingEvent` est le miroir strict de `ScenarioIntroEvent`.

## 8. Tests (même commit que le code, règle §5)

- **`tests/Unit/Domain/Scenario/ScenarioBriefingEventTest.php`** (nouveau) : `id`
  = `briefing`, `hasOccurred()` vrai quel que soit l'état, `restartsClock` vrai.
- **`tests/Unit/Domain/Scenario/ScenarioIntroEventTest.php`** : `restartsClock`
  attendu passe à **faux**.
- **`tests/Unit/Domain/Scenario/PrimoAccedantScenarioTest.php`** :
  `explainedEvents()` compte **3** entrées dans l'ordre `intro`, `briefing`,
  panne.
- **`tests/Integration/GameDashboardTest.php`** : acquitter `intro` fait
  apparaître `briefing` (pas encore le jeu) ; acquitter `briefing` entre dans le
  jeu et réancre l'horloge ; les deux modales bloquent le `data-poll`.

## 9. Hygiène (dans le même lot)

Suppression du fichier non suivi `tests/Integration/ZZPanelWellFormedProbe.php`
— sonde de debug cassée (préfixe `ZZ`, corps vide/malformé), non enregistrée
dans l'histoire git.

## 10. Hors périmètre (explicitement)

- Coaching contextuel sur la scène (approche C) — plus tard.
- Objectif persistant dans le HUD (option « but pas persistant ») — non retenu.
- Guidage/tutoriel des premiers pas (option « 1ers pas ») — non retenu.
- Toute mécanique de jeu nouvelle. On ne touche qu'à la présentation d'accueil
  et à la liste des `explainedEvents` du scénario.

## 11. Suite

Mettre à jour `CLAUDE.md` (retirer « onboarding » de la liste « reste côté
MVP », la boucle MVP devient complète *cadrage compris*) une fois implémenté.
