# Spec — Premier arbre de travaux (rénovation détaillée)

> **Statut** : design validé (brainstorming du 15 juillet 2026), **non planifié**.
> **Phase** : ouvre la première extension **post-MVP** (V1.x). Le MVP Phase 0-1
> reste verrouillé (CLAUDE.md §2) tant que cette spec n'est pas consciemment
> planifiée. Ce document ne touche à aucun code ; il fige le *quoi* et le
> *comment*, à relire avant d'écrire le plan d'implémentation.
> **Source de vérité conceptuelle** : `docs/game-design-document.md`.

## 1. Objectif & rôle premier

Le gameplay MVP est **structurellement mou** : les décisions sont peu nombreuses
(5 travaux), *one-shot* et front-loaded ; le milieu d'année est vide. Cet arbre
répond à **un** problème prioritaire, choisi explicitement :

> **Rôle premier = pédagogie de la rénovation.**

L'arbre existe d'abord pour enseigner la **vraie logique d'un parcours de
rénovation** : l'ordre ADEME (on isole du haut vers le bas *avant* de changer le
chauffage, on ventile *après* avoir étanchéifié), les vrais arbitrages (ITI vs
ITE, rendement décroissant du triple vitrage, la PAC bridée par de mauvais
émetteurs), et ce que couvrent les aides.

Les deux autres bénéfices (profondeur stratégique, rythme de décision) viennent
**en bonus**, pas comme objectif — on ne sacrifie pas la justesse pédagogique
pour eux.

Périmètre **resserré** assumé : une douzaine de nœuds sur 5 branches (dont
plusieurs à variantes : ITI/ITE, kit/installation…). Assez pour raconter le
parcours, assez petit pour rester tenable (sourçage, IHM).

## 2. Principe pédagogique : « conseil non bloquant »

**Décision actée** : quand le joueur veut faire les travaux dans un ordre
sous-optimal, le jeu **ne verrouille rien** et **ne fabrique aucun malus
artificiel**. On peut tout faire dans n'importe quel ordre, à tout moment. Un
**encart de conseil** (💡 info / ⚠ déconseillé maintenant) guide, mais ne
bride pas. Cohérent avec §1 (« la pédagogie vient des systèmes, pas de pop-ups
moralisatrices ») et « pas de game over arbitraire ».

Conséquence structurante — **les leçons se répartissent en deux natures** :

| Nature | Mécanisme | Exemples |
|---|---|---|
| **Montrée par le système** | La simulation existante produit déjà la conséquence ; l'encart ne fait que la *nommer*. Pédagogie forte, gratuite. | PAC dans une passoire → besoin de chauffage élevé → **factures qui restent hautes** (calculé depuis l'isolation). Vitrage/isolation → **confort monte** (effet paroi froide). PAC sur radiateurs fonte → **SCOP dégradé** → factures/CO₂ hauts. |
| **Conseil purement textuel** | Le modèle ne simule pas le revers ; l'encart informe sans dent. | « Ventiler *après* avoir isolé » (pas de modèle d'humidité). L'ordre optimal. Ce que couvrent les aides. |

**Note de design (non retenue au premier arbre)** : si un jour on veut donner des
dents à la leçon ventilation sans verrou, l'ajout minimal serait un *petit effet
émergent* (indicateur qualité d'air / humidité qui se dégrade en logement
sur-isolé sans VMC). Hors périmètre ici — noté pour mémoire.

## 3. Contenu de l'arbre — 5 branches, une douzaine de nœuds

Colonne vertébrale = **le parcours ADEME** : *enveloppe → ventilation → chauffage
→ production*. 🎯 = arbitrage phare (vrai choix, pas un upgrade linéaire).
⚠ = sourçage à sécuriser (§8).

### Branche A — Enveloppe
Remplace l'échelle abstraite actuelle (3 paliers `Original/Retrofitted/Reinforced`)
par les **vraies surfaces**, dans l'ordre de priorité réel.

- **A1 — Isolation des combles** : priorité #1 (~25-30 % des pertes, la chaleur
  monte). Peu cher, gros effet. La leçon « par où on commence ».
