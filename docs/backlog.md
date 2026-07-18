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

- ~~**Amplitude des extrêmes trop faible (recalibration, sourcé Météo-France)**~~
  : **fait** (juillet 2026). La cause racine n'était pas « coefficients trop
  petits » mais un **bug de mapping** : `dailyTemperatureNoiseC` était sourcé
  comme un *écart-type d'anomalie journalière* (~3 °C) mais consommé comme une
  *demi-bande* que le smoothstep atténuait de moitié (écart-type réel ~1,5 °C).
  Correctif : `SeededNoise::smoothUnit()` (bruit lissé normalisé à écart-type 1)
  → le coefficient sourcé est délivré tel quel. + amplitude saisonnière montée
  à 8,7 (dans la fourchette 6-9, référence France semi-continentale : janvier
  ~4 °C, juillet ~21 °C). Résultats mesurés : **DJU 2 131 → 2 275**, vraies
  vagues de froid (**−4,5 °C**, contre +1,1 avant), fin des jours de chauffe
  estivaux absurdes. **Correction d'un pronostic** : la sensibilité ne tombe PAS
  à 9 % — mesurée **13,9 % → 12 %/°C**. Le modèle degrés-jours journalier est
  *structurellement* sur-sensible (chaque +1 °C de consigne frappe tous les
  jours de chauffe) ; l'écart 12 → 7 % ADEME relève des amortisseurs non
  modélisés → voir « Modèle d'inertie/intermittence » ci-dessous. La
  canicule (~30 °C) reste pour la **Phase 5** (couche d'événements extrêmes : un
  bruit symétrique ne peut pas faire hiver-froid ET canicule à la fois).

- **Modèle d'inertie/intermittence thermique (post-MVP, le vrai correctif de la
  sensibilité)** (déclencheur : axe réalisme thermique fin, ou quand la
  pédagogie de la consigne devient centrale). Constat mesuré : le degrés-jours
  journalier donne une sensibilité thermostat de ~12 %/°C, ~1,7× l'ADEME (~7 %),
  parce qu'il suppose la maison tenue *pile à la consigne 24 h/24, tous les
  jours*. Trois amortisseurs réels, tous modélisables et **déterministes** :
  1. **Apports gratuits variables** (le plus gros levier, gratuit chez nous) :
     `base = consigne − apports(soleil, occupants)` au lieu d'une base fixe à
     18 °C → les jours doux/ensoleillés, la maison flotte au-dessus de la
     consigne, la chaudière ne tire pas, monter la consigne ne coûte rien. **On
     a déjà le modèle nébulosité/irradiance** pour piloter les apports solaires.
  2. **Intermittence / réduit de nuit** : passer en **degrés-heures** avec un
     planning d'occupation (réduit ~16 °C la nuit) → +1 °C de consigne de confort
     n'affecte que les heures occupées (~0,6-0,7 des heures).
  3. **Inertie (masse du bâti)** : modèle RC (résistance-capacité) où la
     température intérieure est un état lissé par la masse — version « propre »
     de (1)+(2), idéalement à pas infra-journalier.

  **Face gameplay du levier 2 — programmation du thermostat** (idée joueur,
  juillet 2026). Mécanique concrète : le joueur *configure* un planning de
  consigne (réduit nuit à partir de ~23 h, éventuellement absence journée) — un
  levier **non-monétaire** (le jeu est très « dépenser de l'argent » sinon) et un
  gain d'efficacité quasi gratuit, exactement ce que l'ADEME recommande.
  **Décision d'architecture actée** : NE PAS passer le tick à l'heure — cadence
  du tick (décisions du joueur = journalière) ≠ résolution de la physique. On
  intègre un **profil horaire dans la fonction pure du tick journalier**
  (degrés-heures : `besoin = Σ_24h heatLoss × max(0, consigne(h) − Text(h))`),
  le tick reste à 1 jour. Passer à un tick horaire multiplierait par 24 les
  transitions d'état (~8 760/an), alourdirait persistance/rendu et casserait le
  rythme temps réel, pour zéro gain (on ne décide rien à l'heure ; un « zoom
  journée » serait de la présentation). **Garde-fous de design** : presets
  (« confort constant » / « réduit nuit » / « réduit nuit + absence »), JAMAIS un
  planificateur 24 curseurs (micro-management anti-pédagogie). **Dépendances** :
  un **profil diurne de température** (nouvelle facette météo — aujourd'hui on ne
  produit qu'une moyenne journalière), les **degrés-heures**, et le **confort
  pondéré par l'occupation** (le réduit nuit ne doit pas pénaliser le ressenti :
  on dort — sinon « baisser la nuit » deviendrait un faux malus). Pédagogie
  sourcée : réduit ≠ inconfort ; et la nuance d'inertie (couper *complètement* la
  nuit ne paie pas dans une maison lourde — surcoût de relance —, le *réduit* si).
  **Protocole scientifique NON BIAISÉ (exigence joueur, à respecter)** :
  construire le modèle sur des critères de *réalisme* (sources, ordres de
  grandeur), mesurer la sensibilité %/°C obtenue, PUIS seulement la comparer aux
  ~7 % ADEME comme **validation a posteriori**. Le 7 % reste « inconnu » pendant
  la fabrication — jamais un paramètre à ajuster (ce serait le fudge refusé).
  Estimation (à tester, pas à viser) : intermittence ×~0,65 + apports variables
  ×~0,85 → **~7,5 %**, donc l'ordre de grandeur tomberait probablement seul.
  Réserve : le 7 % ADEME est lui-même une règle empirique avec son périmètre ;
  « être dans 7-10 % » vaudra validation, pas une constante à matcher au dixième.

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

