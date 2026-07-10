# EcoSim — Document de synthèse (Game Design Document)

Jeu de simulation web (Symfony UX, LiveComponents + data-poll) sur la gestion des ressources
énergétiques et la transition écologique. Objectif : sensibilisation pédagogique par le jeu,
avec un socle scientifique aussi rigoureux que possible, tout en restant jouable et non punitif.

---

## 1. Principes directeurs

- **Équilibre 50/50 fun / pédagogie.** Le réalisme prime sur l'équilibrage classique de jeu :
  on ne truque jamais un ordre de grandeur pour "équilibrer" des choix qui n'ont pas le même
  impact dans la réalité. Le levier de gameplay est le **coût d'accès** (prix, délai, prérequis),
  jamais la magnitude de l'effet réel.
- **Aucune donnée sans source.** Tout coefficient de jeu doit être traçable à une source
  citable (ADEME, RTE, GIEC, IEA, Notaires de France, études peer-reviewed...). Un registre
  central de calibration (`ImpactCoefficient`) sert de source de vérité unique, avec fourchettes
  d'incertitude plutôt que valeurs figées quand la littérature diverge.
- **Un seul moteur, plusieurs échelles.** Le même moteur physique/économique est réutilisé aux
  3 échelles de jeu (foyer / ville / pays), avec une granularité de simulation croissante
  (individuelle → agrégée par archétypes de population).
- **Complexité croissante avec l'échelle.** Le nombre de leviers/arbitrages augmente du foyer
  vers le pays — le foyer sert de tutoriel simple, le pays est le palier "expert".
- **Rythme casual/asynchrone.** Pas de temps réel façon SimCity. Le joueur définit des
  **politiques/règles automatiques** appliquées par le moteur en son absence, et revient
  consulter un **rapport de période** (façon bilan RTE / Santé Publique France) plutôt que de
  réagir en direct à chaque événement.
