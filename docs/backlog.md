# Backlog — améliorations notées, non planifiées immédiatement

Petites améliorations identifiées en cours de route (audits, revues), à
raccrocher aux étapes où elles deviennent utiles. Ne pas les faire « en
avance » : chacune est notée avec son déclencheur.

## Météo — à faire ensemble à l'étape 5 (chauffage/confort)

Ces deux raffinements du générateur de température n'ont d'effet visible que
lorsque la température pilote quelque chose (besoin de chauffage, confort) :

- **Bande de bruit saisonnière** : `dailyTemperatureNoiseC` est fixe (±3 °C)
  toute l'année alors que la variabilité réelle est plus forte en hiver
  (advection de masses d'air : ~±4 °C) qu'en été (~±2 °C). Faire suivre la
  bande par le cycle hivernal, comme la moyenne :
  `noiseBand = meanNoise + noiseSeasonalAmplitude × winterCycle(date)`,
  avec un nouveau `Coefficient` sourcé (Météo-France) dans `WeatherCalibration`.
- **Persistance de la température** : le bruit journalier est blanc
  (indépendant d'un jour à l'autre) → pas de « vague de froid » qui s'installe.
  Donner à la température le mécanisme de persistance déjà utilisé par la
  nébulosité (points de contrôle interpolés, `cloudPersistenceDays`).
  Pédagogiquement clé : une semaine de froid continu est le scénario qui met
  un chauffage (et une facture) sous tension.
- **Extraire le value noise vers `Domain/Math`** : `hash01`/`lerp`/`smoothstep`
  (+ `clamp01`) sont des primitives mathématiques génériques, privées dans
  `WeatherGenerator` faute de second consommateur (même règle d'extraction que
  `SeasonalCycle`, sorti quand le solaire en a eu besoin). Le bruit sur la
  demande et la persistance de la température (ci-dessus) seront ces seconds
  consommateurs → extraire alors une classe qui nomme le concept (bruit 1D
  semé et lissé : `(seed, index, salt) → valeur`), plutôt que 4 helpers en vrac.

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

- **Bruit journalier sur la demande** : la demande est une sinusoïde pure →
  autoconsommation plate à l'intérieur d'une saison (95 % chaque jour d'été).
  Un petit bruit semé (comme la météo) rendrait chaque jour différent — le
  livrable §15 promet « météo/demande variables ». À faire avec l'étape 5.
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