- **Orientation des panneaux (rendement par pan de toit)** (déclencheur :
  quand un second pan devient jouable, ou un axe réalisme PV plus fin).
  Réflexion joueur juillet 2026, à l'occasion d'un fix de position des
  panneaux dans la scène (§17) : la scène différencie déjà visuellement les
  deux versants (gauche/droit, cf. `_cutaway.html.twig`), ce qui en ferait un
  point d'accroche naturel pour modéliser l'orientation. Constat réel :
  l'orientation/inclinaison est, avec l'ombrage, le facteur qui pèse le plus
  sur le productible PV après la puissance crête — un pan mal orienté (est/
  ouest, pire nord) produit sensiblement moins qu'un pan sud, au même kWc.
  En vrai, on installe quasi toujours sur le(s) pan(s) le(s) plus favorable(s)
  (rarement les deux, le rendement/€ du mauvais pan étant souvent
  insuffisant) — donc un choix de pan avec rendement différent par pan est
  fidèle au réel ET cohérent avec le principe du jeu (le levier reste le
  coût/choix, jamais une magnitude truquée). À calibrer avec source (PVGIS/
  ADEME) le jour où c'est fait, pas de coefficient à vue.

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
- **Délai & conditions d'accès de l'éco-PTZ (le levier « délai » du §1, à
  modéliser)** (déclencheur : Phase 4 économie complète, ou passe réalisme des
  aides). Aujourd'hui l'éco-PTZ est **instantané** au clic — irréaliste. Réel
  (sources : service-public.fr, ADEME, Ministère ; plafonds/durées exacts à
  revérifier avant codage) : **artisan RGE obligatoire**, logement > 2 ans,
  **aucune condition de revenus** (c'est un prêt, la banque évalue la
  solvabilité), action seule éligible depuis 2019, cumulable MaPrimeRénov',
  **jusqu'à 50 000 €** (plafonds plus bas par action : ~15 k€ action seule,
  25 k€ deux, etc.), durée **15 ans** (20 ans seulement pour rénovation globale
  performante — notre jeu met 20 à plat, optimiste). **Obtention = 4-8
  semaines** (devis RGE → dossier bancaire → délai de rétractation ~10-14 j →
  déblocage). **Conséquence de design majeure** : un éco-PTZ n'est PAS
  mobilisable pour une panne d'urgence (on ne reste pas 2 mois sans chauffage).
  L'urgence se paie comptant / crédit conso rapide ; l'éco-PTZ 0 % récompense
  l'ANTICIPATION. Cible : à la panne, l'option PAC-via-PTZ porte un délai (des
  semaines d'appoint électrique en attendant les fonds) → réparer devient le
  choix rationnel de l'urgence, et remplacer *avant* la panne devient la
  stratégie gagnante. Renforce exactement la leçon « anticiper vs subir » de
  l'événement panne, et matérialise le levier coût-d'accès du §1 (prix/délai/
  prérequis, jamais magnitude truquée). Voir `RenovationQuoter` (« travaux
  instantanés en Phase 0-1 »).
- **Durée des travaux (chantier + délais amont/aval, le levier « délai » du §1
  généralisé)** (déclencheur : Phase 4 économie complète, ou passe réalisme des
  aides — même chantier que le délai éco-PTZ ci-dessus, à traiter ensemble).
  Aujourd'hui un travaux est **instantané** : clic → équipement posé dans la
  seconde. Irréaliste et surtout ça **annule le levier délai** du §1 (le coût
  d'accès n'est pas que le prix). Un vrai travaux est une **chaîne de phases**,
  chacune un délai à modéliser :
  1. **Délai de démarrage** (le plus long et le plus sous-estimé) : devis →
     prise de RDV artisan RGE → carnet de commandes. Souvent **plusieurs
     semaines à quelques mois**, très **saisonnier** (forte demande chauffage en
     automne/hiver → délais qui explosent quand on en a le plus besoin). C'est le
     délai qui rend une panne de chaudière en janvier réellement subie.
  2. **Durée du chantier** lui-même. Ordres de grandeur (guides pro/ADEME, à
     **vérifier sur sources primaires avant codage** — §13) : remplacement
     chaudière/PAC air-eau ~1-3 j ; isolation combles soufflés ~1 j ; ITE
     (murs par l'extérieur) ~1-3 semaines ; pose PV ~1-2 j sur toiture.
  3. **Délais administratifs / raccordement** propres au PV : déclaration
     préalable en mairie (**~1 mois**, réglementaire), puis Consuel + mise en
     service Enedis (**plusieurs semaines**) — on produit APRÈS, pas au clic.
  4. **Délai de déblocage des fonds** (déjà couvert : éco-PTZ 4-8 semaines).
  5. **Décalage de trésorerie des aides** : MaPrimeRénov' est **versée après
     travaux** → il faut **avancer l'argent**. Une aide n'améliore pas la
     trésorerie du jour du chantier, seulement après — leçon de cash-flow réelle.
  6. **Conséquence pendant le chantier** : remplacer la chaudière = **jours sans
     chauffage** (appoint / inconfort), ITE = gêne. Le chantier n'est pas neutre.

  **Le délai est ASYMÉTRIQUE par nature de travaux — c'est voulu, ça porte la
  leçon.** La **réparation d'urgence de la chaudière fioul est l'exception
  rapide** : dépannage chauffagiste **~24-72 h** (au tick journalier ≈ +0-2 j),
  réparation ~quelques heures ; seule réserve réaliste = disponibilité d'une
  pièce sur chaudière ancienne (nuance optionnelle). À l'opposé, la PAC porte
  toute la chaîne longue (RGE + carnet saisonnier + éventuel éco-PTZ +
  raccordement). Cet écart **crée** l'arbitrage de la panne au lieu de le
  casser : réparer = court/modéré/mauvais-long-terme (reste fioul), remplacer =
  long/cher/bon. Sous urgence, le délai court rend *réparer* rationnel sur le
  moment — donc la seule façon d'avoir la PAC sans subir le délai est de
  l'installer AVANT la panne. Si la réparation était lente aussi, il n'y aurait
  plus de choix, juste deux mauvaises options.

  **Effet de design** (converge avec le §1 et l'événement panne §15) : le délai
  transforme « j'achète quand je veux » en « je dois **anticiper** » — poser la
  PAC *avant* l'hiver / *avant* la panne devient la stratégie gagnante, subir en
  urgence coûte cher (comptant + inconfort d'attente). Matérialise le levier
  coût-d'accès = **prix + délai + prérequis**, jamais une magnitude truquée.
  Implémentation cohérente avec le moteur : un travaux commandé n'est pas
  appliqué au tick courant mais **programmé** (date de fin = tick + délai,
  déterministe et semé comme le reste) ; l'`EndReport`/HUD montre les chantiers
  en cours. Voir `RenovationQuoter`/`RenovationHandler` (« travaux instantanés en
  Phase 0-1 ») et l'entrée **délai éco-PTZ** ci-dessus (à unifier : les deux
  délais se composent — obtention du prêt PUIS chantier).

Le **cycle de vie des équipements** (usure, entretien, panne, dégradation
batterie) est un système à part entière, décrit dans sa section dédiée plus bas.

## Cycle de vie des équipements — usure, entretien, panne (V1.x)

**Déclencheur : écran de ROI / V1.x** (coût complet de possession comme axe de
comparaison). Cette section **consolide et remplace** trois entrées jadis
éparses (frais d'entretien annuels, durée de vie, dégradation batterie) et y
raccroche la proposition joueur « âge/durabilité + jauge de risque + entretien
manuel » (juillet 2026). Conçu pour donner la **pression active « quitte le
fioul »** qui manque aujourd'hui — au-delà de la seule facture — sans casser le
déterminisme ni la leçon garantie de la panne.

**Le MVP ne bouge PAS.** La panne du 20/01 reste **scriptée** (§15 : « 1
événement scripté, pas un compteur d'usure ») = **leçon garantie**, tout le
monde la vit. Un système d'usure la rendrait *contingente* (un joueur diligent
pourrait ne jamais tomber en panne → rater la leçon). Réconciliation prévue pour
V1.x : la panne scriptée devient un **plancher** (la chaudière est vieille, elle
lâche de toute façon), l'entretien la **retardant sans l'annuler** — préserve la
garantie tout en récompensant l'entretien. À trancher au moment de V1.x.

**Modèle (déterministe, jamais un dé — §5).** Chaque équipement porte un **âge**
et une **santé qui décroît de façon déterministe** (âge + entretien sauté). La
« jauge de risque de panne » est le **même patron que la jauge moisissures** :
elle monte au fil des jours, la panne se déclenche **au passage d'un seuil
visible** → le joueur la voit venir et agit. Aucune probabilité cachée, cohérent
avec le moteur semé. Durées de vie de référence sourcées (ADEME, à revérifier) :
chaudière fioul ~15-20 ans, PAC ~17 ans, panneaux ~30 ans, batterie ~10-15 ans.

**Entretien = action manuelle (mini-travaux).** Déclenché à la main, avec un
**rappel UI « c'est le moment »** à l'échéance :
- **Obligation réglementaire réelle et différenciée** (corrige l'idée « tout type
  de chaudière ») : fioul/gaz/bois 4-400 kW = **annuel** (décret 2009-649,
  consolidé décret 2020-912) ; PAC 4-70 kW = **tous les 2 ans** (décret 2020-912) ;
  **électrique pur (effet Joule) = aucune obligation** (pas de combustion) ;
  panneaux/batterie ≈ 0.
- **Coût** ~150 € (fourchette 150-200 €/an, ADEME) — ferme.
- **Délai** ~1 semaine : ⚠️ **pas de source dure**, ordre de grandeur (prise de
  RDV, visite ~1 h) — à assumer comme tel, pas à maquiller en chiffre sourcé.
- **Effet** : sauter l'entretien **accélère la décroissance déterministe de
  santé → rapproche la panne**. ⚠️ **Discipline §13** : on ne code PAS un
  « −X % de risque » (magnitude **non sourçable**). On modélise « l'entretien
  retarde la panne / prolonge la vie » (direction sourçable ADEME) →
  concrètement, l'entretien **repousse la date de panne déterministe de N
  jours**, pas une probabilité. C'est ce qui garde le système honnête ET semé.

**Batterie : usure = capacité, pas panne.** L'autodécharge (~1-3 %/mois) reste
ignorée (négligeable au tick journalier, la batterie se vide chaque nuit). La
**perte de capacité** (~2-3 %/an, garanties typiques 70-80 % après 10 ans)
s'intègre au même système comme **« santé = capacité résiduelle »** (dégradation
continue, pas seuil de panne) — elle nourrit le mauvais ROI de la batterie seule
(retour 15-20+ ans, §8), fidèle au principe « ne jamais forcer un ROI positif ».

**Lien ROI (§8).** Les durées de vie sourcées deviennent **nécessaires** dès
l'écran de ROI : le §8 exige de montrer qu'un retour > 40 ans **dépasse la durée
de vie** des équipements. Tout passe par des `Coefficient` sourcés (décrets +
ADEME) ; l'entretien devient une **ligne de charge périodique** (l'écart
d'entretien fioul/PAC fait partie de la comparaison honnête §13, même s'il est
faible ~150 € devant l'écart de facture énergie ~3 000 €/an).

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

## Arbre de travaux — rénovation détaillée (spec design, juillet 2026)

**Design validé, spec écrite : `docs/specs/2026-07-15-arbre-travaux-design.md`**
(branche `docs/arbre-travaux-spec`). Première extension **post-MVP (V1.x)** ;
le MVP Phase 0-1 reste verrouillé tant que la phase n'est pas ouverte.

> **État d'avancement (juillet 2026)** — **Tranches 1 à 3 IMPLÉMENTÉES.**
>
> - **T1 — enveloppe par surfaces** (`docs/specs/2026-07-16-tranche1-enveloppe-plan.md`) :
>   `InsulationLevel` (3 paliers) → `EnvelopeState` (combles / murs ITI-ITE /
>   vitrage) ; déperdition agrégée (facteur `1 − Σ retraits` sourcés, planché
>   0,15) ; 4 travaux chiffrés+câblés ; DPE inchangé ; départ F/G préservé ;
>   plafond enveloppe seule ≈ 0,50 (assumé). 233 tests, « ready to merge ».
> - **T2+T3 — conseils + tiroir** (`docs/specs/2026-07-16-tranche2-3-conseils-tiroir-plan.md`) :
>   `RenovationAdvisor` (domaine pur, **non prescriptif** — 2 niveaux 💡 repère /
>   ⚠ déconseillé-maintenant, pas de ★ ni halo, choix préservés ; seuil 0,70 de
>   « peu isolée » en calibration de jeu) ; badge de conseil sur `QuoteCard` +
>   icônes des 4 travaux ; **tiroir latéral scrollable** (`.at-drawer`, règle le
>   débordement) + séparation lire/agir + bande « ✔ fait » ; note éducative
>   statique. 246 tests, « ready to merge ». Née d'un retour joueur : panneau de
>   coin qui débordait, fouillis, pas de pédagogie.
>
> - **T4 — chauffage complet** (`docs/specs/2026-07-17-tranche4-chauffage-plan.md`) :
>   **émetteurs basse température** → le SCOP de la PAC dépend d'eux (2,5 fonte /
>   4,3 BT), *seule la PAC sensible* (fioul/granulés insensibles) ; **chaudière
>   à granulés** = 3ᵉ vecteur pellet (kg, prix, CO₂ ~30 g/kWh) traversant
>   facture/totaux/CO₂/DPE — « facture 2 lignes » préservée à l'œil (fioul et
>   granulés exclusifs) ; 2 travaux chiffrés+câblés + conseils non prescriptifs ;
>   tiroir chauffage lire/agir. Bascule SCOP assumée (PAC seule = 2,5). 270 tests.
>
> - **T5 — ventilation + production + ECS** (`docs/specs/2026-07-17-tranche5-ventilation-production-ecs-plan.md`) :
>   **VMC double flux** = 4ᵉ surface d'enveloppe (récupère la chaleur, −0,14 ;
>   rouvre le chemin sous 0,50) + conseil « à poser après avoir isolé » ; **kit
>   solaire plug-and-play** (0,9 kWc, 800 €, sans installateur ni aide ; upgrade
>   vers l'installation complète) ; **chauffe-eau thermodynamique** = découpe de
>   l'ECS (réduit la demande, défaut ballon élec non régressif). 3 travaux
>   câblés + conseils ; lire/agir des slots roof/garage comblé. 291 tests.
>
> - **T6 — gestes du quotidien** (`docs/specs/2026-07-17-tranche6-gestes-plan.md`) :
>   **calfeutrage** (−0,04 perte, 80 €) + **rideaux thermiques** (−0,02 paroi
>   froide, 120 €) sur `EnvelopeState`, **non subventionnés**. Conseils Info
>   **anti-théâtre** (« geste bon marché… pas un gros levier ») — les magnitudes
>   sont honnêtement petites vs les gros travaux. Zone séjour (dernier lire/agir
>   comblé). 300 tests.
>
> - **T7 — visuels de scène par travail** (plan
>   `2026-07-17-tranche7-visuels-scene-plan.md`, branche `feat/scene-visuals`) :
>   `HouseSceneView` passe d'un palier d'isolation grossier (0/1/2) à un modèle
>   **par surface** (combles / murs ITI vs ITE / vitrage / VMC / rideaux), et
>   chaque travail obtient son rendu — dont le **contraste ITI/ITE** (seule
>   l'ITE change la façade), la **chaudière granulés + silo**, le **kit solaire
>   AU SOL** (jamais sur le toit : c'est ce qui définit le plug-and-play) et le
>   **ballon thermodynamique** dans le garage. La neige de toit est désormais
>   liée aux **seuls combles**, et la cheminée fume pour **toute combustion**
>   (le pellet fume aussi). 301 tests.
>   **Exceptions assumées (aucun visuel)** : **calfeutrage** (des joints ne se
>   voient pas) et **émetteurs basse température** (hors coupe). Le **ballon
>   électrique** d'origine reste invisible : c'est l'équipement de départ, le
>   dessiner prétendrait que le joueur a agi.
>
> **✅ ARBRE RESSERRÉ COMPLET (T1 → T7).** Le premier arbre de travaux
> (game-design / spec `2026-07-15-arbre-travaux-design.md`) est entièrement
> implémenté, et chaque travail se voit sur la scène.
>
> **Pistes futures (backlog, hors arbre resserré)** : **carbone gris +
> hiérarchie des leviers** (voir « Consommation par usage » ci-dessous) ;
> conso par usage (électroménager/veille) ; **T2 dynamisme** (prix variables,
> politiques + points d'arrêt) ; réalisme financier dans le temps (délais/dette,
> voir remarque éco-PTZ) ; type d'isolant (Phase 5) ; VMC simple flux ;
> distinction visuelle `glazingMaxed` ; **raffinement des assets de scène** (les
> SVG posés en T7 sont fonctionnels et lisibles, pas de l'illustration soignée —
> ils peuvent être remplacés un par un sans toucher au modèle sémantique).

Répond à la mollesse structurelle du gameplay (décisions one-shot, milieu
d'année vide) en **multipliant les choix de travaux**, avec pour **rôle premier
la pédagogie de la rénovation** (le vrai parcours ADEME, pas un gating de jeu).

- Mécanisme **« conseil non bloquant »** : aucun verrou ni malus artificiel ;
  leçons montrées par le système (factures/SCOP/confort qui bougent) ou conseil
  textuel (encarts 💡/⚠).
- Contenu resserré (~une douzaine de nœuds / 5 branches) : enveloppe **par
  surfaces** (combles, murs **ITI/ITE**, vitrage), ventilation (VMC), chauffage
  (PAC, granulés, **émetteurs BT → SCOP** — levier spécifique PAC, combustion
  insensible), production & ECS (PV **kit plug-and-play**/complet, batterie,
  chauffe-eau thermo), **gestes** (rideaux, calfeutrage).
- IHM : **zones de scène = branches**, séparation *lire* (4 coins) / *agir*
  (zones), **tiroir latéral** pour les travaux, entêtes de contexte-décision.
- Impact domaine : `Household` par surfaces, DPE recalculé, `RenovationAdvisor`
  (moteur du conseil). Aides à périmètre inchangé. Tous coefficients à sourcer.

**Déclencheur** : ouverture consciente de la phase V1.x → plan d'implémentation
(`writing-plans`). **Croise** la section « Confort d'été & rénovation
granulaire » ci-dessus : le **type d'isolant** (laine vs biosourcé) est
volontairement **différé à la Phase 5** dans la spec, car son intérêt (déphasage
/ confort d'été) n'a de sens qu'une fois les canicules modélisées — pas de
magnitude truquée (§1).

## Consommation par usage — itemiser la demande électrique (piste, réflexion juillet 2026)

**Déclencheur/amorce : la Tranche 5 casse déjà l'ECS (eau chaude) de la demande
de base** (`householdDailyBaseDemandKwh` → appareils + ECS, avec COP du
chauffe-eau). C'est la première fissure dans le bloc de conso forfaitaire.
Une fois les usages itemisés, ça débloque une famille de mécaniques (idée
joueur) :
- **efficacité des équipements** : électroménager (A→A+++), éclairage LED,
  chauffe-eau (déjà fait) → travaux/gestes bon marché, sourcés (étiquette
  énergie, ADEME) ;
- **sobriété / usages** : veille des appareils, délestage, **décalage des
  usages** (lave-linge en heures creuses / au pic solaire) → le levier
  *comportement*, distinct du levier *équipement* (« consommer moins » vs
  « consommer mieux ») ;
- se marie avec le **prix variable** (HP/HC, autoconsommation) de la phase
  « dynamisme » ci-dessous, et avec la batterie / le pilotage.
Non planifié : à raccrocher à la phase « dynamisme » ou à une extension de
l'arbre côté équipements électriques.

**Deux exigences pédagogiques (réflexion joueur, juillet 2026) pour que ce soit
honnête et pas du théâtre — s'appliquent à TOUT l'arbre, pas qu'aux usages :**

- **Carbone gris (fabrication) + amortissement carbone.** Aujourd'hui l'axe CO₂
  (`CarbonAccountant`) ne compte que le carbone *à l'usage*. Or fabriquer un
  équipement a un coût carbone (énergie grise) parfois non négligeable vs
  l'économie : garder un appareil qui marche peut battre le remplacer ; une PAC,
  des panneaux, une VMC ont une **dette carbone de fabrication** → un **temps de
  retour carbone** (parfois long, parfois jamais atteint). Transforme « verdir »
  en *investissement carbone avec amortissement*. Sourçable (ADEME / Base
  Carbone : contenu carbone de fabrication par équipement). Reframe l'axe
  Environnement en **CO₂ usage + CO₂ fabrication**. Croise [[environnement-co2]].
- **Ordres de grandeur / hiérarchie des leviers.** Personne n'a les magnitudes en
  tête → on fait des gestes contraignants et minuscules (éteindre la box ~17
  kWh/an) en ratant le gros levier (chauffage = milliers de kWh/an ; avion =
  centaines de kg d'un coup). Rendre les impacts **comparables sur une échelle
  commune** (kgCO₂/an, €/an) et **classer les leviers** pour que le joueur voie
  que l'isolation écrase le geste — et qu'un petit geste **ne compense jamais**
  un mauvais gros arbitrage. Le jeu a déjà la matière (effets « ≈ X €/an », Δ
  DPE, CO₂ vécu) ; il manque la **mise en contraste explicite**.
- **Impact immédiat, dès les gestes (T6)** : présenter rideaux/calfeutrage
  **honnêtement** (petit gain de confort, pas un gros levier) — ne pas les
  survendre dans les conseils. C'est l'anti-théâtre appliqué tout de suite.

## Dynamisme du gameplay — rythme de décision (phase dédiée, réflexion juillet 2026)

Thème **frère** de l'arbre de travaux ci-dessus : deux réponses au **même**
problème (le gameplay MVP est **structurellement mou**). L'arbre attaque la
**largeur** des choix ; cette phase-ci attaquerait le **rythme** — le milieu
d'année vide. À traiter en **phase dédiée**, pas au premier arbre. Non planifié.

**Diagnostic (à garder en tête).** La mollesse est *structurelle*, pas un manque
de contenu : les décisions sont *one-shot* et front-loadées, aucune boucle
récurrente ne porte le milieu de partie. Et le **tick temps réel** (même en ×1,
a fortiori ×3 à 4 s/jour) rend le **micro-management impossible** — mais « régler
le thermostat une fois pour toutes » est tout aussi plat. La prévision météo
comme unique levier de micro-gestion a été jugée **trop tendue** dans ce cadre.

**Modèle de design retenu de la réflexion — politiques + points d'arrêt** (pas de
clic-par-tick) :
- le joueur pose des **règles automatiques** (politiques) qui tournent seules ;
- le jeu **s'auto-met en pause** aux moments qui comptent : événement, alerte,
  franchissement de seuil, **bilan mensuel** ;
- un bouton **« avancer jusqu'au prochain point de décision »** ;
- garde-fou : des **politiques avec surcharge ponctuelle**, jamais des clics
  quotidiens obligatoires. (Cohérent avec `TimeProgressionPolicy` /
  `PausesWhileAway` déjà posé, et avec la pause auto au matin de la panne.)

**Pistes de profondeur inventoriées** (sans trahir l'ADN : réalisme sourcé,
multi-critères, pas de game over, pédagogie par les systèmes) :
- **Prix variables de l'énergie** — HP/HC, Tempo, volatilité du fioul → donne du
  sens au pilotage/stockage et à l'autoconsommation dans le temps.
- **Calendrier d'événements** — pannes, aléas, échéances (au-delà de l'unique
  panne scriptée actuelle) : la trame qui remplit l'année.
- **Objectifs souples** — jamais un score unique (§1) ; des caps/paliers par axe
  qui donnent une direction sans « gagner/perdre ».
- **Programmation du thermostat** — cf. entrée « Face gameplay du levier 2 —
  programmation du thermostat » plus haut dans ce backlog ; c'est la première
  brique concrète de « politique » (consignes horaires/saisonnières).
- **Modèle d'inertie / intermittence** — l'inertie thermique et l'intermittence
  PV/vent rendent le *timing* des décisions signifiant (prérequis d'une partie
  des points ci-dessus).
- **Réalisme financier dans le temps** — délais de versement de l'éco-PTZ,
  entretien récurrent, cycle de vie des équipements (croise la section « Cycle
  de vie des équipements » de ce backlog).
- **Persistance + graphe multi-années** — voir courber ses axes sur plusieurs
  années (prérequis : étape « Persistance & méta-jeu »).
- **Confort d'été / canicules** → **Phase 5** (hors scope ici, cf. « Confort
  d'été & rénovation granulaire »).

**Déclencheur** : jalon dédié « dynamisme du gameplay », après (ou en parallèle
de) l'arbre de travaux — les deux se renforcent : les **gestes bon marché** de
l'arbre créent déjà un séquençage par le budget qui préfigure la boucle
récurrente visée ici.

## Confort thermique : représentation + consigne réglable (réflexion joueur, juillet 2026)

Constat : le confort n'est qu'un %, peu lisible, et surtout **sans conséquence
mécanique** — donc dominé par l'argent. Le vrai problème est le même que la
panne avant l'appoint forcé : baisser le chauffage économise sans coût réel.
Deux résolutions, à mener DANS CET ORDRE.

- ~~Rendre le confort VISIBLE~~ : **fait** — occupant SVG plein corps dans le
  séjour, 4 états pilotés par le ressenti `feltC` (transi < 14 · frileux
  14-18 · à l'aise 18-25 · en sueur > 25 °C), teinte du séjour assortie, clic =
  panneau confort. Reste éventuel : radiateur chaud/froid animé, boîtier
  thermostat mural. Le détail sourcé conservé ci-dessous pour référence.
  **Point clé sourcé** : le confort dépend de la température
  OPÉRATIVE (air + parois), pas de l'air seul → 19 °C n'est PAS une solution
  unique. En passoire, 19 °C d'air ≈ 16 °C ressenti (parois froides, déjà
  modélisées) ; rénové, 19 °C d'air ≈ 18-19 °C ressenti. Le DPE devient donc un
  déterminant du confort, gratuitement. Sources : OMS *Housing and Health
  Guidelines* 2018 (mini 18 °C, 20 °C vulnérables) ; ADEME (19 °C pièces de
  vie, +1 °C ≈ +7 % conso) ; Code de l'énergie R241-26 (plafond 19 °C) ;
  EN 16798-1 / ASHRAE 55 (confort adaptatif, plage saisonnière, jusqu'à
  ~26-28 °C l'été — lien §16).
- ~~Indicateur de précarité énergétique~~ : **fait** — taux d'effort
  énergétique (coût énergie annuel estimé / revenu annuel) affiché dans le
  panneau, badge « ⚠️ Précarité énergétique · N % » dans le HUD au-delà du
  seuil ONPE **8 %** (`FinanceCalibration::fuelPovertyEffortThreshold`, loi
  Grenelle II). Passoire ~12 % → en précarité ; rénové ~2 % → sorti. Autres
  indicateurs officiels si besoin un jour : BRDE (bas revenus / dépenses
  élevées), froid ressenti, restriction.
- **Éco-gestes : la couche de confort bon marché (V1.x)**. Réponse gameplay à
  « comment améliorer mon ressenti sans trop dépenser ? » — un palier de
  micro-décisions à quelques dizaines d'euros ENTRE « ne rien faire » et
  « rénover », qui augmentent le ressenti par euro en réduisant l'effet parois
  froides / les infiltrations. **Discipline obligatoire (§13)** : chaque geste
  = un `Coefficient` sourcé + fourchette, avec une magnitude honnête « du
  ridicule au majeur ». Classement indicatif (à vérifier sur sources primaires
  avant de coder ; sources de départ : éco-gestes ADEME) :

  | Geste | Coût | Impact confort | Nature |
  |---|---|---|---|
  | Calfeutrage / joints (infiltrations) | ~qques € | **majeur** en passoire | micro-geste |
  | Changement fenêtres (double/triple) | cher | majeur (radiatif + air) | gros travaux (cf. §16) |
  | Rideaux épais / volets la nuit | faible | modéré | micro-geste |
  | Réflecteurs derrière radiateurs | très faible | mineur-modéré | micro-geste |
  | Tapis sur sol froid | faible | mineur | micro-geste |
  | Brasseur d'air (déstratification) | moyen | **mineur** plafond std / modéré été | double-saison |
  | Boudins de porte, agencement | ~0 | mineur | micro-geste |

  Le **brasseur d'air** : concept réel (déstratification — repousse l'air chaud
  du plafond vers l'occupant), mais gain hiver **faible** sous plafond standard
  (2,5 m), notable seulement sous hauts plafonds ; son vrai intérêt est l'ÉTÉ
  (brise, ressenti −2-3 °C, §16). Ne pas le survendre l'hiver. Enseigne que
  confort ≠ juste température, et que la passoire pénalise le
  **confort-par-euro** (le calfeutrage à 15 € peut battre un geste « sérieux »).
  Nuance de design : l'**habillement** (pull/plaid) est un **signe d'inconfort**
  montré par l'occupant, PAS un fix gratuit — sinon « mets un manteau »
  annulerait la pénalité. Déjà en jeu : l'isolation augmente déjà le ressenti
  de façon disproportionnée (facteur paroi 0,15 → 0,03) ; les éco-gestes en
  sont la déclinaison granulaire.
- ~~Consigne de chauffe réglable~~ : **fait** — thermostat cliquable (boîtier
  mural + panneau +/−, plage 16-23 °C), consigne sur `Household`, besoin de
  chauffage et confort qui la lisent (base DJU = consigne − apports gratuits,
  ~+7 %/°C ADEME), **prévision live** ±1 °C sur la facture annuelle (via
  l'estimateur), avertissement sous 18 °C (plancher OMS), occupant qui réagit.
  L'arbitrage est rendu VISIBLE (occupant + confort + précarité) — l'anti-abus
  d'interim. **La dent financière du froid reste à venir** avec le système
  moisissures ci-dessous (décision maintenue : les moisissures ajoutent la
  conséquence physique du sous-chauffage). L'anti-abus réaliste et élégant :
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
- **Précarité énergétique ≠ insalubrité : deux cadres distincts, ne pas les
  confondre**. La **précarité énergétique** (indicateur TEE ci-dessus) est
  ÉCONOMIQUE/thermique : « le ménage peut-il payer une chaleur suffisante ? »
  (suivi ONPE, loi Grenelle II) — ne dit rien de l'état physique du logement.
  L'**insalubrité / non-décence / habitat indigne** est SANITAIRE et légale :
  « le logement est-il vivable ? » (décret décence 2002-120 : humidité/
  moisissures = non-décent ; Code de la santé publique, ordonnance 2020-1144
  « habitat indigne » ; loi MOLLE 2009). Un logement peut être insalubre quel
  que soit le revenu de l'occupant. **Deux mécaniques distinctes dans le jeu** :
  (1) un **indicateur de précarité** = un % économique, faisable maintenant ;
  (2) l'**insalubrité = un ÉTAT du logement** piloté par la moisissure (étape
  future). Le pont : la moisissure est le moteur SANITAIRE de la non-décence,
  le DPE le moteur ÉNERGÉTIQUE (loi Climat & Résilience 2021 : passoire
  progressivement interdite à la location) — les deux peuvent rendre le
  logement « non-décent / indigne », par des chemins séparés.
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
- ~~**Phase dupliquée dans la calibration**~~ : fait (juillet 2026).
  `EnergyCalibration::householdDemandPeakDayOfYear` **dérive** désormais de
  `WeatherCalibration::coldestDayOfYear` (source unique de vérité, plus de
  risque de dérive).

- **Coût du rendu du tableau de bord — 91 ms par affichage** (mesuré, juillet
  2026). `GameViewFactory::build()` prend **90,9 ms**, parce que
  `AnnualOutcomeEstimator::estimate()` **simule 365 jours** et qu'il est appelé
  1× pour l'état courant, 2× pour l'aperçu du thermostat, et **1× par travail
  disponible** (13 aujourd'hui) — soit ~5 000 jours simulés par rendu, rejoués
  **toutes les 4 secondes** par le `data-poll`, que les tiroirs soient ouverts
  ou non. Sans conséquence en solo local ; ça sature ~2,5 cœurs à 100 joueurs.
  Deux leviers, du plus simple au plus structurant : (a) ne coter que les
  travaux du tiroir **ouvert** ; (b) mémoïser l'estimation par `Household`
  (l'estimateur est pur et déterministe — même maison, même résultat, et le
  parc de configurations visitées dans une partie est petit). Le **catalogue de
  travaux** (`docs/specs/2026-07-18-catalogue-travaux-design.md`) rend (a)
  trivial mais ne le fait pas : chantiers orthogonaux.

## Persistance & méta-jeu (étape dédiée, décidée en bloc)

**Décision actée (juillet 2026)** : la persistance Doctrine **attend une étape
dédiée**, pensée en bloc avec toute son UX — pas un simple portage session→DB.
Raisonnement (joueur) : copier la session en base est de la **plomberie
invisible** qui ne sert pas le gameplay ; quitte à persister, il faut **tout ce
qui va autour** pour que ça vaille le coup. À faire ensemble, comme une vraie
feature de méta-jeu :

- **Comptes / login** (le seul cas où Symfony Security se justifie enfin :
  portabilité multi-appareils, vraie identité — cf. la mise au point ci-dessous).
- **Reprendre une partie** : boutons explicites (« Continuer », « Nouvelle
  partie »), plus la reprise implicite au chargement.
- **Parties multiples en parallèle** (plusieurs foyers/scénarios sauvegardés).
- **Historique** : liste des parties terminées avec leur bilan (comparer ses
  runs — pédagogie).
- **Stats** : agrégats inter-parties (progression, meilleurs bilans par axe…).

**Point d'honnêteté à ne pas réoublier** : un cookie anonyme n'apporte **pas
plus d'identité** que la session actuelle (elle est déjà keyée par cookie). La
vraie plus-value de la persistance = (1) **durabilité** (la session PHP expire /
meurt à la fermeture du navigateur, or le jeu se joue sur plusieurs jours réels
via `PausesWhileAway` → une partie longue s'évapore), (2) **historique**
(la session ne tient qu'une partie), (3) **cycle de vie des données** (sauvegarde,
migration, stats, socle des phases suivantes). Si un jour on ne veut QUE la
durabilité sans le reste, un chemin cheap existe (session persistée en base via
`PdoSessionHandler` + cookie longue durée, ~config seule) — mais c'est un
demi-pas qui n'apporte ni historique ni modèle de données.

**Terrain déjà prêt** (le gros du travail technique est fait) : `GameStore` est
une **interface** (présentation découplée), la (dé)sérialisation `Game ⇄ array de
primitifs` existe dans `SessionGameStore` (`dehydrate`/`hydrate` + `FORMAT_VERSION`,
extractible vers un `GameSnapshot` partagé), Doctrine est configuré (Postgres
prod / SQLite `_test`), `src/Entity/` est vierge. Un `DoctrineGameStore` stockera
le même snapshot en JSON versionné, keyé par l'identité choisie. Déclencheur :
l'étape méta-jeu dédiée, quand le contenu justifie de sauver/comparer des parties.

**Payoff gameplay qui justifie la persistance — le graphe conso/CO₂ multi-années
avec marqueurs de travaux** (décidé juillet 2026). La consommation (et le CO₂)
dépend de l'**historique d'équipement** — contrairement à la météo (recalculable
car semée), elle **doit être stockée** point par point. Feature cible : une
timeline de la conso (kWh/m²/an) et des émissions (kgCO₂/an) sur plusieurs
années, avec un **marqueur à chaque travaux** (« isolation → −30 % », « PAC →
−encore, et le CO₂ s'effondre »). C'est *le* retour visuel qui rend tangible
l'effet cumulé des décisions — et c'est exactement le genre de contenu qui rend
la persistance utile plutôt qu'invisible. Dans une seule année, une version
réduite est possible en stockant la série en session (~365 points) ; le
multi-années exige la vraie persistance. À concevoir avec l'axe Environnement
(la facette climat du DPE + l'intensité conso sont posées côté MVP — voir
ci-dessous « Environnement / CO₂ »).

## Environnement / CO₂

**Fait (MVP, juillet 2026).** L'axe Environnement est posé au sens *empreinte
vécue* :
- **Facette climat du DPE** — double étiquette officielle (énergie kWhEP/m²/an +
  climat kgCO₂/m²/an, classe finale = la pire des deux), rendu officiel dans le
  coin Patrimoine (`DpeCertifier`, `DpeAssessment`).
- **Compteur d'émissions réelles cumulées** — `CarbonAccountant::emittedKg(fioul,
  import réseau)` : le CO₂ *réellement* mis dans l'air depuis le jour 1, distinct
  de la note DPE (le solaire autoconsommé n'émet rien → seul l'import réseau
  compte). Affiché dans le coin Énergie & climat, détaillé dans son panneau
  (« empreinte réelle » vs « empreinte du logement ») et repris au bilan de fin.
  Facteurs sourcés (arrêté DPE 2021 : fioul 324 g/kWh, élec 79 g/kWh) partagés
  avec l'étiquette climat pour une histoire cohérente.

**Reste à faire.**
- **Graphe conso/CO₂ multi-années** avec marqueurs de travaux — le vrai payoff,
  mais il exige la persistance (voir « Persistance & méta-jeu » ci-dessus). Le
  `CarbonAccountant` fournit déjà le point d'émission annuel ; ne manque que le
  stockage de la série.
- **Affiner le facteur électricité** — le compteur réutilise le facteur *DPE
  conventionnel* (79 g/kWh) pour rester cohérent avec l'étiquette. Un facteur
  *consommation ADEME Base Carbone* (~60 g) serait plus juste pour l'empreinte
  vécue ; à trancher (deux facteurs = deux sources, plus de complexité) le jour
  où le contenu carbone horaire du réseau entrera en jeu (cf. entrée
  « inertie / intermittence » : CO₂ selon le mix horaire).
- **Canicule = PAS ici.** La gestion des canicules est du **confort d'été**
  (Phase 5 / §16), pas de l'empreinte : c'est l'impact du climat sur le confort,
  pas l'inverse. Elle a son propre jalon (« V1.x confort d'été » + générateur de
  canicules) et son prérequis physique (modèle de confort estival, aujourd'hui
  hiver-only). La boucle pédagogique « émettre → réchauffer → plus de canicules »
  est du climat long terme, hors scope MVP/V1 — fil narratif, pas mécanique.