- **Pas de couperet arbitraire.** Aucun game over sur un seuil scientifique franchi (ex:
  dépassement d'une trajectoire climatique). Les défaites émergent de l'effondrement systémique
  cumulé (réseau, finances, satisfaction), toujours comprises et anticipables par le joueur.
- **Multi-critères, jamais un score unique.** CO2, coût, confort, patrimoine, satisfaction
  restent des axes séparés et affichés séparément — sommer des grandeurs de nature différente
  (liquide/potentiel/non-monétaire) donnerait une fausse précision.

---

## 2. Échelles de jeu

| Échelle | Rôle | Granularité |
|---|---|---|
| **Foyer** | Tutoriel/onboarding, appropriation individuelle | Simulation individuelle fine |
| **Ville** | Cœur du jeu, contenu principal | Simulation spatiale (grille) |
| **Pays** | Palier expert / fin de jeu, enjeux systémiques et politiques | Simulation statistique par archétypes de population |

Le joueur peut switcher entre échelles selon le scénario. Les décisions nationales (fiabilité du
réseau, politiques tarifaires) redescendent vers le foyer sous forme de signaux (voir §9).

---

## 3. Sources d'énergie — modèle générique

### Entité `EnergySourceType` (catalogue statique, un type = une fiche technique)

Catégories : `RenewableIntermittent` (éolien, solaire), `RenewableDispatchable` (hydraulique,
biomasse), `LowCarbonNonRenewable` (nucléaire), `Fossil` (charbon, gaz, fioul), `Storage`
(batteries, STEP).

Statuts de disponibilité technologique : `Legacy` (ancien modèle) / `Commercial` (standard
actuel) / `NearFuture` (R&D avancée, ~5-10 ans, ex. projections NREL 2030) / `Research`
(prototype non commercialisable, ex. fusion, éolien aéroporté).

**Champs / critères communs (mêmes pour toutes les sources, pour rester comparables) :**

- **Production** : puissance nominale, courbe de puissance, facteur de charge moyen, puissance
  minimale technique (pilotable ou non), vitesse de rampe (MW/min)
- **Planification** : délai de permitting, délai de construction, délai de **démantèlement**
  (site occupé/non productif pendant le démantèlement), unité minimale déployable
- **Économique** : capex/kW, opex annuel (%), coût du combustible (nul pour renouvelables),
  durée de vie, coût de démantèlement
- **Environnemental** : empreinte carbone cycle de vie (fourchette + source), temps de retour
  énergétique, recyclabilité, consommation d'eau (refroidissement)
- **Spatial** : emprise au sol, distance minimale aux habitations, contrainte géographique
  (tags de terrain requis)
- **Nuisances** : bruit, impact visuel, impact biodiversité
- **Techno** : prérequis de recherche (arbre technologique), `technicalParameters` en **JSON
  libre** (cube du vent pour l'éolien, angle solaire pour le PV, etc. — interprété par un
  `PowerCalculatorInterface` dédié par catégorie)

### Arbre technologique éolien terrestre (exemple détaillé, modèle à transposer)

| Palier | Puissance | Facteur de charge | Statut |
|---|---|---|---|
| Micro résidentiel | 1-10 kW | ~10-15% | Commercial (niche, décevant vs PV résidentiel) |
| Legacy 2000s | 660 kW - 2 MW | ~25-31% | Legacy |
| Workhorse actuel | 2,8-3,8 MW | 33,5% (parc), jusqu'à 38,2% (récent) | Commercial |
| Grand gabarit | 4-6 MW | jusqu'à 40%+ | Commercial (contrainte transport des pales) |
| Proche futur | 3,3-8,3 MW | — | NearFuture (projections NREL 2030) |
| Recherche | variable | — | Research (aéroporté, sans pales, VAWT nouvelle génération) |

Coût 2023 ≈ 1 694 $/kW (-71% depuis 1983), LCOE ≈ 49 $/MWh. Carbone cycle de vie ≈ 5-8 gCO2/kWh
(parcs optimisés), fourchette large selon méthodo (3,3-70 gCO2/kWh) — **toujours afficher en
fourchette sourcée**, jamais un chiffre unique figé.

---

## 4. Carte et terrain

- **Grille de tuiles fixes** (façon Civilization/SimCity).
- **Génération procédurale par seed** (bruit de Perlin/Simplex, plusieurs octaves) →
  déterministe : même seed = même carte, ce qui permet des **scénarios reproductibles** (seed
  fixé en dur) à côté du mode bac à sable (seed aléatoire).
- **Archétypes de carte** (`MapArchetype` : Plains, Mountain, Coastal, Island, Valley) obtenus
  en combinant le même bruit de base avec un **masque de forme macro** (`ShapeMaskInterface` :
  radial pour une île, linéaire pour du littoral, corridor pour une vallée).
- **`TerrainTile`** : altitude, type de terrain, `windExposureFactor` et `solarExposureFactor`
  (dérivés du relief local vs voisinage), rugosité, constructibilité, distance aux habitations.
- Possibilité de **cartes 100% écrites à la main** via un `FixtureMapSource` (interface
  `MapSourceInterface` commune avec `ProceduralMapSource`), pour des scénarios très spécifiques.
- **Effet de sillage** entre éoliennes proches (simplification du modèle de Jensen) : vrai
  dilemme de densité de placement.

---

## 5. Moteur météo (3 couches temporelles empilées)

```
Climat long terme → paramètre les moyennes/probabilités
Saisonnalité      → paramètre les cycles/sinusoïdes
Météo instantanée → bruit court terme + événements
```

### Couche 1 — instantanée (par tick)
Vent (vitesse + direction), nébulosité, irradiance (dérivée), température, précipitations.
**Variable pivot recommandée : la pression atmosphérique**, pour corréler vent/nébulosité de
façon réaliste et générer le scénario le plus dangereux pour un réseau renouvelable :
l'anticyclone hivernal (froid + ciel dégagé + peu de vent = pic de demande + faible production).
Meilleur rapport réalisme/gameplay de toute la couche.

### Couche 2 — saisonnalité
Sinusoïdes peu coûteuses : angle solaire selon latitude/jour de l'année, vent moyen saisonnier,
température moyenne saisonnière, débit hydraulique (fonte/étiage), courbe de demande
saisonnière (chauffage hiver / clim été). **Priorité haute pour le MVP** (faible coût, fort
rendement pédagogique).

### Couche 3 — climat long terme
Réchauffement progressif (avec **inertie thermique**, jamais de baisse nette de température à
l'échelle d'une partie, même à zéro émission — fidèle à la physique réelle), fréquence
d'événements extrêmes croissante, montée du niveau de la mer, sécheresse structurelle,
variabilité accrue, seuils de bascule irréversibles. **V2, pas MVP.**

---

## 6. Modèle climatique

- `ClimateModel` : bilan radiatif simplifié avec inertie thermique (temperature "court après"
  le forçage radiatif, jamais instantanée) — inspiré des modèles simplifiés type FaIR/MAGICC.
- **Scénarios GIEC (SSP) comme courbes de référence en fond de graphique**, pas comme rails
  fixes : SSP1-1.9 (~1,4°C, objectif Paris), SSP1-2.6 (~1,8°C), SSP2-4.5 (~2,7°C, "statu quo"),
  SSP3-7.0 (~3,6°C), SSP5-8.5 (~4,4°C, pire cas).
- **Projection contrefactuelle mondiale** (`CounterfactualWorldProjector`) : "si le monde entier
  suivait ta trajectoire d'émissions par habitant" — plus honnête que prétendre qu'une ville
  pilote le climat mondial, tout en donnant une vraie réponse à "l'impact de mes choix généralisés".
- **Pas de game over sur seuil climatique.** Alertes progressives (badge "trajectoire
  catastrophe" au-delà de SSP3-7.0/5-8.5) qui augmentent le multiplicateur d'événements extrêmes,
  sans jamais être un couperet direct.
- Seuils de bascule (tipping points) : au-delà d'une anomalie donnée, effet permanent
  (fréquence d'événements qui ne redescend jamais totalement) — enseigne l'irréversibilité.

---

## 7. Game over — critères systémiques (jamais climatiques directs)

| Critère | Description |
|---|---|
| Effondrement réseau prolongé | Délestages répétés sur durée soutenue |
| Faillite budgétaire | Trésorerie sous seuil critique |
| Effondrement satisfaction citoyenne | Sous seuil prolongé → migration/désertion → boucle d'aggravation |

**Indicateur de surmortalité** (canicule + coupure réseau) : présenté sobrement dans le
**rapport de fin de période**, façon bulletin Santé Publique France — jamais un compteur "morts"
gamifié. VoLL RTE (33 000 €/MWh pour une coupure de 2h en pointe hiver) sert à monétiser
objectivement la valeur d'une coupure évitée, réutilisable pour l'indicateur d'autonomie (§9).

---

## 8. Économie — budget par échelle

### Foyer (le plus simple)
- Revenu fixe, épargne disponible, dépenses courantes.
- **Prime de rénovation** générique (`SubsidyProgram`, inspirée MaPrimeRénov') : taux dégressif
  par tranche de revenu (jusqu'à 80% pour les très modestes), plafond, **écrêtement** (reste à
  charge minimum toujours dû).
- **Prêt à taux zéro** (`Loan`, inspiré éco-PTZ : jusqu'à 50 000€, 0%, 20 ans) cumulable avec la
  prime.
- **ROI réaliste et parfois négatif** : un bouquet complet (isolation + PAC + PV) sans aide peut
  afficher un retour >40 ans, au-delà de la durée de vie des équipements — **ne jamais forcer un
  ROI positif artificiellement**. La batterie seule est souvent le moins rentable des composants
  (retour 15-20+ ans).
- **Effet de synergie/séquencement** : isoler avant d'installer une PAC change fortement le ROI
  et le confort — modéliser un effet d'ordre, pas un simple cumul additif.
- **Valeur immobilière** (`PropertyValuation`) : +4%/classe DPE (appartement) ou +8%/classe
  (maison), décote passoire ~15% (jusqu'à 25%+ en zones rurales). Dans 80% des cas la décote
  dépasse le coût des travaux → 2e canal de rentabilité, **non liquide** (réalisable à la
  revente seulement), à afficher séparément du ROI facture.
- **Revente réseau** (`GridSellContract`) : tarif figé 20 ans à la signature (comme EDF OA),
  indexé ensuite. L'**autoconsommation vaut ~18-20x plus** que la revente du surplus (tarif
  rachat ~1,1 c€/kWh mi-2026 vs achat réseau ~19-25 c€/kWh) → dimensionner au plus près du
  besoin plutôt que viser large, et la batterie redevient intéressante *dans ce contexte*
  (valorise le kWh autoconsommé, pas exporté).
- **3 blocs d'affichage distincts, jamais fusionnés** : 💶 Finances / 🏠 Patrimoine / 😊 Qualité
  de vie.

### Ville
- Budget annuel (dotations + fiscalité locale), subventions externes (façon fonds vert :
  dossier → délai → % financement), emprunt plafonné avec intérêt. Pas encore d'arbitrage
  inter-catégories complexe.

### Pays
- Arbitrage multi-postes (`BudgetCategory` : Ecology, Health, Education, SocialWelfare...),
  contrainte dure (somme = recettes). Réduire un poste social/santé pour financer l'écologie a
  un **coût de popularité ET peut réduire l'acceptation des mesures écologiques elles-mêmes**
  (`secondaryEcologyAcceptanceDelta`, écho aux mouvements de contestation de taxes carbone
  perçues comme injustes) — pas de justice environnementale sans justice sociale, modélisé
  explicitement.
- Repère réel français : budget vert ≈ 40 Md€ (8% du budget État 2024), besoins réels de
  transition ≈ 60+ Md€/an tous acteurs confondus.

### Qualité de vie (foyer, transposable aux autres échelles)
- `thermalComfortScore` : température intérieure simulée (météo + isolation + chauffage) vs
  plage de confort (19-26°C), dégradation progressive hors plage.
- `energyAutonomyRatio`, `resilienceScore` (autonomie en heures lors d'une coupure — connecté
  au moteur météo et aux événements), `financialSecurityScore` (exposition à la volatilité prix).

---

## 9. Autonomie individuelle vs choix nationaux

- **Signal de tension réseau national → foyer**, façon **Ecowatt RTE réel** (vert/orange/rouge,
  préavis avant délestage) : pont concret entre l'échelle pays (simulée en arrière-plan) et le
  foyer joué activement.
- **Valorisation de l'autonomie via la VoLL** (33 000 €/MWh RTE) : la même batterie/PV a une
  valeur contextuelle qui grimpe avec la dégradation du réseau national — calcul objectif, pas
  un multiplicateur arbitraire.
- **Jauge d'autonomie** (`AutonomyGauge`) : taux d'autosuffisance, heures de tenue en coupure,
  exposition résiduelle aux prix/décisions nationales — interprétée différemment selon le niveau
  de tension national (confort en vert, critique en rouge).
- **Effet retour réaliste (« mort des réseaux »)** : les coûts fixes réseau (TURPE) se
  répartissent sur moins de consommation si l'autoconsommation collective augmente → hausse du
  coût pour les foyers non-équipés (souvent les plus modestes) → vrai dilemme pour le joueur
  pays : encourager l'autonomie sans creuser les inégalités énergétiques.
- **Mode pays = archétypes de population** (`PopulationArchetype`) plutôt que simulation
  individuelle exhaustive : parts de population, taux d'adoption d'autonomie, revenu moyen,
  réagissant statistiquement aux incitations et à la fiabilité perçue.

---

## 10. Réseau électrique (infrastructure de transport, distinct des sources de production)

### Paliers réels (base de l'arbre technologique réseau)

| Niveau | Tension | Usage | Support |
|---|---|---|---|
| BT | < 1 kV | Distribution finale | Poteaux, quasi-systématiquement enterré |
| HTA | 1-63 kV (souvent 20 kV) | Distribution régionale | Poteaux (rural) ou souterrain (urbain) |
| HTB | > 63 kV, jusqu'à 400 kV | Transport longue distance | Pylônes, aérien quasi-exclusif |
| Submarine/HVDC | THT | Offshore, interconnexions | Câble sous-marin, palier avancé/coûteux |

Point clé contre-intuitif : l'enfouissement coûte jusqu'à **~20x plus cher** en HTB (400 kV)
qu'en aérien, alors qu'il est quasi gratuit en BT — d'où son quasi-abandon au-delà de 110 kV
dans la réalité. Bon point pédagogique sur les limites techniques de "juste enterrer les lignes".

### Modélisation
- `GridInfrastructureType` : même pattern que `EnergySourceType` (capacité max, taux de perte/km,
  capex/km, opex, délais, impact visuel, distance min aux habitations, impact biodiversité).
- `GridConnection` : relie une installation de production au réseau, coût = distance × coût/km
  (majoré si terrain difficile), pertes = taux/km × distance appliqué à la puissance livrée nette.
- Palier requis dérivé de la puissance nominale de l'installation (BT pour résidentiel, HTA pour
  ferme moyenne, HTB pour grosse centrale/parc).
- **Raccordement = poste de coût à part entière**, peut rendre un site physiquement excellent
  (bon vent/soleil) économiquement mauvais s'il est isolé du réseau — cohérent avec le vrai
  surcoût documenté du raccordement offshore.
- Le réseau est un **prérequis transversal** (`prerequisiteTechIds`) : une grosse centrale ne
  devrait pas être constructible sans accès HTB à proximité.

---

## 11. Demande énergétique — tendances structurelles

Chaque secteur a **deux vecteurs opposés** (électrification/nouveaux usages vs efficacité/
sobriété), jamais une seule tendance linéaire. Calibration réelle RTE *Futurs énergétiques
2050* (scénario de référence, 2020→2050) :

| Secteur | Évolution nette | Hausse | Baisse |
|---|---|---|---|
| Résidentiel | 160→135 TWh (**-16%**) | Chauffage électrique (PAC) | Rénovation, équipements efficaces (l'emporte) |
| Tertiaire | 130→110 TWh (**-15%**) | Data centers (**×3**) | Efficacité sur le reste (compense entièrement) |
| Industrie | 115→180 TWh (**+57%**) | Électrification procédés, réindustrialisation | Efficacité (+30 TWh, insuffisant) |
| Transport | 15→100 TWh (**×6,7**) | Fin thermique (94% parc léger électrique en 2050) | — |
| Hydrogène (nouveau) | +50 TWh | Nouvel usage électro-intensif | — |

Total France : 460 TWh (2020) → 554-754 TWh (2050) selon scénario, référence 645 TWh (+40%),
**pendant que l'énergie finale totale (tous vecteurs) baisse d'environ 40%** — voir §12.

- **Effet rebond** modélisé explicitement (une partie du gain d'efficacité est "reperdue" par un
  usage accru, ex: chauffer plus large une fois isolé) — jamais un gain 100% linéaire.
- **3 scénarios RTE réutilisables** comme courbes de référence (même principe que les SSP GIEC) :
  Reference / Sobriety / Reindustrialization.
- **Levier jouable "accueil data centers/IA"** : arbitrage recettes fiscales vs pression sur la
  demande, sujet réel et actuel, connecté au budget national (§8).

---

## 12. Électrification — pourquoi l'énergie finale baisse même quand l'électricité monte

Les technologies électriques convertissent l'énergie avec un rendement très supérieur aux
solutions fossiles :

- **PAC vs chaudière gaz** : chaudière gaz condensation ≈ 100% de rendement utile ; PAC air/eau
  SCOP mesuré en conditions réelles ≈ **2,9-4,3** (étude ADEME) → 3-4x moins d'énergie finale
  pour le même confort thermique.
- **Véhicule électrique vs thermique** : moteur thermique ≈ 20-35% de rendement, moteur
  électrique ≈ 85-90% → facteur ~3 également.

`ElectrificationConversionProfile` + `ElectrificationImpactCalculator` : l'énergie électrique
requise après électrification = énergie fossile déplacée × (rendement fossile / rendement
électrique) — presque toujours très inférieure au volume fossile qu'elle remplace. Le delta CO2
net dépend directement de l'intensité carbone du mix électrique du joueur au moment de
l'électrification (bouclage avec §3 et le registre de calibration) : **électrifier avant d'avoir
décarboné la production donne un gain amoindri** — encore un cas de séquencement qui compte.

Indicateur de synthèse recommandé (niveau pays) : un graphique à 3 courbes — énergie finale
totale (en baisse), demande électrique (en hausse), CO2 fossile cumulé évité — qui résout
visuellement le paradoxe apparent "on électrifie mais on consomme plus".

---

## 13. Registre de calibration et garde-fous anti-biais

- `ImpactCoefficient` : table centrale, aucun coefficient de gameplay sans ligne sourcée
  correspondante (source, fourchette d'incertitude, date de dernière revue).
- **Courbe de coût marginal d'abattement (MACC)** comme grille de classement de référence pour
  comparer des leviers hétérogènes (isolation, renouvelables, sobriété...) sur un même axe
  coût/tonne évitée — outil réel utilisé par McKinsey/ADEME/AIE.
- **Biais identifiés à surveiller activement** :
  - Salience visuelle (favoriser les leviers spectaculaires type éolienne au détriment de
    l'isolation, invisible mais souvent plus efficace)
  - Levier magique unique (vérifier que les contraintes réelles d'un levier dominant — délai,
    géographie, coût du stockage — sont bien présentes plutôt que de le nerfer artificiellement)
  - Ancrage d'échelle (comparer les leviers à l'échelle où le joueur agit, pas dans l'absolu)
  - Disponibilité des données (ne pas sous-représenter les leviers mal documentés — fourchette
    large assumée plutôt qu'omission ou chiffre inventé)
- **Sanity checks automatisés** : rejouer un scénario réel connu (ex. mix électrique français
  2023) et vérifier que le moteur reproduit un ordre de grandeur cohérent (ex. gCO2/kWh mesuré).

---

## 14. Sujets non tranchés / à approfondir avec Claude Code ou en session dédiée

- Mécanique fine des **politiques/règles automatiques** — un premier cas concret est défini
  (priorité de charge solaire maison vs véhicule électrique, §18), à généraliser plus tard en
  vrai système de règles pour la ville/le pays.
- Qualité de vie / satisfaction **agrégée** aux échelles ville et pays (précarité énergétique,
  qualité de l'air...) — mentionné mais pas formalisé comme au niveau foyer. **Hors scope MVP.**
- Calibration chiffrée complète du registre `ImpactCoefficient` pour toutes les sources
  d'énergie autres que l'éolien terrestre (solaire, hydraulique, nucléaire, fossiles, stockage).
- Choix final JSON flexible vs sous-classes Doctrine si le JSON s'avère limitant en pratique.
- UI/UX du rapport de période (quelles données, quelle fréquence, quel ton) — version minimale
  actée pour le MVP (§15 Phase 0-1), version soignée façon "bilan RTE" à concevoir plus tard.
- Design précis des scénarios GIEC/RTE comme courbes de fond dans les graphiques (specs
  visuelles) — hors scope MVP (pas de couche climat long terme en V1).
- Scénario "locataire" et arbre technologique détaillé de l'isolation (laine de verre,
  biosourcé, isolation externe...) : idées validées mais **repoussées en V2**, volontairement
  exclues du MVP pour ne pas fragmenter l'effort initial (voir §18).
- Benchmark réel de performance du tick à grande échelle (nombre d'installations) — à mesurer
  en pratique dès que les entités de base existent, plutôt qu'à estimer théoriquement plus loin
  (voir §17, coût dominé par l'accès base de données, pas par le calcul physique).

---

## 15. Plan de développement — ordre du MVP

Principe : chaque phase doit produire une boucle **jouable et démontrable** (même minimale),
jamais une couche technique isolée sans rendu visible — pour garder un feedback rapide avec
Claude Code et éviter de coder plusieurs systèmes en parallèle sans jamais rien voir tourner.

### Phase 0-1 — MVP resserré (périmètre verrouillé)

Un seul scénario, une seule échelle (foyer), pas de carte. Objectif : boucle complète jouable
du début à la fin, volontairement dépouillée de tout ce qui sera réintroduit progressivement
dans les phases suivantes.

- **Simulation** : 1 tick = 1 jour de jeu, pas de vitesse ajustable, pas de pause, pas de
  sélection de scénario (un seul foyer, un seul point de départ).
- **Météo** : 2 paramètres seulement — nébulosité (variation jour à jour) + température
  (sinusoïde jour/nuit + saison). Pas de vent, pas de pression atmosphérique, pas d'événements
  extrêmes, pas de climat long terme.
- **Production** : 1 seule source (panneaux solaires, 1 seul modèle au catalogue) + 1 seul type
  de stockage (batterie, capacité unique). Pas de réseau électrique modélisé (`GridConnection`
  etc. hors scope) — juste un achat/vente au réseau abstrait à tarif fixe.
- **Bâtiment** : état de départ fixe (vieille chaudière fioul, isolation nulle/DPE F-G).
  Isolation à 3 paliers discrets (aucune/correcte/performante), chauffage à 2 choix (garder le
  fioul / passer à la PAC). 1 seul événement scripté : panne de chaudière qui force la décision.
  Pendant la panne : **chauffage d'appoint électrique automatique et non désactivable**
  (personne ne vit à 4 °C — le foyer possède déjà des convecteurs) — effet Joule plafonné par
  la puissance des appareils, consigne de survie 16 °C (R241-26) rarement tenue par grand
  froid. L'urgence est systémique, pas scriptée : l'électricité explose (~×9 sur la ligne),
  le confort reste mauvais (~30 %), et le joueur paie le prix du fioul pour geler — décider
  vite vaut de l'argent ET du confort, sans malus arbitraire (§1). Leçon §12 en creux :
  l'électrique direct est le pire chauffage, la PAC son opposé exact.
- **Finances** : revenu fixe, épargne simple, 1 système de prime générique (taux selon revenu,
  plafond), 1 type de prêt (taux zéro, durée fixe), facture à 2 lignes (électricité + fioul),
  1 contrat de revente à tarif fixe (pas d'évolution tarifaire dans le temps).
- **Véhicule** (à couper entièrement si le temps presse) : choix binaire thermique/électrique,
  1 ligne de budget carburant, pas encore de logique de répartition solaire/VE (§18, V1.1).
- **Suivi** : `thermalComfortScore` seul (pas de sous-composantes), valeur du bien via une
  formule simple liée au DPE (pas de marché immobilier dynamique). Pas d'autonomie/résilience/
  signal réseau national (inutile sans échelle pays).
- **Fin de partie** : 1 seuil simple (ex. DPE amélioré + budget stable) déclenche un bilan de
  fin factuel, pas de score, pas de plusieurs conditions de victoire.
- **Interface** : tableau de bord avec jauges (finances/confort/patrimoine) + 2-3 zones
  cliquables (toit, chaudière, garage) — **pas de carte à grille, pas d'hexagones, pas
  d'isométrique, pas d'animations à ce stade** ; tout le travail sur le rendu graphique (§17)
  est réservé à partir de la Phase 3 (arrivée de la carte/ville).
- Socle technique : Symfony UX/Doctrine, mécanisme de tick (cron réel ou "lazy tick" à la
  demande), un LiveComponent `data-poll` pour rafraîchir les chiffres.

**Livrable** : un joueur peut, sur une seule maison, gérer sa production solaire face à une
météo/demande variables, traverser la panne de chaudière, financer une rénovation, et atteindre
un bilan de fin — la boucle complète, du début à la fin, avant toute extension.

### Phase 2 — Deuxième source + intermittence visible
- Ajout de l'**éolien** (introduit le vent, le cube de la vitesse, cut-in/cut-out) → première
  vraie leçon d'intermittence combinée (solaire + éolien qui ne produisent pas en même temps).
- Stockage basique (batterie, sans encore la nuance tarif de revente).
- Rapport de période minimal (texte simple : "cette semaine, X% de la demande couverte").

**Livrable** : le premier vrai dilemme de mix énergétique est jouable.

### Phase 3 — Carte et spatialisation
- Grille de tuiles (`TerrainTile`), génération procédurale simple (un seul archétype, ex.
  Plains), `windExposureFactor`/`solarExposureFactor`.
- Placement des installations sur la grille (au lieu d'un foyer abstrait).
- Effet de sillage éolien basique.

**Livrable** : le placement devient un choix stratégique, pas juste "combien j'en pose".

### Phase 4 — Économie foyer complète
- Budget foyer (§8) : revenu, épargne, `SubsidyProgram`, `Loan`, calcul de ROI (avec le cas
  "non rentable" assumé), `PropertyValuation`, `GridSellContract`.
- Les 3 blocs d'affichage (Finances / Patrimoine / Qualité de vie) avec `thermalComfortScore` et
  `resilienceScore` de base.

**Livrable** : le tutoriel foyer est fonctionnellement complet et autonome.

### Phase 5 — Météo complète + saisonnalité
- Vent + pression atmosphérique corrélée (couche 1 complète du §5), couche 2 (saisonnalité)
  complète.
- Archétypes de carte multiples (Mountain, Coastal, Island...).

**Livrable** : la météo devient un vrai système crédible, réutilisable pour la ville.

### Phase 6 — Passage à l'échelle ville
- Ajout des sources restantes utiles à cette échelle (hydraulique, biomasse, premières fossiles
  comme back-up).
- Réseau électrique (§10) : `GridInfrastructureType`, `GridConnection`, pertes par distance.
- Budget ville (§8, subventions + emprunt plafonné, pas encore d'arbitrage multi-postes).
- Politiques/règles automatiques (mécanique encore à spécifier — première itération simple
  possible : seuils + priorités, sans langage complexe).
- Satisfaction citoyenne (acceptabilité sociale des installations/lignes).

**Livrable** : le cœur du jeu, jouable en profondeur sur cette seule échelle.

### Phase 7 — Climat et conséquences long terme
- `ClimateModel` (inertie thermique), courbes SSP en référence, multiplicateur d'événements
  extrêmes, événements météo extrêmes (canicule, tempête, sécheresse) branchés sur la
  production/demande.
- Game over systémique (§7), indicateur de surmortalité en rapport de période.

**Livrable** : la boucle de rétroaction climat ↔ difficulté est vivante.

### Phase 8 — Passage à l'échelle pays
- `PopulationArchetype`, arbitrage budgétaire multi-postes (§8), signal Ecowatt-like et jauge
  d'autonomie (§9), effet de report de coûts réseau.
- Tendances de demande structurelle (§11) et électrification (§12).
- Trajectoire contrefactuelle mondiale.

**Livrable** : les 3 échelles sont jouables et connectées (le national influence le foyer).

### Phase 9 — Scénarios, contenu, équilibrage
- Rédaction de scénarios concrets (seeds fixes, objectifs, contraintes).
- Remplissage complet du registre `ImpactCoefficient` pour toutes les sources restantes.
- Sanity checks automatisés (rejouer le mix réel français comme test de non-régression).
- Passes d'équilibrage fun (délais, coûts d'accès) sans jamais toucher aux magnitudes réelles.

### Comment piloter ça avec Claude Code
- Donner **ce document entier une seule fois en tout début de session** comme contexte de
  référence durable (par ex. dans un fichier `CLAUDE.md`/`docs/game-design.md` du repo, pas
  collé à chaque prompt).
- Driver le travail réel **phase par phase**, en ne demandant qu'une phase (voire une
  sous-partie) à la fois — éviter de demander "code tout le §3" d'un coup.
- Après chaque phase, valider que le livrable est bien démontrable avant de passer à la
  suivante — cohérent avec le principe "toujours quelque chose de jouable".

## 16. Confort d'été et rénovation granulaire (pan acté, à planifier)

Pan de jeu identifié après la première partie complète du MVP (juillet 2026) : le modèle
thermique ne connaît que l'hiver, et l'isolation à 3 paliers abstraits épuise les décisions
en quelques choix. Acté sur le principe ; **à planifier quand deux prérequis seront là** :
la météo à événements extrêmes (canicules, Phase 5 — sans canicule aucun investissement
d'été n'a d'effet mesurable) et la refonte de l'affichage en rendu « jeu » plutôt que
tableau de bord (§17, Phase 3+) — multiplier les gestes de rénovation n'a de sens que si
l'interface sait les montrer sur la maison, pas en empilant des cartes de devis.

- **Éclater l'isolation en éléments** : combles (~25-30 % des déperditions), murs
  (~20-25 %), fenêtres/huisseries (~10-15 %), plancher bas (~7-10 %) — répartition ADEME.
  Chaque geste = coût/gain/prime propres → plus de décisions dans la durée, budget étalé,
  et l'effet de séquencement du §8 (isoler avant de dimensionner la PAC) devient tangible.
- **Matériaux à double caractéristique hiver/été** : résistance thermique (R) pour l'hiver,
  **déphasage/inertie** pour l'été — à R égal, fibre de bois ~10-15 h de déphasage vs laine
  minérale ~4-6 h (CSTB/ADEME). Leçon rare : deux isolants « équivalents » l'hiver ne le
  sont pas l'été.
- **L'échelle des solutions AVANT le compresseur** (chaque palier a un coût et un effet
  sourcés) : protections solaires (volets, brise-soleil, films — facteur solaire g, petit
  coût/gros effet), surventilation nocturne, brasseurs d'air (+2-3 °C de ressenti pour
  ~30 W), puits provençal/canadien, VMC double flux — puis seulement la climatisation.
- **Climatisation en arbitrage honnête, jamais moralisateur (§1)** : confort gagné vs kWh
  d'été (avec la synergie réelle clim ↔ solaire : le pic de clim coïncide avec le pic PV),
  CO₂ selon le contenu carbone du réseau, fluides frigorigènes à fort PRG, rejet de chaleur.
  La PAC air/eau **réversible** raccorde ce pan à l'équipement déjà en jeu.
- **Confort adaptatif** : la plage de confort n'est pas symétrique ni fixe — 19 °C est une
  borne d'hiver (réglementaire), en été le confort tient jusqu'à ~26-28 °C (EN 16798).
  Bornes saisonnières à introduire quand l'été devient un vrai sujet.

## 17. Interface graphique et rendu (décisions validées, pour la Phase 3+)

Hors scope du MVP (§15 Phase 0-1), à activer à partir de l'arrivée de la carte (Phase 3).

### Vue locale (échelle foyer) — la maison en coupe interactive

Réflexion actée (juillet 2026, maquette : `docs/mockups/vue-locale.html`) : l'échelle foyer
n'a PAS besoin de la grille hexagonale — celle-ci sert la carte régionale/ville (sections
suivantes). La maison est une **scène à emplacements** (« slots »), pas un terrain à tuiles.

- **Forme retenue : la coupe latérale** (« dollhouse » façon Fallout Shelter / Les Sims en
  section) plutôt que l'isométrique : lisibilité pédagogique maximale (on voit TOUT l'état du
  foyer d'un regard), assets 2D plats bien plus simples, et correspondance 1:1 avec les zones
  déjà en jeu (toit, chauffage, garage) qui deviennent des emplacements cliquables. S'y
  ajoutent : murs/isolation (l'épaisseur/couleur du liseré = le palier), séjour (la teinte
  intérieure = le confort), jardin (réserve pour plus tard). L'isométrique reste le langage de
  la carte Phase 3+ ; les deux échelles partagent la palette pour rester un seul jeu.
- **L'état EST le rendu** (le principe central) : chaque équipement se dessine selon le
  `GameView` — les panneaux apparaissent sur le toit une fois posés, la chaudière en panne
  fume avec un halo rouge pulsant, la pièce vire au bleu quand le ressenti chute, le ciel
  suit la nébulosité et la saison. Les indicateurs quittent les cartes-tableaux pour devenir
  **diégétiques** ; il ne reste qu'un bandeau HUD fin (date, épargne, confort, vitesse).
- **Clic = panneau contextuel** : cliquer un emplacement ouvre le panneau d'action existant
  (devis coût/prime/reste à charge + effets estimés + double financement). La panne de
  chaudière devient le cas d'école : l'élément crie visuellement, le clic propose
  réparer/remplacer, le temps est déjà en pause automatique.
- **Technologie : SVG inline + classes CSS, pas de sprites** pour cette échelle. Précision de
  vocabulaire : chaque équipement demande bien un *dessin* (chaudière, PAC, panneaux,
  batterie…), mais un dessin = **~15-30 formes SVG**, pas un binaire à exporter/licencier.
  Le catalogue Phase 0-1 tient en ~10 petits dessins. **Organisation en bibliothèque** (maquette
  v3) : *un fichier `.svg` autonome par élément* (`house-shell`, `boiler-fioul`,
  `boiler-fioul-broken`, `heat-pump`, `solar-panels`, `battery`, `tree-winter`, `cloud`…),
  chacun ouvrable/retouchable tel quel dans Inkscape/Illustrator, ses micro-animations
  embarquées (style interne). La scène ne possède que l'ambiance (ciel, sol, lumière) et
  ASSEMBLE les assets ; en jeu le renderer Twig les inclut **inline**
  (`{{ include('scene/assets/x.svg') }}`) pour garder le pilotage des états par classes CSS —
  les maquettes composent par `<image href>`. Retravailler un asset à la main ne touche ni la
  scène ni le code. Un changement d'état = une classe (pas
  un asset de plus), les animations légères sont du CSS pur (`transform`/`opacity` : fumée,
  pulsation, rotation du ventilateur de PAC, nuages — cohérent avec la règle d'animations de
  la carte), net à toutes tailles, thémable par variables CSS. Le pipeline d'assets illustrés
  (§ production d'assets ci-dessous) reste pour la carte Phase 3+. La présentation ne lisant
  QUE `GameView` (§3), la migration est purement présentationnelle — le LiveComponent et le
  `data-poll` restent tels quels, le SVG remplace les cartes dans le template.
- **Rendu de l'isolation** (le cas le moins évident, résolu par la coupe elle-même) : la coupe
  montre l'épaisseur des parois → la **couche d'isolant est visible** dans la tranche du mur
  et du toit (liseré coloré qui apparaît/s'épaissit par palier), complétée par deux signaux
  d'ambiance : la **neige qui tient sur le toit** d'une maison bien isolée (déperditions
  faibles — métaphore réaliste et parlante) et la teinte intérieure chaude/froide déjà pilotée
  par le confort. Pendant les travaux (quand les délais existeront, post-MVP) : échafaudage.
- **Cohérence entre échelles (risque identifié, à trancher en Phase 3)** : deux échelles ≠
  deux identités visuelles. Ce qui doit être UNIQUE : palette, typographie, iconographie,
  ton « flat » ; ce qui peut différer : la disposition (scène à slots au foyer, grille hexa
  sur la carte) — invisible pour le joueur, qui vit ça comme un **zoom** (cliquer sa parcelle
  sur la carte ouvre la coupe, comme l'inspecteur de bâtiment d'un city-builder). Point de
  vigilance : la carte prévoit de « vraies textures illustrées » (§ ci-dessous) alors que la
  coupe est en flat vector — AU moment de la carte, trancher une direction artistique unique
  (illustrer la coupe OU aplatir la carte) ; d'ici là le flat vector CSS-thémable est
  précisément le choix qui reste re-stylable à moindre coût.
- **Progressivité** (l'ordre à suivre, chaque pas jouable) : ① la scène SVG remplace la
  section « Ma maison » (états visuels, zones cliquables qui scrollent vers les devis
  existants) — les cartes chiffrées restent à côté ; ② les indicateurs migrent dans la scène
  (confort en teinte, batterie en jauge dans le garage, météo dans le ciel) et les clics
  ouvrent de vrais panneaux contextuels, le dashboard se réduit au HUD ; ③ le jardin devient
  une **grille de parcelle grossière** le jour où un gameplay l'exige (potager, arbres
  d'ombrage, panneaux au sol, borne VE — V1.1+), sans hexagones : quelques cases suffisent.
- **Météo & ambiance (l'état simulé pilote le ciel — jamais de déco mensongère)** : seul ce
  que la simulation SAIT s'affiche. Dès la Phase 0-1 : nébulosité continue (`--cloud` :
  densité des nuages, halo du soleil, gris du ciel), saison (teinte du ciel, **hauteur du
  soleil** — bas l'hiver, la vraie cause de la production faible —, arbre en 4 états),
  température (givre/neige d'ambiance sous ~0 °C — habillage *déduit*, assumé tant qu'aucun
  gameplay n'en dépend : pas de précipitations dans le modèle), **cheminée qui fume
  proportionnellement au fioul brûlé** (et plus du tout en PAC — la leçon §12 rendue
  visible), scintillement des panneaux les jours de production, teinte intérieure = confort.
  En Phase 2/5, quand les données arrivent : éolienne qui tourne à sa vitesse réelle
  (cut-in/cut-out visibles — l'intermittence incarnée), vent (arbres, fumée couchée),
  canicule (ciel blanc, herbe jaunie — pan §16), pluie/orages seulement quand le générateur
  les produira. Mécanique : faits météo dans le `SceneView` → classes/custom properties sur
  les `<g>` → `scene.css` ; le `data-poll` fait vivre le ciel jour après jour.
- **Structure de rendu (pour changer de DA sans refonte)** : la scène est séparée en
  *modèle de scène* et *renderer*. Un `HouseSceneView` (application) décrit le « quoi
  montrer » en termes purement sémantiques — liste de slots `{key, state, label, action,
  jauges}` + ciel `{nébulosité, saison, neige}` — avec l'interdit d'hygiène : **jamais de
  coordonnées d'ÉCRAN, couleurs ou formes** dans ce modèle (`broken`, pas « halo rouge »).
  Précision importante : les faits SPATIAUX qui changent la simulation restent des données de
  domaine et passent dans le modèle — l'orientation N/S/E/O d'un panneau (module la
  production, ADEME) comme, en Phase 3+, la coordonnée de tuile hexagonale d'une installation
  (voisins, vent, distances — coordonnées *logiques* axiales, cf. Red Blob Games). Le test :
  « cette valeur change-t-elle un résultat de simulation ? » Oui → modèle ; non (pixels,
  projection, tri en profondeur) → renderer. Le
  « comment dessiner » vit dans UN template de renderer (`_scene_cutaway.html.twig` : formes
  SVG, coordonnées, classes CSS). Changer de direction artistique = changer de renderer :
  plus belles textures = le CSS/`<defs>` du renderer seul ; vue isométrique = un second
  template lisant les mêmes données (+ redessiner les ~10 dessins en perspective — le vrai
  coût de l'iso est du dessin, pas du code) ; canvas/three.js un jour = le même
  `HouseSceneView` sérialisé en JSON vers un contrôleur Stimulus. Les interactions sont des
  *intentions* (« ce slot ouvre l'action heat_pump ») — le renderer décide seulement où est
  la zone cliquable. C'est le principe du §3 (la présentation ne voit que `GameView`) répété
  un cran plus bas.
- **Hors périmètre de cette vue** : pièces multiples détaillées / meubles (aucun gameplay
  attaché), vraie 3D, personnages animés. La coupe reste un tableau de bord incarné, pas un
  jeu de poupées.

- **Style retenu** : isométrique **plate** façon SimCity 2000/Cities Skylines — camera fixe,
  jamais de rotation. **Pas de vraie 3D CSS** (`perspective`/`preserve-3d`) sur la grille : sans
  rotation caméra, une vraie 3D n'apporte aucun bénéfice visuel et risque une "explosion de
  couches" GPU sur une grille dense. Réservée, si besoin, à un élément isolé hors grille (ex.
  modèle 3D détaillé au clic sur un panneau de détail).
- **Grille hexagonale en CSS pur** (`clip-path: polygon()`, colonnes décalées) : pas de SVG, pas
  de canvas. Meilleur choix que le carré pour représenter le vent/relief à 6 directions. Un
  guide de référence fait autorité sur les coordonnées/voisinage/distance hexagonaux (Red Blob
  Games) — à donner tel quel à Claude Code plutôt qu'à re-dériver.
- **Relief** : paliers d'altitude discrets (réutilisent les `TerrainType` déjà définis), rendus
  par une paroi ("skirt") visible **uniquement sur les arêtes qui touchent un voisin plus bas**
  — jamais sur tout le pourtour. Coût de rendu proportionnel au périmètre des zones en relief,
  pas à leur surface. Teinte fixe par orientation d'arête pour simuler une lumière directionnelle
  sans aucun calcul d'éclairage réel.
- **Empreinte multi-tuiles** : `EnergySourceType.footprintTiles` (liste de coordonnées
  hexagonales relatives à une tuile d'ancrage — ex. 1 tuile pour une éolienne, motif "fleur" à 7
  tuiles pour une centrale). Le sprite est dessiné une seule fois, dimensionné pour déborder
  visuellement sur les tuiles voisines — pas de découpe par tuile. Validation de placement :
  toutes les tuiles de l'empreinte doivent être constructibles/libres.
- **Tri en profondeur (painter's algorithm)** : tuiles et objets triés par position verticale à
  l'écran (`cy`), du plus loin au plus proche de la caméra — un objet haut sur une tuile proche
  peut légitimement recouvrir une tuile plus loin qu'il déborde visuellement (comportement
  voulu, pas un bug).
- **Textures de terrain et transitions** : vraies textures illustrées (pas des aplats), avec
  gestion des bords via la technique du **dual-grid** (grille de données décalée d'une
  demi-tuile par rapport à la grille d'affichage) + **ordre de priorité entre terrains** — ramène
  le besoin à ~16 tuiles de transition par terrain (au lieu d'une combinatoire par paire),
  confirmé fonctionnel en hexagonal par une implémentation open-source de référence
  (`TileMapDual`). Coût total ≈ nombre de terrains × 16, fixe et borné.
- **Animations légères** : `transform`/`opacity` uniquement (rotation des pales d'éolienne,
  reflet solaire, nuages, fumée) — jamais de canvas. N'animer que les éléments visibles dans le
  viewport (`IntersectionObserver`) pour rester performant à grande échelle.
- **Virtualisation de la grille** : la taille logique de la carte (ex. 1000×1000 tuiles) est
  indépendante du coût de rendu — seules les tuiles visibles à l'écran (+ marge) existent comme
  éléments DOM ; défilement = montage/démontage des tuiles qui entrent/sortent du viewport.
- **Production d'assets** : hybride statique (SVG/PNG illustré, 1 asset par variante du
  catalogue) + animation séparée sur un calque dédié (ex. rotor d'éolienne isolé du mât). Outils
  IA à style verrouillé (Scenario, Leonardo.ai, PixelLab, Sprixen) recommandés pour générer les
  variantes en cohérence, avec passe de nettoyage/alignement manuelle systématique — pas un
  pipeline 100% automatisé. Ressources CC0 de départ : Kenney.nl, Screaming Brain Studios
  (itch.io, dont des packs hexagonaux dédiés), OpenGameArt.org.
- **Performance du calcul par tick** : le coût dépend du **nombre d'installations actives**, pas
  de la taille de la carte (les tuiles vides ne coûtent rien). Le vrai goulot d'étranglement
  attendu est l'accès base de données (hydratation Doctrine en masse), pas le calcul physique
  lui-même (trivial pour PHP même sur plusieurs milliers d'installations). Leviers si besoin :
  requêtes en masse (DBAL brut plutôt que Doctrine pour la boucle de tick), agrégation des logs
  au lieu d'un détail par installation à chaque tick, écritures en masse. Un tick de 30s+ (voire
  simulant plusieurs heures/jours par cycle réel, cohérent avec le rythme casual/asynchrone)
  rend ces optimisations non critiques pour le MVP — à mesurer réellement dès que possible
  plutôt qu'à sur-anticiper.

---

## 18. Arc de partie et scénario pédagogique

- **Principe directeur : la pédagogie vient des systèmes, jamais du texte.** Aucune popup de
  jugement, aucun personnage moralisateur. Les rapports factuels déjà définis (bilan de période,
  indicateur de surmortalité, VoLL, ROI batterie décevant, NIMBY...) sont eux-mêmes l'outil
  pédagogique — le système raconte, le jeu ne fait jamais la leçon.
- **Structure en "ères" déclenchées par seuils atteints**, pas par des dates figées (ex. passage
  à l'ère suivante quand le joueur franchit un % de renouvelable) — laisse un rythme propre à
  chaque partie plutôt qu'un parcours scripté. Applicable à l'échelle ville (héritage fossile →
  premiers pas → réseau/croissance → transition profonde → horizon).
- **Accès aux échelles : nested, jamais séquentiel.** Débloquer la ville puis le pays ne fait
  jamais disparaître le foyer. C'est même nécessaire pédagogiquement : la jauge d'autonomie et
  le signal réseau national (§9) n'ont de sens que si le foyer reste jouable en parallèle du
  national. Le foyer du tutoriel devient un **personnage récurrent** ("ta" maison précise, pas
  une maison abstraite) que le joueur peut retrouver à tout moment, même en pleine gestion
  nationale — dispositif narratif fort : voir concrètement comment les grandes décisions macro
  se répercutent sur sa propre vie quotidienne.
- **Fin de partie = bilan, jamais un score.** Cohérent avec le principe multi-critères (§1) :
  pas d'écran de victoire, un rapport de legs factuel (trajectoire vs courbes SSP, CO2 cumulé,
  autonomie, satisfaction) qui laisse la conclusion morale au joueur.

### Scénario MVP retenu : le foyer primo-accédant

Un seul scénario pour la V1 (les autres pistes ci-dessous sont volontairement repoussées) :
jeune couple au budget serré, achat d'une maison ancienne mal isolée, chauffage fioul. Choisi
pour son universalité et parce qu'il couvre à lui seul tout le nécessaire pédagogique sans
multiplier les scénarios à maintenir (voir périmètre exact en §15 Phase 0-1).

**Point d'équilibre important, tranché en session** : le foyer ne doit pas devenir un mini-jeu
de rénovation déconnecté du reste. Répartition voulue :
- **Boucle principale récurrente** : solaire + batterie + météo + demande — la vraie miniature
  du gameplay ville/pays (production vs demande sous contrainte météo), à laquelle le joueur
  revient à chaque visite.
- **Décisions ponctuelles et structurantes, pas une boucle à gérer en continu** : chauffage et
  isolation — 1 à 2 grandes décisions sur toute la partie, pas un système à optimiser sans cesse.
- **Calcul simple en arrière-plan, jamais une mécanique à part entière** : aides/prêt — l'esprit
  du dispositif réel (revenus modestes mieux aidés, reste à charge, prêt à taux zéro), sans
  simuler l'empilement réel des dispositifs (MaPrimeRénov'/CEE/TVA réduite/aides locales).
- Levier réglementaire réel exploité sans script forcé : interdiction d'installer une **nouvelle**
  chaudière 100% fioul en France depuis juillet 2022 (la chaudière existante peut être réparée
  indéfiniment) — source de tension naturelle vers l'événement de panne, pas une date imposée.

**Véhicule électrique — mécanisme de justification retenu** (V1.1, après le MVP resserré) :
- Ajouter une ligne de budget récurrente "carburant" symétrique à la facture d'énergie, pour
  que l'abandon du thermique se ressente directement en €/mois, sur le même principe que le
  gain d'efficacité de conversion déjà modélisé pour le chauffage (§12).
- Aide à l'achat réutilisant telle quelle l'entité `SubsidyProgram` (dispositif réel français
  équivalent, montant dégressif selon revenu).
- **Mécanique pédagogique forte** : une règle de priorité de charge (maison d'abord / VE
  prioritaire le matin / charge nocturne réseau / surplus solaire uniquement) — premier cas
  concret du système de "politiques automatiques" resté abstrait (§14). Rend tangible le
  décalage temporel réel entre production solaire (midi) et besoin de recharge (soir), et donne
  un second cas d'usage concret à la batterie (pont jour/nuit), renforçant sa valeur perçue.
- Pas de lien forcé avec le confort thermique : le VE se justifie sur les seuls axes Finances et
  Carbone, ce qui est très bien ainsi (principe multi-critères, §1).

**Cas réel validé — kit solaire "plug and play" (contenu V2, balcon/locataire)** : rentable en
pratique (retour ~4-7 ans) **malgré l'absence totale d'aide publique**, précisément parce que la
revente de surplus y est interdite — donc 100% autoconsommation, la valeur la plus haute (§8).
Bon contre-exemple pédagogique au "gros bouquet" de rénovation peu rentable sans aides : un
petit investissement bien calibré à la consommation réelle peut battre un gros projet mal
dimensionné. Batterie intégrée : ROI dégradé (~9-11 ans), cohérent avec le reste du modèle.

**Idées explicitement repoussées en V2** (pour ne pas fragmenter l'effort du MVP) :
- Scénario "locataire" (incitation dissociée propriétaire/locataire, kit solaire de balcon
  plafonné à 800W comme seule option) — bon contenu mais trop limitant seul pour un MVP.
- Arbre technologique détaillé de l'isolation (laine de verre / biosourcé / isolation externe,
  chacun avec ses propres caractéristiques) — réduit à 3 paliers discrets pour le MVP.
- Missions courtes optionnelles greffées sur les systèmes en place (ex. "traverse un
  anticyclone hivernal avec moins de 2h de coupure") — nécessitent la couche climat (Phase 7).

---

## 19. Sources principales citées durant la conception

RTE (Futurs énergétiques 2050, bilans prévisionnels, VoLL/critère de sécurité d'approvisionnement,
Ecowatt), ADEME (études PAC, confort thermique, MACC), CRE (tarifs EDF OA, TURPE), Notaires de
France (impact DPE sur la valeur immobilière), NREL/ATB (projections éoliennes 2030), Sénat
(coûts raccordement éolien offshore), Wikipédia (COP, lignes HT/HTB), littérature académique sur
l'analyse de cycle de vie des énergies renouvelables.
