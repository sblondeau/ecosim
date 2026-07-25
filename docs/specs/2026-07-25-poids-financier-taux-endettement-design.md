# Poids financier — taux d'endettement & crédit immo explicite (design)

**Date** : 2026-07-25
**Statut** : design validé (décision gate actée : refus net à 35 %), prêt pour le
plan d'implémentation.
**Nature** : deuxième volet **post-MVP / V1.x**, la **racine 2** du problème
« rénover sans douleur ». La phase délais (racine 1, le rythme) est faite et
mergée mais **ne suffit pas** : on enchaîne encore les travaux au PTZ, quasi
gratuit dans l'horizon scoré.

## 1. Problème

Le vrai frein réaliste au « PTZ gratuit » n'est PAS le plafond 50 k€ (jamais
atteint : horizon 1 an ≪ terme 20 ans, 0 % → ~12 mensualités payées sur 240,
~95 % du coût tombe après la fin de partie). C'est la **solvabilité** : une
banque ne prête pas au-delà d'un **taux d'endettement de 35 %** (règle HCSF
contraignante depuis 2021, assurance incluse). L'éco-PTZ **compte** dans ce taux
— le 0 % n'exempte pas, c'est la mensualité qui pèse. Un foyer déjà proche du mur
ne peut donc PAS empiler les PTZ à volonté.

## 2. Modèle

- **Crédit immobilier explicite** : on sort la mensualité de prêt immo du forfait
  `monthlyLivingExpenses` (aujourd'hui fondue dedans) et on la modélise comme une
  charge nommée. **Reste-à-vivre et revenu crédité inchangés** (on ne fait que
  ré-étiqueter : 2 100 → 1 260 « autres dépenses » + 840 immo = 2 100).
- **Taux d'endettement** = `(mensualité immo + mensualité éco-PTZ) / revenu net`.
  Au départ (aucun PTZ) : 840 / 2 800 = **30 %** — pile la moyenne ACPR.
- **Mur HCSF 35 %** = 980 €/mois. Capacité PTZ = 980 − 840 = **140 €/mois** → sur
  240 mois ≈ **33 600 € de PTZ effectif**. Le plafond de solvabilité mord **bien
  avant** les 50 k€.
- **Gate = refus net** (décision actée) : commander un éco-PTZ qui porterait le
  taux **au-dessus de 35 %** est refusé, avec un message qui nomme le taux visé.
  Pas de marge de flexibilité (simplicité, lisibilité).
- **Comptant inchangé** : déjà limité par l'épargne (7 750 € au départ, ~consommés
  par le solaire). Le gate ne porte que sur le **prêt**.
- **Cap 50 k€ plat gardé en parallèle** (simplification actée) : le gate de
  solvabilité s'ajoute *avant* lui et devient le limiteur réel.

## 3. Calibration (sourcée, §13)

| Coefficient | Valeur | Fourchette | Source |
|---|---|---|---|
| `mortgageMonthlyPayment` | 840 €/mois | 700-900 | ACPR *Le financement de l'habitat 2024* : taux d'effort moyen à l'octroi ~30,7 % ; sur 2 800 €/mois → ~840 € |
| `debtRatioCeiling` | 0,35 | — | HCSF (règle contraignante depuis 2021, assurance incluse) |
| `monthlyLivingExpenses` | 1 260 € (était 2 100) | — | Reliquat après extraction du crédit immo (2 100 − 840). Total inchangé |

`mortgageMonthlyPayment` est un **ordre de grandeur** cohérent avec le taux
d'effort moyen ACPR, pas un chiffre relevé — noté comme tel. Le mur 35 % et le
taux moyen ~30 % sont, eux, durs.

## 4. Architecture

### Domaine
- **`FinanceCalibration`** gagne **`mortgageMonthlyPayment()`** et
  **`debtRatioCeiling()`** (`Coefficient`), et **`monthlyLivingExpenses` passe à
  1 260**.
- **`SolvencyPolicy`** (nouveau service `final readonly`, dépend de
  `FinanceCalibration`) — pur, déterministe :
  - `debtRatio(Loan $loan): float` = `(mortgage + loan.monthlyPayment) / income`.
  - `debtRatioAfterBorrowing(Loan $loan, Money $amount): float` =
    `debtRatio(loan.borrow(amount))`.
  - `allowsBorrowing(Loan $loan, Money $amount): bool` =
    `debtRatioAfterBorrowing ≤ ceiling`.
  - `ceiling(): float`.
  La mensualité PTZ prospective réutilise `Loan::borrow()` (borrowedTotal / 240),
  la source de vérité existante — pas de calcul dupliqué.

