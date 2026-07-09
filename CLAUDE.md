# CLAUDE.md — EcoSim

Contexte durable pour Claude Code. À lire en début de session. La **source de
vérité conceptuelle** reste `docs/game-design.md` (game design complet) ; ce
fichier ne résume que ce qui pilote le code au quotidien.

## 1. Le projet

**EcoSim** : jeu de simulation web **pédagogique** sur la gestion de l'énergie et
la transition écologique. Stack : **Symfony UX (Twig/Live Components + data-poll),
Doctrine ORM, PostgreSQL, PHP 8.4**.

Principes de fond (cf. game-design §1) :
- **Réalisme sourcé** : aucun coefficient de gameplay sans source citable. Le
  levier de jeu est le *coût d'accès* (prix/délai/prérequis), jamais la
  magnitude truquée d'un effet réel.
- **Multi-critères, jamais un score unique** : finances / patrimoine / confort
  restent des axes séparés.
- **Pas de game over arbitraire**, ton factuel (bilans), la pédagogie vient des
  systèmes, pas de pop-ups moralisatrices.

## 2. Scope courant : **Phase 0-1 uniquement** (MVP resserré, verrouillé)

On ne construit QUE la Phase 0-1 (game-design §15). **Hors scope, ne pas
anticiper** : carte/grille hexagonale, isométrique, éolien, réseau électrique
modélisé, climat long terme, échelles ville/pays, animations/rendu graphique.

Périmètre exact : 1 foyer, 1 scénario (primo-accédant, maison ancienne, chauffage
fioul, DPE F-G). Tick = 1 jour. Météo = **nébulosité + température** seulement.
Production = **solaire (1 modèle) + batterie (1 capacité)**, achat/vente réseau à
tarif fixe. Isolation à 3 paliers, chauffage 2 choix (fioul / PAC), 1 événement
scripté (panne de chaudière). Finances : revenu fixe, 1 prime, 1 prêt taux zéro,
facture 2 lignes, 1 contrat de revente. Suivi : `thermalComfortScore` + valeur du
bien (formule DPE simple). Fin = horizon fixe → bilan factuel.

### Décisions actées (ne pas re-litiguer sans le demander)
- **Véhicule électrique : HORS SCOPE** cette phase (revient en V1.1).
- **Fin de partie : horizon fixe** (durée bornée en jours), pas open-ended.
- **Tick** : moteur découplé du déclencheur. Avancée fondée sur le temps écoulé
  réel (`SECONDS_PER_GAME_DAY`, ~30 s = 1 jour, ajustable). Politique
  `TimeProgressionPolicy` = flag → défaut **PausesWhileAway** (le temps ne tourne
  pas hors ligne ; `ContinuesWhileAway` réactivable quand les politiques
  automatiques existeront, hors scope MVP).
- **Registre de calibration** : PHP/YAML typé (valeur + fourchette + source +
  date), pas de table Doctrine `ImpactCoefficient` pour le MVP.

## 3. Architecture : **DDD pragmatique** (hexagonal + cœur fonctionnel)

Choix validé : **pas du DDD par le livre**. On prend les Value Objects et un
domaine isolé sans framework ; on évite agrégats riches/mapping/Domain
Events/repositories-de-collection (surdimensionnés pour un foyer). Le cœur de
simulation est **fonctionnel** : un tick = fonction pure `tick(GameState):
GameState`, déterministe et rejouable (seed).

**Règle de dépendance (impérative)** : `Domain` ne dépend de personne.

```
Présentation  src/Twig/Components/  (LiveComponent)   ← lit un GameView (DTO), remplaçable (canvas/3D demain)
Application   src/Application/       (GameManager, GameStateMapper, GameView)
Domain ★      src/Domain/            PHP PUR, 0 dépendance Symfony/Doctrine  ← le cœur, la calibration, le tick
Persistance   src/Entity/ + src/Repository/  (Doctrine, entités anémiques = état)
```

- `src/Domain/**` : **interdit** d'importer Symfony, Doctrine, ou tout `vendor`
  framework. Que du PHP natif (dont `DateTimeImmutable`, ok). VOs immuables
  (`final readonly`), enums natifs (partagés avec les entités, acceptable).
