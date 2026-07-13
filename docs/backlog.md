# Backlog — améliorations notées, non planifiées immédiatement

Petites améliorations identifiées en cours de route (audits, revues), à
raccrocher aux étapes où elles deviennent utiles. Ne pas les faire « en
avance » : chacune est notée avec son déclencheur.

## Météo

- ~~Bande de bruit saisonnière~~ : fait (`temperatureNoiseSeasonalAmplitudeC`,
  ±4 °C en hiver / ±2 °C en été).
- ~~Persistance de la température~~ : fait (`temperaturePersistenceDays`, vagues
  de froid/redoux qui s'installent sur plusieurs jours).
- ~~Extraire le value noise vers `Domain/Math`~~ : fait (`SeededNoise` :
  `uniform`/`centered`/`smooth`, canaux indépendants).

En Phase 5 (météo complète), la **pression atmosphérique** devient la variable
pivot qui corrèle température/nébulosité/vent (game-design §5) — l'anticyclone
hivernal (froid + ciel clair) ne peut pas être produit intentionnellement avant.

- **Faire de l'irradiance une sortie de la météo** (déclencheur : Phase 5, ou
  Phase 2/éolien si le patron devient récurrent avant). Le game-design §5 liste
  « irradiance (dérivée) » comme variable météo, mais aujourd'hui le modèle du
  ciel vit dans `EnergyCalibration`/`SolarProductionCalculator` :
  `solarPeakDayOfYear`, `solarClearSkyPeakSunHoursMean`,
  `solarSeasonalAmplitudeHours`, `solarCloudLossFactor` sont de la climatologie
  d'irradiation / optique atmosphérique, pas des propriétés de l'installation.
  Cible : la météo livre l'irradiance du jour (dérivée saison + nuages), et
  `SolarProductionCalculator` ne fait plus que la conversion
  (irradiance × kWc × performance ratio) — seuls performance ratio et
  équipement restent côté Energy. Même patron ensuite pour l'éolien (la météo
  livre le vent, l'énergie le convertit).

## Énergie / gameplay

- ~~Bruit journalier sur la demande~~ : fait (`householdDemandDailyNoiseKwh`,
  bruit blanc semé ±1,5 kWh/j).
- ~~UX batterie~~ : fait (la jauge montre l'énergie restituée à la maison le
  soir, pas le niveau de fin de journée toujours nul).