### Application
- **`RenovationHandler::order`** (branche prêt) : après le check du cap 50 k€,
  ajoute le **gate de solvabilité**. Si `!allowsBorrowing(state.loan, net)` →
  refus : *« Prêt refusé : ce crédit porterait votre endettement à X % (plafond
  35 %). »* (X = taux prospectif arrondi).
- **`SimulationEngine::incomeFor`** : `income − living − mortgage` (au lieu de
  `income − living`). Montant crédité **identique** (living réduit d'autant).
- **`GameViewFactory`** :
  - `monthlyLeftover` : soustrait aussi la mensualité immo (reste-à-vivre
    identique — régression testée).
  - **`GameView`** expose : `mortgageLabel` (ligne crédit immo),
    `debtRatioLabel` (« 30 % »), `debtRatioLevel` (`ok` | `tendu` | `saturé`
    pour la couleur).
  - **`ActionView`** (par travaux éligible prêt) : `loanDebtRatioAfterLabel`
    (le taux **si** ce prêt est pris) ; **`loanAllowed`** intègre désormais la
    solvabilité (le bouton prêt se désactive si le prêt percerait le mur).

### Présentation
- **Panneau Finances** (`_finances.html.twig`) : ligne **crédit immobilier** +
  **taux d'endettement** avec **code couleur** (vert/ambre/rouge), là où vivent
  déjà revenu / dépenses / reste-à-vivre.
- **`QuoteCard`** : près du bouton **éco-PTZ**, l'**évolution du taux** si on le
  prend (*« endettement 30 % → 34 % »*) ; bouton désactivé + raison si le prêt
  percerait 35 %.

### Seuils de couleur du taux
`ok` (vert) < 33 % · `tendu` (ambre) 33–<35 % · `saturé` (rouge) ≥ 35 %. Le
départ (30 %) est vert ; la couleur monte à mesure qu'on mobilise le PTZ.

## 5. Tests (même commit que le code, §5)

- **`SolvencyPolicy`** : taux de départ = 30 % (valeurs exactes) ; taux après un
  emprunt donné ; `allowsBorrowing` vrai juste sous le mur, faux juste au-dessus
  (bornes exactes semées).
- **`RenovationHandler`** : un PTZ qui percerait 35 % est **refusé** (message
  nommant le taux) ; un PTZ sous le mur est **accordé** ; le **comptant** n'est
  jamais gaté par la solvabilité ; le cap 50 k€ reste refusé séparément.
- **`GameViewFactory`** : `debtRatioLabel`/`debtRatioLevel` corrects ;
  `loanAllowed` faux quand le prêt percerait le mur ; **régression** :
  `monthlyLeftover` identique avant/après l'extraction du crédit immo.
- **`SimulationEngine`** : **régression** — revenu crédité identique après
  l'extraction (`income − living − mortgage` == ancien `income − living`).

## 6. Garde-fous (§1, §13)

- **Levier de coût d'accès, pas verrou artificiel** : la banque ne prête pas à un
  foyer sur-endetté — règle réelle, sourcée (HCSF), pas un plafond arbitraire de
  gameplay.
- **Déterminisme** : tout est fonction pure de l'état (`Loan`) + calibration ;
  aucun dé.
- **Sourçage honnête** : mur 35 % et taux moyen ~30 % durs ; mensualité immo
  assumée comme ordre de grandeur cohérent (§13).

## 7. Hors périmètre (→ `docs/backlog.md`)

- **Cap PTZ tiéré réel** (15/25/30/50 k selon le nombre d'actions) — infobulle
  possible plus tard.
- **Marge de flexibilité HCSF** (~15 % des prêts au-delà) — écartée (refus net).
- **Amortissement du crédit immo** (mensualité constante sur l'horizon, pas de
  tableau) et **assurance emprunteur** détaillée.
- **Crédit conso rapide** pour l'urgence (une autre porte de financement) —
  piste séparée.

## 8. Suite

Spec validée → plan d'implémentation (`writing-plans`), en petites étapes
démontrables : (a) extraction du crédit immo + `SolvencyPolicy` + régressions
(cash-flow inchangé) ; (b) gate dans `RenovationHandler` + refus nommé ;
(c) UI panneau Finances (taux coloré) + bouton prêt (avant → après). Mettre à
jour `docs/backlog.md` (entrée « taux d'endettement » → cette phase).