- **A2 — Isolation des murs** 🎯 **ITI vs ITE** : *l'*arbitrage phare. Même
  objectif, deux façons mutuellement exclusives :
  - **ITI** (intérieure) : moins chère, mange de la surface habitable, ponts
    thermiques résiduels, chantier qui vide les pièces.
  - **ITE** (extérieure) : plus chère, meilleure performance (pas de pont
    thermique), ravale la façade, parfois interdite (mitoyenneté, patrimoine).
- **A3 — Menuiseries** 🎯 double → triple vitrage : leçon **rendement
  décroissant** (le triple ne vaut le coût qu'en climat froid) + confort (paroi
  froide, acoustique).

### Branche B — Ventilation
- **B1 — VMC** : simple flux → double flux (récupère la chaleur de l'air
  extrait). Porte la leçon « on ventile *après* avoir étanchéifié ».

### Branche C — Chauffage
Garde l'existant, l'étoffe.
- **C1 — Générateur** : fioul (départ) → **PAC air/eau** (déjà) → **granulés/bois**
  (combustible pas cher, manuel, stockage). ⚠
- **C2 — Émetteurs basse température** 🎯 (plancher chauffant / grands radiateurs
  BT) : **modificateur de SCOP** de la PAC. Sans lui, PAC sur radiateurs fonte =
  SCOP dégradé (~2,3) ; avec lui = SCOP nominal (~4,2). Casse le mythe « PAC =
  solution magique ». ⚠

### Branche D — Production & eau chaude sanitaire
- **D1 — Solaire PV** 🎯 **kit plug-and-play** (entrée pas chère, faible
  rendement, sans installateur, sans aide) → **installation complète** (déjà).
  Enseigne « le premier pas accessible ». ⚠
