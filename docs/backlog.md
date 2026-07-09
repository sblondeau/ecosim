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

## Robustesse

- ~~Versionner le format de session~~ : fait (champ `version` + reset si mismatch).
- ~~CSRF sur les POST~~ : fait (`csrf_token('game')` + vérification contrôleur).
- **Phase dupliquée dans la calibration** : `WeatherCalibration::coldestDayOfYear`
  et `EnergyCalibration::householdDemandPeakDayOfYear` encodent la même réalité
  (creux thermique de mi-janvier) en deux endroits — risque de dérive, à
  rapprocher.