- Les entités Doctrine sont **anémiques** (juste l'état). La logique vit dans les
  services de domaine sans état.
- La présentation ne voit **que** `GameView` (objet de lecture plat) → c'est ce
  qui permet de swapper l'UI (Twig → canvas/three.js) sans toucher au métier.
- État présent : domaine `src/Domain/{Time,Weather,Energy,Building,Finance,Math,Simulation,Calibration}`
  (tick, météo semée à régimes persistants, production solaire/batterie/bilan,
  chauffage fioul/PAC + confort + DPE, `Household` avec panne `boilerBroken`,
  argent — `Money` en centimes, facture 2 lignes, revenu mensuel, prime par
  tranches, éco-PTZ `Loan`, `RenovationQuoter` (dont réparation chaudière,
  comptant seul), valeur du bien DPE — `SimulationEngine` avec **événement
  scripté** panne de chaudière (20 janvier, `Scenario::BOILER_BREAKDOWN_DAY`,
  ne se déclenche que si encore au fioul ; maison non chauffée = équilibre
  apports internes/déperditions), `Scenario` (départ verrouillé : nu, fioul,
  4 000 € d'épargne), registre `Coefficient`) ; `src/Application/`
  (`GameView`/`ActionView`/`EndReportView` + factory — **bilan de fin par axes,
  jamais d'agrégat** (§1) —, `RenovationHandler`, `GameStore`/`SessionGameStore`
  v7, `Game`) ; présentation `GameController` (dashboard, jour-suivant,
  **travaux**, nouvelle-partie, CSRF par attribut, flashs) + dashboard Twig
  (Finances avec revenu/dépenses/reste à vivre, Patrimoine, Confort, zones,
  bandeau panne, bilan de fin, travaux avec devis et double financement) ;
  `app:simulate:demo`. **La boucle §15 est complète.** Restent : passe UX
  (backlog « Interface / pédagogie »), LiveComponent `data-poll` (tick temps
  réel), persistance Doctrine.

Migration future possible vers du DDD plus strict (agrégats + mapping) sans tout
casser, si l'échelle ville/pays l'exige — mais **pas maintenant**.

## 4. Qualité — chaîne obligatoire

Tout passe par le `Makefile` (ou scripts Composer équivalents). **`make qa` doit
être vert avant tout commit.**

| Commande | Rôle |
|---|---|
| `make cs` / `cs-fix` | php-cs-fixer (`@Symfony` + `@PHP84Migration`, `declare(strict_types=1)`, comparaisons strictes) |
| `make stan` | PHPStan **niveau 8** (`phpstan.neon`) |
| `make twig` | `lint:twig` + twig-cs-fixer |
| `make test` | PHPUnit |
| `make qa` | tout : cs + stan + twig + test |

Règles PHPStan (issues du philosophie de l'outil, à respecter) : **corriger la
cause**, jamais suppresser. Pas de `@phpstan-ignore`, pas de baseline, pas de
`assert()`/`@var` pour forcer un type, pas de cast ni d'élargissement de type
pour faire taire une erreur.

PHPStan est installé **via Composer** (`require-dev`) avec les extensions
`phpstan-symfony` + `phpstan-doctrine` (auto-enregistrées par
`phpstan/extension-installer`). Setup standard, rien de spécial à installer.

### Contrainte d'environnement (bac à sable Claude Code web) — cf. `docs/tooling.md`
La politique d'egress bloque GitHub Releases / jsdelivr : dans une session web,
`composer install` ne peut pas récupérer le dist « dist-only » de PHPStan, donc
`make stan` n'y tourne pas (mais `cs` / `twig` / `test` oui). L'analyse statique
est alors couverte par la **CI** (egress libre). C'est une limite du bac à sable,
pas du projet — en local et en CI tout s'installe normalement.

## 5. Tests — règles

- **Domaine = tests unitaires purs** (`tests/Unit/...`), **sans DB ni kernel**,
  rapides. C'est là que vit la logique → couverture prioritaire. Miroir de
  l'arbo `src/` (`tests/Unit/Domain/Time/GameDateTest.php`).
- **Déterminisme** : le tick et la météo sont semés (seed) → tester des valeurs
  exactes, pas des approximations. Pas de `random`/horloge non injectée dans le
  domaine.
- **Intégration** (`tests/Integration/...`) : seulement pour la persistance
  Doctrine et l'application (mapper, GameManager). Prévoir SQLite en mémoire.
- Namespace de test : `App\Tests\...` → dossier `tests/`. PHPUnit 13.
- Chaque nouvelle brique de domaine arrive **avec ses tests dans le même commit**.

## 6. Calibration & traçabilité (game-design §13)

Tout coefficient chiffré (rendement PV, SCOP PAC, décote DPE, tarif rachat…) doit
être **traçable à une source** (ADEME, RTE, CRE, Notaires de France…), avec
fourchette d'incertitude quand la littérature diverge. Le registre central
(PHP/YAML typé) est la source unique ; ne jamais coder un nombre « magique »
inline sans le rattacher au registre + un commentaire de source.

## 7. Conventions de code

- `declare(strict_types=1)` partout ; classes `final` par défaut ; VOs
  `final readonly`.
- Identifiants/commentaires de code en **anglais** ; libellés destinés au joueur
  en **français** (`Season::label()` → « Été »…).
- Commits : messages clairs, en anglais, format `type(scope): subject`
  (`feat(sim):`, `chore:`…). `make qa` vert avant de committer.

## 8. Méthode de travail

- **Avancer par petites étapes démontrables** (game-design §15) : chaque étape
  produit quelque chose de visible/testable (jamais une couche technique isolée).
  Ordre suivi : tick → météo → production solaire+batterie → bâtiment/confort →
  finances → événement panne + bilan.
- **En cas d'ambiguïté ou de sous-spécification pour cette phase : demander**,
  ne pas improviser une mécanique non documentée.
- Les améliorations identifiées mais volontairement différées sont notées dans
  **`docs/backlog.md`**, chacune avec l'étape qui la déclenche. Consulter ce
  fichier en début d'étape ; ne pas les faire « en avance ».
- Interface : **minimale** au MVP (jauges finances/confort/patrimoine + 2-3 zones
  cliquables toit/chaudière/garage). Pas de carte, pas d'animations.

## 9. Branche & environnement

- Développer sur `claude/ecosim-phase-0-1-mvp-n8lo8z` (Phase 0-1).
- ⚠️ Selon la session, l'accès GitHub peut être **en lecture seule** (push git et
  API en 403). Dans ce cas : committer proprement en local et signaler le
  blocage, ne pas boucler sur des retries de push.

## 10. Aide-mémoire commandes

```bash
make install          # composer install + PHAR tools (phpstan)
make qa               # gate qualité complet (à passer avant commit)
make test             # tests uniquement
php bin/console app:simulate:demo --days 14 --from 2025-01-01   # démo du tick
```
