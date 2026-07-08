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
- **UX batterie** : avec la calibration actuelle (5 kWh, demande nocturne
  ~5-7 kWh), la batterie finit à 0 kWh tous les soirs, été comme hiver — la
  jauge « niveau de fin de journée » affichera toujours 0 et paraîtra cassée.
  Afficher plutôt l'énergie déchargée du jour (« la batterie a couvert X kWh
  ce soir ») et/ou revoir le couple capacité / répartition jour-nuit.
  À traiter au plus tard avec les actions joueur (installation batterie).
- **Départ équipé vs nu** : la partie démarre avec 3 kWc + 5 kWh installés,
  ce qui contredit le scénario primo-accédant et vide la décision « installer »
  de son sens. Passer au départ nu **dans le même commit** que l'action
  d'installation (sinon le jeu devient inerte entre-temps). Décision actée.
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
- **Durée de vie / dégradation de la batterie dans le ROI** (déclencheur :
  étape finances, calculs de retour sur investissement). L'autodécharge
  (~1-3 %/mois) est volontairement ignorée : négligeable au tick journalier,
  et la batterie se vide chaque nuit de toute façon. En revanche la **perte de
  capacité** (~2-3 %/an, garanties typiques 70-80 % après 10 ans) participe au
  mauvais ROI de la batterie seule (retour 15-20+ ans, game-design §8) — à
  intégrer en `Coefficient` sourcé quand les calculs de ROI arriveront, pour
  rester fidèle au principe « ne jamais forcer un ROI positif ».

## Robustesse

- ~~Versionner le format de session~~ : fait (champ `version` + reset si mismatch).
- ~~CSRF sur les POST~~ : fait (`csrf_token('game')` + vérification contrôleur).
- **Phase dupliquée dans la calibration** : `WeatherCalibration::coldestDayOfYear`
  et `EnergyCalibration::householdDemandPeakDayOfYear` encodent la même réalité
  (creux thermique de mi-janvier) en deux endroits — risque de dérive, à
  rapprocher.
