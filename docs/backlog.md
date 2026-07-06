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

## Robustesse (avant multiplication des actions joueur)

- **Versionner le format de session** (`SessionGameStore`) : les fallbacks
  silencieux (`?? 0.0`) transforment une session d'un ancien format en partie
  absurde (équipement à 0) au lieu de la réinitialiser. Ajouter un champ de
  version et `reset()` si mismatch.
- **CSRF sur les POST** : les formulaires bruts du dashboard n'ont pas de
  `csrf_token()` et le contrôleur ne vérifie rien. Faible enjeu tant que la
  seule action est « jour suivant », à poser proprement avec les vraies
  actions joueur (installer, emprunter…).
- **Phase dupliquée dans la calibration** : `WeatherCalibration::coldestDayOfYear`
  et `EnergyCalibration::householdDemandPeakDayOfYear` encodent la même réalité
  (creux thermique de mi-janvier) en deux endroits — risque de dérive, à
  rapprocher.