- **D2 — Batterie** (déjà).
- **D3 — Chauffe-eau thermodynamique** : le levier ECS oublié (~15 % de
  l'énergie du logement), pas cher. ⚠

### Branche E — Gestes du quotidien
Petits, pas chers, surtout du **confort ressenti**.
- **E1 — Rideaux thermiques**, **E2 — Calfeutrage / joints de fenêtres**. ⚠
  (gains de quelques %, surtout ressenti / paroi froide — sourçage le plus
  faible, à assumer comme tel).

### Coupé volontairement (YAGNI / honnêteté des sources)
- **Type d'isolant** (laine vs biosourcé) : son vrai intérêt = le *déphasage /
  confort d'été*, **non modélisé avant la Phase 5 canicule**. Le mettre maintenant
  = magnitude truquée (viole §1). → **différé, couplé à la Phase 5.**
- Plancher bas / cave, géothermie, solaire thermique, réseau de chaleur,
  émetteurs comme sous-branche riche, véhicule électrique (V1.1) → extensions
  notées, hors premier arbre.

## 4. Enchaînements, synergies & conseils

L'arbre n'a **pas d'arêtes verrouillantes** (§2). Le « parcours » se lit dans
**l'ordre des cartes + les badges de conseil**, calculés depuis l'état courant du
foyer. Règles de conseil (le moteur, §5) :

- **Chauffage avant enveloppe** → sur la PAC : ⚠ « maison peu isolée → PAC
  surdimensionnée, factures qui restent hautes » *(montré par le système)*.
- **PAC sur radiateurs haute température** → ⚠ « vos radiateurs bride la PAC
  (SCOP 2,3 au lieu de 4,2) → voir émetteurs basse température » *(montré : le
  SCOP change réellement)*.
- **Ventilation avant isolation** → sur la VMC : ⚠ « conseillée *après*
  l'isolation (récupère la chaleur) » *(conseil textuel)*.
- **Triple vitrage** → 💡 « peu utile ici (climat doux) : le double suffit »
  *(montré : gain marginal faible)*.
- **Batterie avant panneaux** → déjà géré (offre masquée tant qu'aucun PV, cf.
  `RenovationQuoter::batteryQuote`) — on conserve.

Synergies positives à rendre lisibles (montrées par le système) : isolation →
rend la PAC efficace ; émetteurs BT → SCOP nominal ; PV + batterie →
autoconsommation.

## 5. Modèle de domaine (impact `src/Domain`)

> Rappel règle de dépendance : `Domain` = PHP pur, 0 framework (CLAUDE.md §3).

### 5.1 État — `Household` (nouveaux champs)
Aujourd'hui : `solarKwc`, `batteryKwh`, `insulation` (enum 3 paliers),
`heatingSystem` (fioul/PAC), `boilerBroken`, `heatingSetpointC`. On enrichit :

- **Enveloppe par surface** : remplacer le champ `insulation` monolithique par
  des sous-états de surface :
  - `roofInsulated: bool` (combles) ;
  - `wallInsulation: WallInsulation` enum `None | Interior | Exterior` (ITI/ITE) ;
  - `glazing: Glazing` enum `Single | Double | Triple`.
- **Ventilation** : `ventilation: Ventilation` enum `None | SingleFlow | DoubleFlow`.
- **Chauffage** :
  - `heatingSystem` étendu : `FuelOilBoiler | HeatPump | PelletBoiler` ;
  - `lowTempEmitters: bool` (module le SCOP de la PAC).
- **Production / ECS** :
  - `solarKwc` conservé ; ajouter la notion de **kit plug-and-play** vs
    installation complète (deux niveaux de `solarKwc`, ou un flag `solarKit`) ;
  - `waterHeater: WaterHeater` enum `ElectricTank | Thermodynamic`.
- **Gestes** : `thermalCurtains: bool`, `draughtProofing: bool`.

Household reste **VO immuable** (`final readonly`), méthodes `with*()` par champ.

### 5.2 DPE — recalcul par surfaces
Le DPE (énergie + GES) n'est plus dérivé d'un palier abstrait mais **agrégé
depuis les surfaces** : chaque poste (toiture, murs, vitrage, ventilation,
générateur) contribue à la déperdition / consommation. Répartition sourcée des
pertes (§8) : toiture ~25-30 %, murs ~20-25 %, air/ventilation ~20 %, vitrage
~10-15 %, plancher ~7-10 %. `DpeCertifier` / `HeatingNeedCalculator` consomment
ces contributions.

### 5.3 SCOP dépendant des émetteurs
`HeatingEnergyCalculator` : le SCOP de la PAC devient fonction de
`lowTempEmitters` (et, plus finement, du niveau d'isolation). Deux valeurs
sourcées (§8) : SCOP dégradé (~2,3, radiateurs HT) vs nominal (~4,2, BT).

**Le nœud « émetteurs basse température » n'affecte QUE la PAC.** Les autres
générateurs y sont insensibles — c'est physiquement juste (§1 : pas de magnitude
truquée) et pédagogiquement propre : une PAC est un *système*, pas une boîte
qu'on visse.

| Générateur | Sensibilité à l'émetteur | Pourquoi |
|---|---|---|
| **PAC air/eau** | **Déterminante (facteur continu)** | Le SCOP dépend de l'écart air extérieur ↔ température d'eau : ~35 °C (BT) → SCOP ~4–4,5 ; ~65 °C (fonte HT) → ~2–2,5. |
| **Chaudière fioul / gaz classique** | **Quasi nulle** | Le rendement du brûleur (~87–90 %) ne bouge pas avec la température d'émission. C'est le cas de la chaudière fioul de départ. |
| **Granulés / bois** | **Négligeable** | Le rendement tient au brûleur, pas à l'émission. |
| **Effet Joule (convecteurs d'appoint)** | **Sans objet** | 100 % de conversion ; le convecteur *est* l'émetteur. |

Donc, dans le domaine : le modificateur d'émetteur s'applique **uniquement**
quand `heatingSystem === HeatPump` ; pour les générateurs à combustion, le nœud
« émetteurs BT » n'a aucun effet chiffré (et l'IHM peut le signaler : « utile
surtout avec une pompe à chaleur »).

**Extension future (hors premier arbre)** : si une **chaudière à condensation**
(gaz THPE / fioul condensation) entrait dans l'arbre, l'émetteur BT deviendrait
son *déclencheur de bonus* tout-ou-rien — la condensation ne récupère la chaleur
latente des fumées que si l'eau de retour passe sous ~55 °C (émetteurs HT → elle
redevient une classique). Noté pour mémoire.

### 5.4 Catalogue de travaux — `Renovation` + `RenovationQuoter`
- L'enum `Renovation` passe de 5 à ~13 cases (ou une structure plus riche :
  nœud = identifiant + branche + éligibilité aides). `isSubsidised()` /
  `isLoanEligible()` étendus (cf. §6).
- `RenovationQuoter::quote()` gère chaque nœud : coût sourcé, prime, foyer
  résultant, `null` si non applicable (déjà fait / rien à améliorer). Le cas
  **ITI/ITE** = deux devis mutuellement exclusifs sur le même nœud « Murs ».
