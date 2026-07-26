# Panneau de décision — refonte du moment de choix (design)

**Date** : 2026-07-26
**Statut** : design validé sur maquettes (piste A « en L », v3). Prêt pour le plan
d'implémentation en **3 étapes**. Les seuils du radar (étape 3) sont un sous-volet
à cadrer le moment venu.
**Nature** : volet **post-MVP / V1.x**, orienté **pédagogie & gameplay**. Ne
touche pas au modèle de simulation (validé réaliste) — c'est une refonte de
l'**interface de décision** + du **contenu pédagogique** + un **radar multi-axes**.
**Maquettes** : artifact `decision-panel-mockups.html` (piste A v3).

## 1. Problème (ressenti joueur)

Le jeu récompense l'**optimisation sans compréhension** : on prend le travaux qui
améliore le mieux les chiffres, sans savoir ce qu'on fait ni pourquoi. Symptômes :

- tiroir latéral **étroit**, travaux empilés, il faut scroller ;
- coût gros, variations avant/après en petit vert **indistinct** → dur de comparer ;
- **aucune pédagogie** : qu'est-ce qu'un calfeutrage, une PAC, pourquoi les combles ;
- on voit mal **ce qui est déjà en place** ;
- pas de vraie logique de choix : si on a le temps et l'argent, on fait **tout**,
  parfois pour 1 % qui n'a pas de sens.

Le fond n'est pas cosmétique : **le moment de décision doit devenir l'endroit où
l'on apprend** (jeu *pédagogique*, §1). Corollaire de méthode : le **radar seul
aggraverait** le problème (il accélère le min-max) — la **pièce porteuse est
l'explication**, le radar vient en appui.

## 2. Principe directeur

Cliquer une zone ouvre un **panneau central** (assume de masquer la scène) qui
répond, pour chaque travaux, à quatre questions dans cet ordre :

1. **Ce que c'est / comment ça marche** — explication factuelle (mécanisme),
   jamais prescriptive (§1 : pédagogie par les systèmes, pas « fais ça »).
2. **Ce que ça change** — une **seule** comparaison : le radar avant→après + les
   chiffres qui vont avec.
3. **Combien / quel financement / quel endettement / quel délai** — consolidé.
4. **Est-ce pertinent maintenant** — ordre, prérequis, ROI.

Quand on valide, **on sait pourquoi**.

## 3. Structure du panneau — disposition « en L » (validée maquettes)

Modale à **hauteur fixe** ; les zones qui débordent **scrollent en interne** (la
modale ne change jamais de taille).

```
┌─────────────────────────────────────────────┐
│  EN-TÊTE : illustration de zone (pleine       │
│  largeur) + titre/état sur un fond lisible    │
├───────┬──────────────────────┬────────────────┤
│       │  CE QUE C'EST (pédago)│  CE QUE ÇA      │
│ CHOIX │  court + ROI +        │  CHANGE (radar  │
│ (rail │  « Voir plus »        │  + effets)      │
│ pleine├──────────────────────┴────────────────┤
│ haut.)│  CTA (barre) : prix · prime/reste ·    │
│       │  endettement→ · délai · financements   │
└───────┴────────────────────────────────────────┘
```

### En-tête
- **Illustration de la zone en pleine largeur** (façon onboarding scénario), le
  **titre + l'état** posés dessus sur un **fond/voile** pour rester lisibles.
- État de zone : ex. « Chauffage actuel : fioul · ~3 150 L/an ».

### Rail « Choix » (pleine hauteur, scrollable)
- **Travaux possibles** : icône · nom · **prix** (+ badge « chantier en cours »
  pour un travaux en vol, grisé/désactivé — réutilise `crewBusy`/`inProgress`).
- Section basse **« Déjà en place »** : les équipements installés (fioul, ballon,
  et les travaux **faits**), **cliquables** → coût/explication, **sans** la partie
  comparative (radar). Remplace les « chips installés » de l'ancien en-tête.

### Zone « Ce que c'est » (pédago, scrollable)
- **1-2 phrases** sur ce qu'est l'équipement (« une PAC puise la chaleur de l'air
  extérieur… ~3× plus sobre »).
