# Contrainte de rénovation — 3 leviers (design)

**Date** : 2026-07-26 (élargit la note « poids financier » du 2026-07-25)
**Statut** : design validé, implémentation **étagée** (on mesure entre chaque
étape et on s'arrête dès que ça mord assez).
**Nature** : volet **post-MVP / V1.x** — rendre la rénovation *contraignante*.
La phase délais (rythme) est faite, mais un **retest** montre qu'elle ne suffit
pas.

## 1. Problème (retest, chiffres à l'appui)

Retest : au **24 juin** le joueur a « presque tout acheté » (sauf PV toit),
**DPE C**, et **DPE C limite B au 24 septembre**. Deux causes mesurées :

1. **Aucune limite de chantiers simultanés.** On commande **tout le jour 1** ;
   les délais tournent **en parallèle** (seuls les générateurs étaient
   exclusifs) → ils ne *décalent* rien. C'est ça, « enchaîner les travaux ».
2. **L'argent ne mord pas.** Revenu 2 800 €/mois = **tranche intermédiaire →
   prime 40 %**. Tous les travaux aidés ≈ 50 000 € affiché → **~30 000 € net**.
   Or le plafond de solvabilité envisagé (33 600 € de PTZ) **> 30 000 €** : il ne
   se déclenche jamais. La prime **instantanée** (versée au clic) supprime en
   plus tout crunch de trésorerie.

**Décision** : on ne fausse PAS les aides (40 % est la vraie tranche, §1). On
pose **trois leviers réels**, dans cet ordre, en mesurant entre chaque.

## 2. Levier ① — un seul chantier d'artisan à la fois (le rythme)

**La notion est portée par le Work** : `RenovationDefinition::requiresProfessional():
bool` (RGE / normes → un professionnel obligatoire ; sinon geste réalisable seul).

- **Travaux « pro »** (combles, murs, menuiseries, PAC, granulés, VMC, émetteurs
  BT, ECS thermo) : **un seul en cours à la fois**, de la commande à la pose.
  Commander un 2ᵉ travaux pro tant qu'un chantier pro est en vol → **refusé**.
- **Gestes DIY** (`requiresProfessional() == false` : calfeutrage, rideaux, kit
  solaire plug-and-play) : **parallélisables sans limite** (confort / gain
  marginal, pas d'autorisation).
- **Réparation chaudière : exemptée** (urgence — toujours commandable même si un
  chantier pro tourne, sinon la panne piège le joueur ; cohérent avec son statut
  déjà spécial : comptant, hors exclusivité).
- **Subsume l'exclusivité générateur** : « un pro à la fois » interdit déjà
  PAC + granulés simultanés → on **retire `ExclusivityGroup` /
  `RenovationConflicts`** au profit de cette règle unique.

**Effet** : ~6 gros travaux séquentiels × (lead 4-8 sem + pose) ≈ 30-40 semaines
→ l'année se remplit, **l'ordre des travaux devient la décision** (isoler avant
la PAC…). Les délais retrouvent enfin leur rôle.

*Réalisme assumé* : en vrai on peut aligner plusieurs artisans ; « un à la fois »
est une **simplification de gameplay** (bande passante du foyer + du chantier),
pas un fait sourcé — desserrable à 2 si le tempo traîne.

## 3. Levier ② — l'avance de trésorerie (la prime après travaux)

Aujourd'hui la prime est déduite **au clic** (net payé tout de suite). En vrai
**MaPrimeRénov' est versée *après* travaux** (instruction ~3-6 mois).

- À la commande, le foyer paie le **prix affiché** (comptant ou PTZ), pas le net.
- La **prime est créditée ~60 jours après la pose** (cash différé). Nouveau flux :
  une liste de **primes en attente** `{montant, jour de versement}` sur
  `GameState`, versées par le `SimulationEngine` au jour dit — **même patron que
  les `ScheduledWork`**.
- **Effet** : il faut **fronter** le plein tarif pendant les travaux → vraie
  tension de trésorerie, surtout combinée au PTZ (qui finance, mais plafonné) et
  au levier ①.

*(Hors périmètre de ce levier : acomptes 30/solde, avance ANAH.)*

## 4. Levier ③ — plafond de solvabilité (le filet réaliste)

Le volet « poids financier » d'origine. Ne mord pas seul aux montants actuels,
mais reste **réaliste et pédagogique**, et mord dès que les travaux coûtent plus
cher / la prime est plus faible.

- **Crédit immobilier explicite** : sortir ~840 €/mois de `monthlyLivingExpenses`
  (2 100 → 1 260 + 840). **Reste-à-vivre et revenu crédité inchangés**.
- **Taux d'endettement** = `(immo + mensualité PTZ) / revenu` ; départ 30 %.
- **Gate = refus net à 35 %** (HCSF) : un PTZ qui percerait le mur est refusé,
  message nommant le taux. Plafond effectif ~33,6 k€ (backstop avant les 50 k€).
- **UI** : panneau Finances = ligne crédit immo + **taux d'endettement coloré**
  (vert < 33 / ambre 33-35 / rouge ≥ 35) ; bouton éco-PTZ = **avant → après**.

## 5. Calibration (sourcée, §13)

| Coefficient | Valeur | Source |
|---|---|---|
| `mortgageMonthlyPayment` | 840 €/mois | ACPR *Financement de l'habitat 2024* : taux d'effort moyen ~30,7 % → ~840 € sur 2 800 € |
| `debtRatioCeiling` | 0,35 | HCSF (contraignant depuis 2021) |
| `subsidyDisbursementDays` | 60 j | Ordre de grandeur assumé (§13) : MaPrimeRénov' versée après travaux + instruction |
| `monthlyLivingExpenses` | 1 260 € (était 2 100) | Reliquat après extraction du crédit immo |

Mur 35 % et taux ~30 % **durs** ; mensualité immo et délai de versement =
**ordres de grandeur assumés**, notés comme tels.

## 6. Architecture

### Domaine
- **`RenovationDefinition`** gagne **`requiresProfessional(): bool`** (levier ①).
- **`FinanceCalibration`** : `mortgageMonthlyPayment()`, `debtRatioCeiling()`,
  `subsidyDisbursementDays()` ; `monthlyLivingExpenses` → 1 260.
- **`GameState`** gagne une liste **`pendingSubsidies: list<PendingSubsidy>`**
  (`{amountCents, disbursementDay}`, VO `final readonly`) — levier ②.
- **`SolvencyPolicy`** (nouveau, pur) : `debtRatio(Loan)`,
  `debtRatioAfterBorrowing(Loan, Money)`, `allowsBorrowing(...)`, `ceiling()` —
  levier ③.
- **`SimulationEngine`** : pose les chantiers échus (déjà) **et** verse les
  primes échues (`pendingSubsidies` dont `disbursementDay <= jour`) ; `incomeFor`
  soustrait le crédit immo.

### Application
- **`RenovationHandler::order`** : (①) refuse un 2ᵉ travaux pro si un chantier pro
  est en vol (réparation exemptée) ; (②) débite le **prix affiché** et programme
  la **prime en attente** au lieu de déduire le net ; (③) gate de solvabilité sur
  le PTZ.
- **`GameViewFactory` / `GameView` / `ActionView`** : masque/désactive les
  travaux pro pendant qu'un chantier pro tourne ; expose primes en attente,
  taux d'endettement (+ couleur), avant→après du prêt ; `monthlyLeftover`
  soustrait le crédit immo.

### Présentation
- Scène / tiroirs : un travaux pro non commandable pendant un chantier pro (déjà
  le patron « en cours »). Panneau Finances : crédit immo, taux coloré, **primes
  à venir**. Bouton prêt : avant → après.

## 7. Tests (même commit que le code, §5)

- **① Concurrence** : `requiresProfessional()` par work ; un 2ᵉ pro refusé tant
  qu'un pro est en vol ; un geste DIY **toujours** accepté en parallèle ; la
  **réparation** acceptée même pendant un chantier pro ; PAC+granulés toujours
  exclusifs (via la nouvelle règle).
- **② Avance** : commander débite le **prix affiché** et programme la prime ;
  `SimulationEngine` verse la prime au `disbursementDay` (pas avant) ;
  (dé)sérialisation `pendingSubsidies` (bump `FORMAT_VERSION`).
- **③ Solvabilité** : taux départ 30 % ; refus net au-dessus de 35 % (message) ;
  comptant jamais gaté ; **régressions** : revenu crédité et reste-à-vivre
  inchangés après l'extraction du crédit immo.

## 8. Garde-fous (§1, §13)

- Leviers de **coût d'accès** (bande passante, trésorerie, solvabilité bancaire),
  pas de magnitude truquée ni de verrou arbitraire. On **ne touche pas** aux
  aides ni aux coûts sourcés.
- Déterminisme : tout est fonction pure de l'état + calibration ; aucun dé.

## 9. Hors périmètre (→ `docs/backlog.md`)

- Cap PTZ tiéré réel (15/25/30/50 k), marge de flexibilité HCSF.
- Acomptes 30 %/solde, avance ANAH, assurance emprunteur détaillée.
- Concurrence à 2 fronts (desserrage) si le tempo à 1 traîne — à décider en test.

## 10. Suite

Implémentation **étagée**, chaque étape démontrable et retestée : **① concurrence**
(le plus direct sur « enchaîner ») → retest → **② avance de trésorerie** → retest
→ **③ solvabilité** (+ UI taux d'endettement). Mettre à jour `docs/backlog.md`
(entrées « taux d'endettement » / « durée des travaux » → cette phase) à la fin.