- **Nouveau service `RenovationAdvisor`** (domaine pur) : pour un nœud + un
  `Household`, renvoie l'**état** (`Done | Available | NotApplicable`) et la
  liste de **conseils** (`{level: info|warn, message}`) selon les règles §4.
  C'est le moteur du « conseil non bloquant ». Déterministe, testé unitairement.

### 5.5 Tests
Chaque brique arrive **avec ses tests unitaires purs** dans le même commit
(CLAUDE.md §5) : DPE par surfaces (valeurs exactes), SCOP par émetteurs,
`RenovationAdvisor` (chaque règle de conseil), quotes de chaque nœud.

## 6. Financement & aides

**Décision actée : périmètre des aides inchangé, seulement étendu aux nouveaux
nœuds.** La prime (MaPrimeRénov'-like) + l'éco-PTZ couvrent les **travaux de
performance** (enveloppe, ventilation, chauffage) ; **pas** la production (PV,
batterie) ni les gestes — exactement la règle actuelle (`Renovation::isSubsidised`).

- Subventionné : A1, A2 (ITI/ITE), A3, B1, C1 (PAC, granulés), C2, D3
  (chauffe-eau thermo — travaux de performance ECS).
- Non subventionné : D1 (PV, kit), D2 (batterie), E1/E2 (gestes).

Montants de prime par nœud → à sourcer (§8). Double financement (comptant /
éco-PTZ) conservé tel quel.

## 7. IHM

### 7.1 Principe : séparer *lire* et *agir*
Cause racine des redondances actuelles : `_slot.html.twig` fait **deux métiers**
(affiche des indicateurs **et** lance les travaux), d'où la duplication
(séjour ↔ coin Confort, toit ↔ coin Énergie).

- **Les 4 coins = lire** (axes Finances / Confort / Énergie / Patrimoine) —
  **inchangés**.
- **Les zones de scène = agir** (les travaux). Elles **perdent les indicateurs
  redondants** et ne gardent qu'un **entête de contexte-décision** : les 1-2
  chiffres que *le travail va changer*.

Entêtes par zone (contexte orienté décision, pas miroir d'axe) :
- Toit / Enveloppe → « DPE **G** · déperditions dominantes : toiture ».
- Chauffage → « SCOP actuel **2,3** · chauffage ~X €/mois ».
- Garage / Production → « autoconsommation X % · export Y kWh ».
- Séjour / Gestes → « ressenti X °C ».

### 7.2 Zones de scène = branches (diégétique)
- **Toit** → Enveloppe + Ventilation.
- **Chaudière** → Chauffage (générateur + émetteurs).
- **Garage** → Production & ECS.
- **Séjour** → Gestes du quotidien.

L'arbre n'est **pas** un graphe de nœuds-et-arêtes (un skill-tree verrouillé
contredirait « conseil non bloquant » §2). Il se lit dans l'**ordre des cartes +
les badges**.

### 7.3 Contenant : tiroir latéral
**Décision actée** : les travaux s'ouvrent dans un **tiroir latéral** (~40 % de
large, pleine hauteur), la zone cliquée restant visible → on décide *en voyant*
l'élément concerné. Remplace le petit `aside` de coin (`.float-panel at-br`),
trop exigu pour des cartes riches.

**La modale centrale (`<twig:Modal>`) reste réservée aux interruptions one-shot**
(la panne de chaudière l'utilise déjà). Langage visuel : modale = « le jeu
t'interrompt » ; tiroir = « tu es allé voir ». Ne pas mélanger.

```
┌───────────────────────────┬────────────────────────────┐
│ [Fin]      ☁ 8° couvert   │ 🔆 Toit — Enveloppe     [✕] │
│        ╱▔▔▔╲              │ DPE G · pertes : toiture++ │
│    🌳  │[PV]│              │────────────────────────────│
│        │▨🔥 │              │ ✔ Combles           fait   │
│        │👤  │              │ ● Murs        ┌ITI┐ ┌ITE┐  │
│        └────┘              │   [comptant] [éco-PTZ]     │
│ [Conf]  [garage]  [CO₂]   │ ○ Menuiseries      4 500 € │
│   ⏸×1×2×3  20janv ▸suiv   │ ─ Ventilation ─            │
│                           │ ○ VMC double flux   2 800 €│
└───────────────────────────┴────────────────────────────┘
```

### 7.4 Anatomie d'une carte de nœud
États (l'état par carte suffit — **pas de compteur de progression**, coupé) :
- **✔ fait** · **● disponible** · **○ pas encore**.
- Coût · aide (si éligible) · financement [comptant] [éco-PTZ].
- Conseils : 💡 (info) / ⚠ (déconseillé maintenant, avec la raison).
- Cas **Murs** : ITI / ITE côte à côte (choix segmenté), pas un upgrade caché.

Exemple « conseil non bloquant » en action (rien n'est grisé) :
```
● Pompe à chaleur air/eau           11 000 € · aide 4 000 €
   ⚠ déconseillée maintenant : maison peu isolée →
      PAC surdimensionnée, factures qui restent hautes
   ⚠ vos radiateurs fonte la brident (SCOP 2,3 au lieu de 4,2)
      → voir « émetteurs basse température » ci-dessous
○ Émetteurs basse température        6 500 €
   💡 fait passer la PAC de SCOP 2,3 → 4,2
```

### 7.5 Composants
- Cartes de nœud, toggles ITI/ITE, badges de conseil = **Twig Components
  anonymes** (`<twig:...>`, CVA-manuel), pas de macro (CLAUDE.md §7). `QuoteCard`
  existant à faire évoluer / décliner.
- Le tiroir = extension du `.float-panel` (nouvelle variante de position/taille),
  piloté par le LiveComponent `GameDashboard` (`selectSlot` existant). Actions
  d'achat = `#[LiveAction]` (comme aujourd'hui).

## 8. Coefficients à sourcer (§6 réalisme sourcé)

Aucun nombre inline sans source dans le registre `Coefficient` (valeur +
fourchette + source + date). À sécuriser **avant** implémentation de chaque nœud :

| Poste | À sourcer | Piste source |
|---|---|---|
| Combles | coût, part des pertes (~25-30 %) | ADEME |
| Murs ITI | coût, perte de surface | ADEME |
| Murs ITE | coût, part des pertes murs (~20-25 %) | ADEME |
| Vitrage double/triple | coût, part vitrage (~10-15 %), rendement décroissant | ADEME |
| VMC double flux | coût, rendement de récupération | ADEME |
| Granulés | coût install, prix combustible, facteur CO₂ (~30 g/kWh) | ADEME / Base Carbone |
| Émetteurs BT | coût, SCOP HT (~2,3) vs BT (~4,2) | ADEME / constructeurs |
| Solaire plug-and-play | coût kit, rendement | ADEME / produits |
| Chauffe-eau thermo | coût, COP, part ECS (~15 %) | ADEME |
| Rideaux / calfeutrage | coût, effet confort/déperdition (faible, à assumer) | ADEME (le plus fragile) |
| Primes par nœud | montants MaPrimeRénov' | ANAH |

## 9. Décisions actées (ne pas re-litiguer sans le redemander)

- **Rôle premier = pédagogie de la rénovation** (les autres bénéfices en bonus).
- **Conseil non bloquant** : aucun verrou, aucun malus artificiel ; encarts
  💡/⚠ ; leçons montrées par le système ou conseil textuel.
- **Contenu** : 5 branches, ~13 nœuds (§3). Type d'isolant **différé** (Phase 5).
- **Travaux instantanés** (pas de délai de chantier) — le « délai » = levier de
  coût d'accès des phases suivantes, hors premier arbre.
- **Aides : périmètre inchangé**, étendu aux nouveaux nœuds de performance.
- **IHM** : zones = branches ; séparation lire/agir ; **tiroir latéral** pour les
  travaux ; modale réservée aux interruptions ; entêtes de contexte-décision ;
  pas de compteur de progression.

## 10. Prochaine étape

Cette spec relue et approuvée → écrire le **plan d'implémentation**
(skill `writing-plans`), en petites étapes démontrables (CLAUDE.md §8) : chaque
étape = quelque chose de visible/testable, jamais une couche technique isolée.
Ordre pressenti : (1) enveloppe par surfaces + DPE recalculé → (2) `RenovationAdvisor`
+ conseils → (3) tiroir latéral + séparation lire/agir → (4) nœuds chauffage
(émetteurs/SCOP, granulés) → (5) production/ECS → (6) gestes.