- Bloc **« Quand la faire · rentabilité »** : ordre/prérequis + **ROI** (amortissement
  = coût net / économie annuelle) + le piège éventuel (⚠ isoler d'abord).
- Bouton **« Voir plus »** (progressive disclosure) → déplie : **mécanisme
  détaillé + schéma**, **sources** (ADEME…), **alternatives**, **prérequis/pièges**.

### Zone « Ce que ça change » (comparaison — LA seule, scrollable)
- **Radar 4 axes** avant→après (cf. §4).
- **Effets chiffrés** dessous (Conso/CO₂, facture, confort, classe DPE) — les
  mêmes valeurs, en clair. Plus de doublon avec un autre tableau.

### CTA (barre du bas, pleine largeur)
- **Gauche** : prix **« à avancer »** en gros · ligne prime/reste · puis
  **endettement avant→après** et **délai** en puces (sous le prix).
- **Droite** : les **2 financements** comme boutons, chacun disant sa conséquence
  (« Comptant — épargne X » / « Éco-PTZ — 54 €/mois · 20 ans »). Le mot « posé »
  est banni (ambigu) → **« Délai ~5 sem. »**.

### Ce que chaque zone lit (mapping, étape 1 — tout existe déjà)
| Zone | Source `GameView`/`ActionView` |
|---|---|
| Rail prix / état | `ActionView.costLabel`, `inProgress`, `crewBusy`, `progressLabel` |
| Déjà en place | `doneChipsBySlot` / `worksBySlot` (état installé) |
| Pédago (transitoire) | `adviceMessage`/`adviceLevel` (en attendant l'explainer, étape 2) |
| Effets | `effectLabels` |
| CTA prix/prime/reste | `costLabel`, `subsidyLabel`, `netCostLabel` |
| CTA endettement → | `game.debtRatioLabel` + `ActionView.loanDebtRatioAfterLabel` |
| CTA délai | `ActionView.delayLabel` |
| CTA financements | `cashAllowed`, `loanAllowed`, `loanMonthlyLabel` |

## 4. Le radar multi-axes (cadrage à préciser à l'étape 3)

**Ce n'est pas une note** (§1) : 4 axes **gardés séparés**, **aucun total/agrégat**.
Chaque axe se normalise **indépendamment** en « distance au niveau recommandé »
(0-1), avec un **repère** sourcé sur la branche. Références (choix joueur) :

| Axe | Référence sourcée | Signal (déjà calculé ?) |
|---|---|---|
| **Finances** | taux d'effort énergétique **> 8 %** (ONPE) + taux d'endettement **> 35 %** (HCSF) — *pas* le solde du compte | ✅ les deux |
| **Conso / CO₂** | émissions vs **cible** officielle (SNBC / ~2 t CO₂·pers, ou cible/m²) | ✅ CO₂ émis ; cible à poser |
| **Patrimoine** | classe **DPE** + **GES** | ✅ `DpeCertifier` |
| **Confort** | **écart à la T° recommandée** (19 °C hiver, ADEME ; été = f(T° ext.), plus tard) | ✅ ressenti ; recommandé à poser |

- Le radar est un **outil du panneau** (avant/après au clic), pas la vedette.
- **Aperçu d'action** : le foyer **résultant** d'un travaux est déjà estimé (pour
  `effectLabels`) → on dessine sa forme en surimpression (fantôme).
- Version **persistante** (« en continu ») possible ensuite — pas prioritaire.
- Garde-fou : les axes **s'arbitrent** (argent ↔ confort ↔ CO₂) → le radar
  *informe* le choix, il ne prescrit pas un optimum unique.

## 5. Contenu pédagogique (explainers, §13)

Chaque `RenovationDefinition` gagne un contenu structuré :
- **`shortWhat`** : 1-2 phrases, factuel (mécanisme).
- **ROI** : calculable (`netCost` / économie annuelle estimée) — pas du contenu figé.
- **`details`** (Voir plus) : mécanisme long, **sources**, alternatives, prérequis.

Ton **factuel** (§1) : expliquer le mécanisme, laisser conclure — jamais « tu
devrais ». Sourcé (ADEME…), montrer les sources sert la pédagogie ET la traçabilité.

## 6. Plan d'implémentation — 3 étapes démontrables

### Étape 1 — La coquille (structure, zéro nouvelle donnée)
Refondre le panneau en L (en-tête illustration, rail + « déjà en place », pédago
avec l'`advice` actuel, colonne effets **sans radar** encore, CTA en barre
consolidant l'existant, hauteur fixe + scroll). **Réutilise le `GameView`
actuel.** Testable : les mêmes actions marchent, la nouvelle mise en page tient.
→ Livre le panneau lisible tout de suite, **aucun risque métier**.

### Étape 2 — Le contenu pédagogique
`RenovationDefinition` : `shortWhat` + ROI (calcul) + `details`/sources. **Incrémental**
(gros travaux d'abord : PAC, combles, ITE), surtout du **contenu sourcé**. Le
bouton « Voir plus » se câble. → Le panneau devient pédagogique.

### Étape 3 — Le radar
D'abord **cadrer les 4 axes** (§4 : normalisation + repères sourcés — le sujet mis
en pause). Puis : service domaine `AxisScores` (4 valeurs 0-1 + repères), `RadarView`,
rendu SVG, **aperçu d'action** (foyer résultant). Éventuellement persistant ensuite.
→ La comparaison multi-axes + l'aperçu, là où vit la nouveauté.

**Ordre justifié** : ① valeur UX immédiate sans toucher au métier ; ② par lots ;
③ isole le seul morceau qui demande une spec dédiée (seuils), donc ne bloque rien.

## 7. Garde-fous (§1, §13)

- **Aucun agrégat / note globale** — le radar garde 4 axes séparés.
- **Explication factuelle**, jamais prescriptive ; ton bilan, pas moralisateur.
- **Sources** pour les explainers et les repères d'axes (traçabilité §13).
- **On ne touche pas au modèle de simulation** (conso/DPE validés réalistes) —
  c'est de l'UI + du contenu + une lecture.

## 8. Hors périmètre / différé

- Confort **été** (le jeu ne gère pas l'été) — la formule T°-recommandée = f(ext.)
  arrivera avec.
- Radar **persistant** hors panneau (le « en continu ») — après l'aperçu d'action.
- Ajout de **vraies alternatives** dans l'arbre (le tree est mince en choix
  exclusifs) — piste séparée, notée au backlog.
- Vraies **illustrations** de zone (assets à produire) — placeholders d'abord.

## 9. Suite

Spec validée → plan d'implémentation (`writing-plans`) de l'**étape 1**, en TDD.
À l'entrée de l'étape 3, rouvrir un mini-cadrage des seuils du radar (§4). Mettre
à jour `docs/backlog.md` (piste « radar multi-axes + aperçu d'action » → en cours).