- ~~Départ équipé vs nu~~ : fait (départ nu, dans le même commit que les
  actions d'installation).
- **Production par phase dans `settle()`** (déclencheur : Phase 2, éolien).
  La signature de `EnergyBalanceCalculator::settle()` est agnostique de la
  source (`float $productionKwh`), mais le modèle interne suppose une
  production « en forme de soleil » : tout en journée, zéro la nuit. L'éolien
  produit aussi la nuit — sommer ses kWh dans le pot « jour » détruirait la
  leçon d'intermittence combinée que la Phase 2 vient enseigner (§15). Cible :
  passer la production par phase (`$daytimeProduction`, `$nightProduction`),
  solaire → jour, éolien réparti sur les deux ; mêmes priorités
  (direct → batterie → réseau) appliquées à chaque phase. Bonus : la batterie
  pourra se charger la nuit avec du vent.

- **Totaux par vecteur énergétique** (déclencheur : V1.1 véhicule/carburant, ou
  comptabilité CO₂). `PeriodTotals` nomme ses vecteurs en dur (kWh électriques,
  litres de fioul) — fidèle au scope « facture 2 lignes » de la Phase 0-1, mais
  dès qu'un 3ᵉ vecteur arrive (essence du véhicule, §18 V1.1) ou qu'il faut
  sommer du CO₂ par vecteur, généraliser en totaux indexés par un enum
  `EnergyCarrier` (quantité + unité par vecteur) sur lesquels facture et bilan
  itèrent, plutôt que d'empiler des champs `xxxLitres`. Chaque vecteur reste
  une ligne séparée (le joueur doit voir quel usage coûte quoi — §18 V1.1 :
  le carburant du véhicule est une ligne à part, pas fusionnée avec le fioul) ;
  l'agrégat « énergie fossile » (budget/CO₂ total) devient alors une somme
  dérivée sur les vecteurs, l'indicateur de l'effet global de l'électrification.
- **Paramétrer la taille/le volume du logement** (déclencheur : V2, scénario
  locataire / plusieurs logements). En Phase 0-1 il n'y a qu'UNE maison, donc
  sa surface (~100 m²), ses ouvertures et sa géométrie sont volontairement
  *fondues dans* les coefficients par-maison (`heatLossKwhPerDegreeDay`,
  `coldWallPenaltyFactor`) plutôt qu'exposées en paramètres jamais variés.
  Quand plusieurs logements existeront : passer à des coefficients par m²
  (kWh/m²/DJU) × surface, et faire dépendre l'effet parois froides de la part
  de parois déperditives (murs extérieurs, vitrages).
- **Solaire dans le DPE et valeur verte des équipements** (déclencheur :
  Phase 4, vraie `PropertyValuation`). La matrice DPE (isolation × chauffage)
  ignore le photovoltaïque — fidèle au réel tant que le chauffage est au fioul
  (le 3CL ne déduit que la part AUTOCONSOMMÉE, négligeable face au fioul), mais
  approximatif après électrification (la PAC rend l'offset solaire tangible,
  un gain de classe devient possible en cas limite). À raffiner alors :
  déduction 3CL de l'autoconsommation dans le calcul de classe, et « valeur
  verte » propre des équipements hors DPE (installation PV revendable +
  contrat de rachat attaché au bien) dans la valorisation. Garde-fous actés :
  pas de double comptage (le canal DPE rémunère la performance du logement,
  la valeur verte rémunère l'équipement), sourçage honnête (littérature
  française mince sur le PV — fourchette large ou omission, §13), magnitude
  bornée par le coût de l'installation et **dépréciation avec l'âge** (des
  panneaux de 15 ans ne valent pas le neuf) — sinon poser des panneaux
  deviendrait une machine à imprimer de la valeur immobilière (« jamais de
  ROI positif forcé », §8).
- **Bilan de fin par axes, jamais de « patrimoine global »** (déclencheur :
  étape 7, bilan de fin). Décision actée : pas d'agrégat épargne + valeur du
  bien − dette — le §1 interdit explicitement de sommer liquide et potentiel
  (« fausse précision »), le §8 verrouille les 3 blocs jamais fusionnés. Le
  bilan montre chaque delta séparément (épargne, valeur du bien, dette PTZ
  restante, confort moyen, énergie). Seul sous-total défendable plus tard,
  interne au bloc Patrimoine : « bien net de dette » (deux grandeurs non
  liquides de même nature). Pas de mécanisme d'achat/revente du bien (§15 :
  pas de marché immobilier dynamique) — la valeur est un indicateur de
  conséquence, pas un objet de transaction.
- **Véhicule (V1.1) : reprise déduite, hors patrimoine** (déclencheur : V1.1).
  Coût réel du passage au VE = prix − aide (`SubsidyProgram` réutilisée, §18)
  − **valeur de reprise de la thermique** (argus) — sans la reprise, le VE
  serait artificiellement pénalisé. La voiture n'entre PAS dans l'axe
  Patrimoine : le §18 la justifie « sur les seuls axes Finances et Carbone »,
  et un actif à dépréciation rapide (~15-25 %/an) exigerait une courbe de
  dépréciation continue pour un axe conçu autour du logement. Sa valeur
  résiduelle se matérialise une seule fois, à l'échange (la reprise).
- **Frais d'entretien annuels par équipement** (déclencheur : écran de ROI, ou
  V1.1 quand le coût complet de possession devient un axe de comparaison).
  Aujourd'hui `monthlyExpenses` est un forfait de vie (INSEE) insensible à
  l'installation. Or l'entretien est réglementé et diffère par équipement :
  chaudière fioul = entretien **annuel obligatoire** (décret 2009-649,
  ~150-200 €/an), PAC = entretien obligatoire **tous les 2 ans** (décret
  2020-912, PAC 4-70 kW, ~150-300 €/an), panneaux ≈ 0 (nettoyage), batterie ≈ 0.
  À intégrer en `Coefficient` sourcés (décrets + ADEME) comme ligne de charge
  périodique — l'écart d'entretien fait partie de la comparaison honnête
  fioul/PAC (§13), même s'il est faible devant l'écart de facture énergie
  (~3 000 €/an). Sans effet sur les décisions au MVP → différé.
