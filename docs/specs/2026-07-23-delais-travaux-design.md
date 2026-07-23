# Délais de travaux — design (phase « rythme »)

**Date** : 2026-07-23
**Statut** : design validé, prêt pour le plan d'implémentation.
**Nature** : première extension **post-MVP / V1.x**. Le MVP (boucle §15 + arbre
resserré + catalogue + onboarding) est complet ; cette phase ouvre le volet
« la rénovation a un rythme ».

## 1. Problème (diagnostic joueur, juillet 2026)

Le plus gros défaut ressenti du gameplay : on clique **« gratuitement » (car
PTZ) sur tous les travaux**, on obtient une **maison rénovée au max en 3 jours
sans douleur**, puis **il n'y a plus rien à faire**. Deux racines distinctes :

1. **Zéro friction / zéro rythme** : les travaux sont **instantanés** → tout
   maxable en 3 jours, le milieu d'année est vide.
2. **Le prêt est quasi-gratuit dans l'horizon** : horizon 1 an (365 j) ≪ terme
   éco-PTZ 20 ans (240 mois), 0 % → on ne paie que **~12 mensualités sur 240**,
   ~95 % du coût tombe *après* la fin de partie.

**Cette phase attaque la racine 1 (le rythme), par les délais.** Décision
joueur : *tester les délais seuls, puis juger* si la sensation « gratuit »
persiste avant d'ouvrir le volet « poids financier » (racine 2). Ce volet
suivant est déjà cadré et noté (`docs/backlog.md` : **taux d'endettement +
crédit immo explicite**, le vrai frein réaliste au « PTZ gratuit »).

## 2. Principe

Un travaux commandé n'est plus **appliqué** au tick courant mais **programmé** :
il se pose à une **date de fin déterministe** (semée comme tout le reste). Le
temps réel du jeu s'écoule d'ici là, d'autres choses arrivent, le calendrier se
remplit — et surtout la **panne** devient un vrai arbitrage temporel.

Le levier reste **le coût d'accès = prix + délai + prérequis** (game-design §1),
jamais une magnitude truquée ni un verrou artificiel.

## 3. Le modèle de délai — `prix + délai_avant_chantier + durée_chantier`

On **ne modélise pas** les sous-phases administratives (contact → devis →
acceptation) comme des compteurs successifs : côté joueur c'est un compte à
rebours, les découper alourdit l'état pour zéro gain. Elles sont **fondues dans
le délai avant chantier**, narrées en infobulle.

**Mais on sépare `délai_avant_chantier` (lead) et `durée_chantier` (pose)** —
pas par purisme, pour un **payoff visible** : le délai avant chantier peut faire
plusieurs mois, et **la scène ne doit montrer l'asset travaux que pendant le
chantier réel**, pas dès la commande. Il faut donc la fenêtre `[début, pose]`.

Plus **un délai conditionnel au financement** (le seul qui porte une leçon
distincte) : le déblocage éco-PTZ, qui s'ajoute *avant* le chantier.

- `début_chantier = commande + (PTZ ? délai_fonds_PTZ : 0) + délai_avant_chantier`
- `pose = début_chantier + durée_chantier`

Le `délai_fonds_PTZ` est séparé **parce qu'il dépend du financement** et porte la
leçon « le PTZ n'est pas mobilisable en urgence ».