- **Durée de vie des équipements** (déclencheur : le même écran de ROI). Aucun
  modèle d'usure au MVP — la seule matérialisation « fin de vie » est la panne
  de chaudière **scriptée** (étape 7, choix du §15 : 1 événement scripté, pas
  un compteur d'usure). À l'horizon fixe du MVP (quelques centaines de jours),
  une PAC (~17 ans) ou des panneaux (~30 ans) ne s'usent jamais en partie. Les
  durées de vie sourcées (ADEME) deviennent nécessaires dès qu'on affiche un
  ROI : le game-design §8 exige de montrer qu'un retour > 40 ans dépasse la
  durée de vie des équipements — même déclencheur que la dégradation batterie
  ci-dessous.
- **Durée de vie / dégradation de la batterie dans le ROI** (déclencheur :
  étape finances, calculs de retour sur investissement). L'autodécharge
  (~1-3 %/mois) est volontairement ignorée : négligeable au tick journalier,
  et la batterie se vide chaque nuit de toute façon. En revanche la **perte de
  capacité** (~2-3 %/an, garanties typiques 70-80 % après 10 ans) participe au
  mauvais ROI de la batterie seule (retour 15-20+ ans, game-design §8) — à
  intégrer en `Coefficient` sourcé quand les calculs de ROI arriveront, pour
  rester fidèle au principe « ne jamais forcer un ROI positif ».

## Interface / pédagogie (retours de la première partie complète, juillet 2026)

Constat après une partie d'un an jouée en entier : le tableau de bord est
exact mais peu pédagogue — on voit les chiffres sans toujours comprendre ce
qu'ils signifient ni ce qu'un choix va changer.

- ~~Layout compact~~ : fait (grille 1200 px, cartes denses, tout au-dessus de
  la ligne de flottaison).
- ~~Infobulles pédagogiques sourcées~~ : fait (`GameView::$help` construit
  depuis le registre de calibration — chiffres cités = chiffres simulés,
  sources nommées ; infobulles CSS pur).
- ~~Effets attendus sur les devis de travaux~~ : fait (`AnnualOutcomeEstimator` :
  le VRAI moteur tourne un an de météo type — seed de référence fixe, jamais
  celle de la partie (pas d'oracle) — facture ≈ ±10 €, confort, production,
  autosuffisance ; batterie sans panneaux = « aucun effet », honnête).
- ~~Historique météo en graphe~~ : fait en mieux — la météo étant semée et
  déterministe, les 30 derniers jours sont **recalculés** à l'affichage
  (aucun stockage, pas de bump de session) et rendus en sparkline SVG sans JS.
  La production, elle, dépend de l'équipement passé → série à stocker le jour
  où on la voudra dans le graphe (persistance Doctrine).
- **Dashboard visuel à l'arrivée de la grille** (déclencheur : Phase 3). Le
  tableau de bord Twig austère est un choix assumé du MVP ; l'architecture le
  prévoit déjà (§3 : la présentation ne lit que `GameView`, donc remplaçable
  par canvas/three.js sans toucher au métier). Quand la carte arrive, refondre
  la présentation en rendu visuel — c'est exactement pour ça que le domaine ne
  connaît pas Twig.

Déjà traité ailleurs : les 365 clics « Jour suivant » relèvent du tick temps
réel / LiveComponent `data-poll` (décision actée : `SECONDS_PER_GAME_DAY`
~30 s, politique `PausesWhileAway`), plus bas dans la feuille de route.

## Confort d'été & rénovation granulaire (réflexion joueur, juillet 2026)

**Acté au game-design §16** (source de vérité — cette entrée n'est que le
rappel). Prérequis : Phase 5 (canicules) ET refonte visuelle « jeu » (§17).

Constat : le jeu ne modélise que l'hiver. La plage de confort 19-26 °C existe,
mais en été la maison suit la moyenne extérieure (pas d'apports solaires, pas
de canicule dans la météo) → le confort d'été est « gratuit ». Et l'isolation
à 3 paliers abstraits épuise les décisions en ~4 choix pour 365 jours.
Déclencheur global : **après la Phase 5** (la météo à événements extrêmes est
le prérequis physique : sans canicule, aucun de ces investissements ne sert),
ou un jalon dédié « V1.x confort d'été » avec un générateur de canicules
minimal si on veut l'avancer.

- **Éclater l'isolation en éléments** : combles (~25-30 % des pertes), murs
  (~20-25 %), fenêtres/huisseries (~10-15 %), plancher bas (~7-10 %) — la
  répartition ADEME des déperditions. Chaque geste = coût/gain/prime propres →
  plus de décisions dans la durée, budget étalé, et l'effet de séquencement du
  game-design §8 (isoler avant de dimensionner la PAC) devient tangible.
- **Matériaux à double caractéristique hiver/été** : conductivité (R) pour
  l'hiver, **déphasage/inertie** pour l'été (fibre de bois ~10-15 h de
  déphasage vs laine minérale ~4-6 h à R égal — CSTB/ADEME). Pédagogie rare :
  deux isolants « équivalents » l'hiver ne le sont pas l'été.
- **Protections solaires** : volets/brise-soleil/films (facteur solaire g),
  petites dépenses à gros effet été — des « quick wins » de gameplay.
- **Rafraîchissement passif** : surventilation nocturne, brasseurs d'air
  (+2-3 °C de ressenti pour ~30 W), puits provençal/canadien, VMC double flux.
  L'échelle des solutions AVANT le compresseur.
- **Climatisation, arbitrage honnête (jamais moralisateur, §1)** : confort
  gagné vs kWh d'été (dont la synergie réelle clim ↔ solaire : le pic de clim
  coïncide avec le pic PV), CO₂ selon le contenu carbone du réseau, fuites de
  fluides frigorigènes (PRG élevé), rejet de chaleur. La PAC air/eau réversible
  raccorde ça à l'équipement existant.
- **Confort adaptatif été** : la borne haute de confort n'est pas 19 °C — en
  été on est bien jusqu'à ~26-28 °C (EN 16798) ; la plage 19-26 actuelle
  l'encode grossièrement, à raffiner quand l'été devient un vrai sujet
  (bornes saisonnières).

## Confort thermique : représentation + consigne réglable (réflexion joueur, juillet 2026)

Constat : le confort n'est qu'un %, peu lisible, et surtout **sans conséquence
mécanique** — donc dominé par l'argent. Le vrai problème est le même que la
panne avant l'appoint forcé : baisser le chauffage économise sans coût réel.
Deux résolutions, à mener DANS CET ORDRE.

- **Rendre le confort VISIBLE (à faire tôt, aucune mécanique nouvelle)** :
  c'est un problème de représentation, pas de modèle. Dans la coupe : un
  **occupant** piloté par le confort **RESSENTI** (`feltC`, pas la consigne
  d'air), le radiateur chaud/tiède/froid, éventuellement un boîtier thermostat
  mural affichant la température. Maquette : `docs/mockups/occupant.html`
  (4 états SVG à couches, palier = classe CSS). Paliers proposés sur le
  ressenti : transi < 14 °C · frileux 14-18 °C · à l'aise 18-25 °C · en sueur
  > 25-26 °C. **Point clé sourcé** : le confort dépend de la température
  OPÉRATIVE (air + parois), pas de l'air seul → 19 °C n'est PAS une solution
  unique. En passoire, 19 °C d'air ≈ 16 °C ressenti (parois froides, déjà
  modélisées) ; rénové, 19 °C d'air ≈ 18-19 °C ressenti. Le DPE devient donc un
  déterminant du confort, gratuitement. Sources : OMS *Housing and Health
  Guidelines* 2018 (mini 18 °C, 20 °C vulnérables) ; ADEME (19 °C pièces de
  vie, +1 °C ≈ +7 % conso) ; Code de l'énergie R241-26 (plafond 19 °C) ;
  EN 16798-1 / ASHRAE 55 (confort adaptatif, plage saisonnière, jusqu'à
  ~26-28 °C l'été — lien §16).
- **Indicateur de précarité énergétique (gratuit, sourcé, à faire tôt)** :
  concept LÉGAL (loi Grenelle II 2010, art. 11 ; suivi ONPE depuis 2011), pas
  inventé. On a déjà coût énergie + revenu → afficher le **taux d'effort
  énergétique** (TEE = coût énergie annuel / revenu annuel). Palier officiel :
  **TEE > 8 %** ET ménage **sous le revenu médian** (la 2ᵉ condition exclut les
  riches en grande maison ; notre primo-accédant modeste la remplit). ~12 M de
  personnes en France (ONPE, ordre de grandeur). Autres indicateurs officiels
  si besoin : BRDE (bas revenus / dépenses élevées), froid ressenti,
  restriction. Concret scénario : passoire ~3 800 €/33 600 € ≈ 11 % → EN
  précarité ; rénové ~750 € ≈ 2 % → sorti. Bascule visible avec la rénovation,
  zéro nouvelle simulation. Le plus rentable.
- **Éco-gestes : la couche de confort bon marché (V1.x)**. Réponse gameplay à
  « comment améliorer mon ressenti sans trop dépenser ? » — un palier de
  micro-décisions à quelques dizaines d'euros ENTRE « ne rien faire » et
  « rénover », qui augmentent le ressenti par euro en réduisant l'effet parois
  froides / les infiltrations (sources : éco-gestes ADEME). Exemples chacun
  avec un petit effet sourcé sur `coldWallPenaltyFactor` ou l'infiltration :
  rideaux épais/doublés la nuit, calfeutrage (joints), panneaux réflecteurs
  derrière radiateurs, tapis sur sols froids, fermer/ne chauffer que les pièces
  occupées, agencement (canapé loin du mur froid). Enseigne que confort ≠ juste
  température, et que la passoire pénalise le **confort-par-euro**. Nuance de
  design : l'**habillement** (pull/plaid) est un **signe d'inconfort** montré
  par l'occupant, PAS un fix gratuit — sinon « mets un manteau » annulerait la
  pénalité. Déjà en jeu aujourd'hui : l'isolation augmente déjà le ressenti de
  façon disproportionnée (facteur paroi 0,15 → 0,03) ; les éco-gestes en sont
  la déclinaison granulaire.
- **Consigne de chauffe réglable + son anti-abus (couple V1.x — ne JAMAIS
  livrer l'un sans l'autre)**. Un thermostat cliquable (+/−) impacterait
  directement facture et confort — leçon ADEME réelle : **−1 °C ≈ −7 % de
  chauffage**. Mais sans garde-fou, l'optimum est de geler les habitants pour
  économiser (exactement la faille à éviter). L'anti-abus réaliste et élégant :
  **condensation / moisissures**, qui exige de modéliser une **humidité
  intérieure** en plus de la température. Chaîne : le foyer produit de la
  vapeur (~10-15 L/j pour une famille, ordre de grandeur CSTB/BRE) → rencontre
  les **parois froides** (déjà calculées via `coldWallPenaltyFactor`) → quand
  l'**HR de surface dépasse ~80 % durablement**, la moisissure germe (modèle
  isoplèthe de germination, **Fraunhofer IBP / Sedlbauer 2001** — pas besoin de
  condensation visible à 100 %). Baisser la consigne refroidit les parois →
  risque ↑ ; **isoler réchauffe la paroi → protège** (renforce la leçon
  isolation) ; **ventiler (VMC) abaisse l'HR → protège**. Implémentation :
  **jauge de risque DÉTERMINISTE** (pas un dé — le jeu est semé), qui monte au
  fil des jours à HR de surface élevée ; au-delà d'un palier → apparition des
  moisissures (le joueur la voit venir et agit). Conséquences : coût de
  traitement (ANAH), décote du bien, santé. N'interdit rien (§1) : rend juste
  la passoire-froide-humide aussi piégeuse qu'en vrai. Borne basse
  réglementaire en complément, mais c'est la moisissure qui donne les dents.
- **Autres pathologies sourcées du logement mal chauffé** (déclencheurs : axe
  santé V1.x, scénario locataire/bailleur). Toutes réelles et citables :
  santé (OMS ; **Marmot Review 2011**, *Health Impacts of Cold Homes* :
  surmortalité hivernale, cardiovasculaire < 16 °C, respiratoire, santé
  mentale ; étude **Eurowinter**) ; **interdiction de louer** les passoires
  (décret décence 2002-120 : absence de moisissures ; **loi Climat &
  Résilience 2021** : DPE G interdit à la location depuis janv. 2025, F en
  2028, E en 2034 — lie directement DPE et valeur/louabilité) ; acariens &
  allergies (HR > ~60 %) ; condensation sur simple vitrage (signe visible) ;
  dégâts au bâti (plâtre, peintures, menuiseries).

Note de fond (§1) : « avoir froid pour économiser » reste un choix *légitime*
d'un jeu multi-critères (précarité énergétique). Le rôle du jeu est de le faire
voir et ressentir (résolution 1) et de rendre le froid-en-passoire réellement
coûteux (résolution 2), pas de l'interdire par un malus arbitraire.

## Conséquences réalistes de l'inconfort (suites possibles de la panne)

La panne a son urgence systémique (chauffage d'appoint automatique : électricité
×9, confort ~30 %). Deux mécanismes réels restent volontairement en réserve :

- **Santé de l'occupant** (déclencheur : V1.x, si un axe santé est acté).
  Sourçable : OMS (risques sanitaires sous 18 °C), littérature précarité
  énergétique (surmortalité hivernale, Fondation Abbé Pierre). C'est un
  système entier (état, frais médicaux, jours d'arrêt) — pas un compteur de
  « bonheur » décoratif (l'axe Confort EST la mesure du bien-être, §1/§8).
- **Gel des canalisations** (déclencheur : scénario « hiver rude » ou mode
  difficile). Maison non chauffée par gel prolongé → rupture + dégât des eaux
  (milliers d'euros). Réaliste mais punitif ; incohérent tant que l'appoint
  automatique maintient le hors-gel — ne devient pertinent que si un scénario
  prive AUSSI le foyer d'électricité (coupure hivernale, résilience §8).

## Scénarios

Le socle est posé (`Domain/Scenario/` : interfaces `Scenario` + `ScriptedEvent`,
événements réutilisables et paramétrables, `PrimoAccedantScenario`). Reste
volontairement différé :

- **Modes de jeu (local/régional/national) et lien scénario↔mode**
  (déclencheur : Phase 6, échelle ville). L'état, le moteur et la boucle de jeu
  des échelles supérieures sont inconnus aujourd'hui — concevoir le mapping ou
  le **chaînage de scénarios** (un `GameConfig` qui enchaîne plusieurs
  scénarios, horizon global ≠ horizon du scénario) serait deviner. Le découplage
  déjà en place (le scénario fournit le défaut, la config fige l'instance de la
  partie) est précisément ce qui permettra le chaînage le jour venu.
- **`scenarioId` en session + registre de scénarios** (déclencheur : le
  2ᵉ scénario jouable). Un seul scénario aujourd'hui → rien à persister.
- **Conditions de fin par seuil** (déclencheur : un scénario qui en a besoin ;
  le §15 évoque « DPE amélioré + budget stable »). L'horizon fixe est la seule
  fin actée pour la Phase 0-1 ; une méthode `isFinished()` rejoindra
  l'interface `Scenario` à ce moment-là, pas avant (pas de code mort).
- **Scénarios « en donnée » (YAML/DSL de déclencheurs)** (déclencheur : V2+,
  quand écrire des scénarios devient un travail de contenu, pas de code). Les
  événements sont des classes PHP : tout prédicat exprimable en code se
  branche déjà sur `shouldFire()` sans DSL.

## Robustesse

- ~~Versionner le format de session~~ : fait (champ `version` + reset si mismatch).
- ~~CSRF sur les POST~~ : fait (`csrf_token('game')` + vérification contrôleur).
- **Phase dupliquée dans la calibration** : `WeatherCalibration::coldestDayOfYear`
  et `EnergyCalibration::householdDemandPeakDayOfYear` encodent la même réalité
  (creux thermique de mi-janvier) en deux endroits — risque de dérive, à
  rapprocher.