**Narration, pas mécanique** : le devis *décrit* la composition du lead en
infobulle sourcée (ex. *« ~8 semaines : carnet de l'artisan RGE très demandé »*)
et la durée de pose (ex. *« + 2 j de chantier »*) — les nombres sont mécaniques,
le détail des sous-étapes reste du texte.

Le réel « on compare plusieurs artisans » justifie *narrativement* la longueur du
lead, ce n'est pas une phase simulée.

### Prix
**Fixe, connu d'avance** (déjà le cas via `RenovationQuoter`). Pas de devis
multiples à prix variables : ce serait du **bruit**, pas une leçon — le fun et
la pédagogie vivent dans le délai et le coût d'accès, pas dans le marchandage.

### Délais par travaux (ordres de grandeur — à confirmer sur sources primaires avant codage, §13)
Chaque travaux porte **son lead + sa durée de chantier** (catalogue-driven, cf.
§5). Regroupement indicatif :

| Nature | Avant chantier (lead) | Durée chantier (pose) |
|---|---|---|
| **Réparation chaudière fioul** (l'exception rapide) | ~1-2 j | ~quelques h → 1 j |
| Isolation combles / gestes | ~2-4 sem. | ~1 j |
| ITE (murs par l'extérieur) | ~4-8 sem. | ~1-3 sem. |
| Chauffage (PAC, granulés) | ~4-8 sem. | ~1-3 j |
| PV + admin (mairie ~1 mois, Consuel/Enedis) | ~1-2 mois | ~1-2 j |

Plus, **global et conditionnel** : **déblocage éco-PTZ ~4-8 sem.** (⚠️ **pas de
source dure** sur le délai bancaire, assumé comme ordre de grandeur — §13),
ajouté *avant* le lead pour un travaux financé au PTZ.

Tous en `Coefficient` (valeur + fourchette + source + date). **Déterministes**,
aucun dé.

## 4. Le payoff — la panne du 20 janvier devient un arbitrage réel

- **Réparer** : chantier ~1-3 j, comptant → on survit à l'hiver. Rationnel *sur
  le moment*.
- **PAC** : ~4-8 sem. si comptant, **bien plus via PTZ** (déblocage + chantier) →
  intenable sous appoint électrique en janvier.
- ⇒ la seule façon d'avoir la PAC **sans subir** = **l'installer AVANT la
  panne**. « Anticiper vs subir » passe de slogan à mécanique. C'est la
  convergence exacte avec §1 et l'événement panne §15.

Et le « max en 3 jours » **meurt tout seul** : chaque chantier prend des
jours/semaines, le calendrier se remplit, l'anticipation devient la stratégie.

## 5. Architecture

### Domaine
- **`GameState`** gagne **`scheduledWorks: list<ScheduledWork>`**, chaque
  `ScheduledWork` = `{ workSlug: string, chantierStartDay: int, completionDay:
  int }` (VO `final readonly`, `Domain/Finance` ou `Domain/Simulation`). Les
  **deux dates** portent la fenêtre du chantier : la scène n'affiche l'asset
  travaux que sur `[chantierStartDay, completionDay]` (§3 — un lead de plusieurs
  mois ne doit pas montrer l'échafaudage dès la commande). Semé/déterministe,
  (dé)sérialisé par `SessionGameStore` (bump `FORMAT_VERSION`).
- **`RenovationDefinition`** (l'interface catalogue) gagne **`leadDelayDays():
  int`** et **`buildDelayDays(): int`** — chaque `Work` renvoie ses durées,
  adossées à des `Coefficient`. Même patron que
  `offerFor()`/`sceneLayerFor()`/`iconAsset()`.
- **`FinanceCalibration`** gagne le `Coefficient` **`ptzFundsReleaseDelayDays`**.
- **`RenovationHandler::order()`** : **programme au lieu d'appliquer**. Il
  re-cote et applique les règles de financement **comme aujourd'hui** (paie
  comptant / emprunte au PTZ *à la commande*, prime déduite au devis), mais au
  lieu de muter le foyer il **ajoute un `ScheduledWork`** avec
  `chantierStartDay = jour + (PTZ ? délai_fonds : 0) + leadDelay` et
  `completionDay = chantierStartDay + buildDelay`.
- **Offres calculées contre le « foyer projeté »** (= foyer courant + tous les
  `scheduledWorks` déjà appliqués) au lieu du foyer courant. Ça résout **d'un
  coup**, sans concept d'exclusivité à maintenir :
  - re-commander un travaux **déjà en cours** → non proposé (déjà là dans le
    projeté) ;
  - **conflit de même fonction** (ex. granulés alors qu'une PAC est en cours) →
    non proposé (le projeté a déjà la PAC) — **cohérence, pas verrou §1** (un
    seul générateur, un seul chantier sur ce slot à la fois) ;
  - **parallèle non-conflictuel** (murs alors que les combles sont en cours) →
    toujours proposé (surface différente). ✅
  Réutilise la logique catalogue existante (`offerFor`/quote contre *un* foyer) ;
  on lui passe le projeté. Le limiteur global reste la **trésorerie** (pas de
  verrou de nombre §1).
- **`SimulationEngine`** : dans sa boucle jour déjà semée, tout `ScheduledWork`
  dont `completionDay <= jour` → **applique l'effet du travaux au foyer
  courant** (effet re-dérivé du catalogue, donc plusieurs chantiers se composent
  proprement), et le retire de la liste. Le tick **rapporte les transitions du
  jour** (chantier démarré / chantier posé) pour que la présentation puisse en
  faire des notices (elles sont *pilotées par le temps*, pas par une LiveAction).
- **`TimeKeeper`** : **inchangé** — il continue de ne mettre en pause que pour la
  **panne** (décision). Un chantier ne déclenche **aucune pause auto** (choix de
  feel : la fluidité prime ; l'icône chantier + les notices portent la
  révélation).

**Note money-flow** : ⑥ (avancer les aides) étant différé, **aucune liste de
« rebates », aucun nouveau flux d'argent** — la seule nouveauté est que l'effet
foyer arrive en différé. Une seule liste (`scheduledWorks`).

### Présentation
- **`GameView`** expose les chantiers en cours : slug, date de pose, jours
  restants, **zone** (`SceneSlot`) concernée. Et la **ligne prêt** montre
  `borrowedTotal / cap` (« X € / 50 000 € max »).
- **`QuoteCard`** : si le travaux est **en cours**, la carte **bascule** de son
  bouton de commande à un état *« ⏳ Chantier en cours — posé le 20 nov (dans
  17 j) »*. Le statut vit **là où on a agi**.
- **Scène** (`HouseSceneView`/`_cutaway`) : indicateur **travaux** léger
  (échafaudage / cône) sur **la zone en chantier**, **uniquement pendant la
  fenêtre `[chantierStartDay, completionDay]`** — pas pendant le lead (qui peut
  durer des mois : rien à voir sur la maison tant que l'artisan n'est pas venu).
  « Ça bosse » rendu **spatialement**, show-don't-tell, même patron
  catalogue-driven que `envelopeLayers`. Un « layer en cours » par slot.
- **Devis** : délai affiché (« posé le … » ; « fonds ~X sem. » pour le PTZ) +
  infobulle de composition sourcée (§3).
- **Notices, pas de modale ni de pause** : une notice **au début** du chantier
  (« Les travaux d'isolation ont commencé ») et une **à la pose** (« Vos combles
  sont isolés »). La modale bloquante reste réservée aux **décisions/événements**
  (intro, briefing, panne) — un chantier ne demande rien, l'enchaîner de modales
  serait du nagging. La **révélation** est portée par : l'icône chantier pendant
  la fenêtre + l'équipement qui apparaît à la pose + la carte « ✔ fait » + les
  deux notices. (Si plusieurs chantiers transitionnent le même jour, les regrouper
  en une notice — « 2 chantiers terminés » — plutôt que d'en écraser.)
- **Pas de panneau dédié, pas de calendrier** : un calendrier supposerait une
  planification sur grille de dates qui n'existe pas (la date de pose est
  *calculée*, pas choisie) — corps étranger dans une UI minimale et spatiale.
  Une puce HUD « 🔧 N chantiers » reste possible **plus tard** si carte + scène
  ne suffisent pas ; on démarre sans.

## 6. Garde-fous (§1, §13)

- **Déterminisme** : délais fixes/semés (`Coefficient`), jamais un dé — le tick
  reste rejouable.
- **Pas de verrou artificiel** : concurrence libre, pacée par la trésorerie ; le
  seul refus est « ce chantier est déjà en cours » (fait, pas gate).
- **Réparation chaudière = l'exception rapide assumée** : c'est cet écart de
  délai qui *crée* l'arbitrage de la panne (le casser en rendant tout lent
  supprimerait le choix).
- **Sourçage honnête** : chantiers sur guides ADEME/pro ; le délai bancaire PTZ
  assumé « sans source dure » (ordre de grandeur), pas maquillé en chiffre
  sourcé.

## 7. Hors périmètre (→ `docs/backlog.md`)

- **③ Saisonnalité des délais** (carnets qui explosent en automne/hiver).
- **④ Inconfort de chantier** (jours sans chauffage pendant un remplacement de
  générateur — se branchera sur l'appoint électrique existant de la panne).
- **⑥ Avancer les aides** (MaPrimeRénov' versée après travaux) + **accomptes**
  (30 % commande / solde pose) — rouvrent le flux d'argent, volet cash-flow.
- **⑦ Dette qui mord** au-delà du repère `/50 000`.
- **Taux d'endettement + crédit immo explicite** (le volet « poids financier »
  juste après — noté avec sa calibration ACPR ~30 %).
- **Cap PTZ tiéré** (15/25/30/50 k), **RGE explicite**, **⑧ horizon/terme**.

## 8. Tests (même commit que le code, §5)

- **Domaine** :
  - `ScheduledWork` : VO, égalité, (dé)sérialisation, fenêtre
    `chantierStartDay`/`completionDay`.
  - `RenovationHandler` : une commande **programme** (n'applique pas au jour
    même) ; `chantierStartDay` = jour + lead (comptant) ; + délai_fonds (PTZ) ;
    `completionDay` = start + build ; concurrence autorisée si finançable.
  - **Foyer projeté** : granulés **non proposé** tant qu'une PAC est en cours ;
    murs **toujours proposé** pendant un chantier combles ; re-commande d'un
    travaux en cours refusée.
  - `SimulationEngine` : un `ScheduledWork` échu applique l'effet **au bon
    jour** (`completionDay`) ; deux chantiers se composent ; rien avant ; le tick
    **rapporte** les transitions début/fin du jour.
  - Délais asymétriques : réparation < PAC < PV (valeurs exactes semées).
  - `TimeKeeper` : un chantier ne déclenche **aucune** pause (seule la panne
    pause — non-régression).
- **Intégration** (`GameDashboardTest`) : commander → `QuoteCard` en état « en
  cours » ; **asset scène absent pendant le lead, présent pendant le chantier,
  puis remplacé par l'équipement posé** ; **notice au début et à la pose** (pas
  de pause auto, le jeu continue) ; **le PTZ n'est pas mobilisable pour la
  panne** (délai le porte au-delà de l'urgence) ; ligne prêt « X € / 50 000 ».

## 9. Suite

Spec validée → plan d'implémentation (`writing-plans`), en **petites étapes
démontrables** (§8) : ex. (a) programmation + résolution moteur + pause (un
délai unique), (b) délais asymétriques par travaux + délai PTZ, (c) UI carte +
scène + ligne plafond. Mettre à jour `docs/backlog.md` (entrées « délai
éco-PTZ » / « durée des travaux » → cette phase).
