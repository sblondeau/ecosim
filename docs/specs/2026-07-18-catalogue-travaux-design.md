# Catalogue de travaux — une classe par travail (design)

> Statut : **validé, à implémenter**. Fait suite à l'audit d'architecture du
> 18 juillet 2026. Prérequis d'aucune tranche de contenu, mais **bloquant de
> fait** pour les suivantes : chaque tranche ajoutée aggrave le problème décrit
> ici.

## 1. Le problème

Ajouter un travail à l'arbre coûte aujourd'hui **~15 sites de code** répartis
sur les trois couches. Relevé exhaustif sur un cas réel (le chauffe-eau
thermodynamique, tranche 5) :

| # | Fichier | Ajout | Forcé par |
|---|---|---|---|
| 1 | `Building/WaterHeater.php` | enum d'état | la mécanique (parfois réutilisable) |
| 2 | `Building/Household.php` | champ + wither | la mécanique |
| 3 | `Finance/Renovation.php` | `case` | l'enum |
| 4 | `Renovation::isSubsidised()` | bras du match | **match exhaustif** |
| 5 | `FinanceCalibration.php` | coefficient sourcé | §6 (légitime) |
| 6 | `EnergyCalibration` / modèle | l'effet physique | la mécanique |
| 7 | `RenovationQuoter::quote()` | bras du match | **match exhaustif** |
| 8 | `RenovationQuoter` | méthode privée ~18 l. | l'enum |
| 9 | `RenovationAdvisor::adviceFor()` | bras du match | **match exhaustif** |
| 10 | `GameView.php` | champ « done » | accidentel |
| 11 | `GameViewFactory::build()` | ligne de mapping | accidentel |
| 12 | `HouseSceneView.php` | champ de surface | accidentel |
| 13 | `GameViewFactory::houseScene()` | ligne de mapping | accidentel |
| 14 | `_slot.html.twig` | `worksOfSlot` + bloc done-chip | accidentel |
| 15 | `QuoteCard.html.twig` | branche `elseif` d'icône | accidentel |
| 16 | SVG + `scene.css` + `_cutaway` | le visuel | l'art (légitime) |

Confirmé par les commits : `ea88c10`, `4834ac4`, `c1cc671` touchent chacun 5 à
9 fichiers pour un ou deux travaux.

### La cause

**L'enum `Renovation` et ses `match` exhaustifs.** `RenovationQuoter` référence
`Renovation::` 28 fois, `RenovationAdvisor` 15 fois. La forme même de ces
classes est « une branche par travail » : elles ne peuvent structurellement pas
être fermées à la modification (OCP).

Le paradoxe : cette exhaustivité est *voulue* (PHPStan force à traiter le
nouveau cas), mais elle ne couvre que **3 `match` PHP**. Elle ne dit rien de
`worksOfSlot`, de l'icône `QuoteCard` ni du gate CSS — précisément les endroits
où l'oubli est silencieux. On paie le couplage sans avoir le filet complet.

### Ce qui n'est PAS le problème

`GameView` et ses ~90 champs. Contre-intuitif, mais : elle n'est construite
qu'à **un seul endroit** et **aucun test ne l'instancie**. Le ripple habituel du
gros DTO n'existe pas. Ajouter un champ y coûte deux lignes. C'est inélégant,
ce n'est pas coûteux — et ce n'est pas ce qui sera corrigé ici (voir §6).

## 2. Objectif

**Ajouter un travail = créer une classe.** Plus, de façon irréductible :
une entrée de calibration (§6 du CLAUDE.md impose que tout chiffre sourcé vive
dans le registre central) et l'asset SVG quand le travail a un visuel.

Cible : **de ~15 sites à 3**, et surtout **la couche vue devient fermée à la
modification** — elle ne connaît plus aucun travail nommément.

## 3. L'interface

```php
namespace App\Domain\Finance;

/**
 * Un travail de l'arbre : tout ce que le jeu doit savoir de lui, en un seul
 * endroit. Ajouter un travail = ajouter une implémentation.
 */
interface RenovationDefinition
{
    public function slug(): string;
    public function slot(): SceneSlot;
    public function offerFor(Household $household): ?RenovationOffer;
    public function adviceFor(Household $household): RenovationAdvice;
    public function isEnergyPerformanceWork(): bool;
    public function doneLabelFor(Household $household): ?string;
    public function sceneLayerFor(Household $household): ?string;
    public function iconAsset(): string;
}
```

### `slug(): string`
Remplace `Renovation::value`. Identité : clé du tableau `actions`, paramètre du
`<twig:Button action="order">`, param de `LiveAction`. Le catalogue valide
l'unicité à la construction — un doublon est un bug de programmation.

### `slot(): SceneSlot`
Remplace le tableau `worksOfSlot` codé en dur dans `_slot.html.twig`.

`SceneSlot` **reste un enum** (`Roof`, `Walls`, `Heating`, `Garage`, `Living`),
délibérément : c'est un ensemble réellement fermé, fixé par le dessin de la
coupe — en ajouter un veut dire redessiner la maison. Ici l'exhaustivité du
`match` est un bénéfice sans coût, contrairement à `Renovation`.

### `offerFor(Household): ?RenovationOffer`
`null` = indisponible (déjà fait, prérequis absent, palier maximal atteint).

La disponibilité reste **fusionnée** avec la cotation plutôt que scindée en un
`isAvailableFor()` séparé : deux méthodes devant s'accorder, c'est un invariant
à maintenir pour rien.

**La définition ne produit PAS un `RenovationQuote` complet.** Aujourd'hui
`$this->subsidy->subsidyFor($price)` apparaît **9 fois** dans le quoter ;
laisser chaque définition fabriquer son devis dupliquerait la politique de
prime dans 15 classes, et la prochaine réforme MaPrimeRénov' deviendrait un
chantier. La définition ne déclare donc que ce qui lui est propre :

```php
final readonly class RenovationOffer
{
    public function __construct(
        public string $title,               // dynamique : « Menuiseries — Triple vitrage »
        public Money $cost,
        public Household $resultingHousehold,
    ) {}
}
```

`RenovationQuoter` **subsiste**, réduit à un service de politique : il prend
l'offre, applique prime et éligibilité prêt, produit le `RenovationQuote`. La
règle de financement reste à un seul endroit.

### `adviceFor(Household): RenovationAdvice`
Remplace le `match` de 15 bras de `RenovationAdvisor`. Les définitions qui ont
besoin du contexte (`$poorlyInsulated`) s'injectent `BuildingCalibration` :
`envelopeLossFactor()` est un calcul trivial, pas une simulation annuelle — le
recalcul par définition est sans conséquence de perf.

### `isEnergyPerformanceWork(): bool`
Fusionne `isSubsidised()` et `isLoanEligible()`, qui sont aujourd'hui
littéralement identiques (`return $this->isSubsidised();`, commentaire « même
périmètre »). Deux méthodes pour un seul fait réel : *ce travail relève-t-il du
périmètre des aides à la performance énergétique*. Nommée d'après la réalité
plutôt que d'après l'une de ses deux conséquences. **Comportement inchangé.**

### `doneLabelFor(Household): ?string`
Remplace les blocs `done-chip` recopiés dans les 5 branches de
`_slot.html.twig`. `null` = pas encore fait. Retour dynamique (`'Batterie
10 kWh'`, `'Murs — ITE'`).

Cas notable, le vitrage : en double, il est **à la fois** fait (chip « Double
vitrage ») et améliorable (devis vers le triple). `?string` et
`?RenovationOffer` étant indépendants, les deux répondent non-null — le cas se
gère sans exception.

### `sceneLayerFor(Household): ?string`
Prend `Household` : le calque dépend de l'état (`walls-interior` vs
`walls-exterior`, `glazing-double` vs `glazing-triple`), pas seulement du
travail.

`HouseSceneView` perd ses champs typés par surface au profit d'une liste :

```php
/** @var list<string> */
public array $activeLayers;   // ['walls-exterior', 'vmc', 'water-heater-thermo']
```

`HouseShell` les émet en boucle (`house--{{ layer }}`). **Conséquence : ajouter
un visuel ne touche plus une ligne de PHP** — un `<g class="…">` dans le SVG,
un gate CSS, fini.

La règle d'hygiène §17 s'en trouve mieux respectée qu'aujourd'hui : le modèle de
scène ne connaît plus le nom des équipements, seulement des clés sémantiques
opaques.

> **Correction (revue finale, tâche 6 — à lire avant d'implémenter le
> palier 4) :** le plan ci-dessus ne tient pas tel quel. `sceneLayerFor()`
> retourne une simple `string`, mais ses 15 valeurs se répartissent en **deux
> familles disjointes de consommateurs**, alors que `activeLayers` +
> `house--{{ layer }}` en boucle n'en sert qu'une :
>
> - **9 clés sont des gates CSS** que `HouseShell` doit préfixer de
>   `house--` (`scene.css` : `.house--{layer} .xxx { display: initial }`) :
>   `roof-ins`, `walls-interior`, `walls-exterior`, `glazing-double`,
>   `glazing-triple`, `vmc-double-flow`, `curtains`, `floor-heating`.
> - **5 clés pilotent l'affichage d'un `<twig:scene:*>` entier** dans
>   `_cutaway.html.twig` (`{% if scene.xxx == '...' %}`) et **n'ont aucune
>   règle `.house--*`** : `heating-heat-pump`, `heating-pellet`,
>   `water-heater-thermo`, `battery`, `solar-full`/`solar-kit`.
>
>   Implémentée telle quelle, la boucle `house--{{ layer }}` poserait ces 5
>   valeurs comme classes CSS mortes (`house--battery`, `house--solar-full`…)
>   et les visuels PAC, batterie, chauffe-eau et solaire **disparaîtraient**.
>   Le palier 4 doit distinguer les deux familles — par exemple deux
>   accesseurs (`houseLayers()`/`componentSelectors()`) ou une structure
>   typée par famille — pas une seule liste plate consommée par une seule
>   boucle.
>
> Deux écarts supplémentaires, dans la même zone :
> - `.house--draughtproofed` existe déjà dans `scene.css` et y est actif
>   (bandeau rouge de la fenêtre, tant qu'elle reste en simple vitrage), mais
>   `DraughtProofingWork::sceneLayerFor()` retourne délibérément `null`
>   (calfeutrage jugé invisible à cette échelle — voir le commentaire de la
>   classe). Le palier 4 doit décider explicitement : soit brancher ce
>   travail sur la classe existante, soit documenter que la classe CSS reste
>   orpheline.
> - L'état `fioul-broken` de `scene.heatingState` n'est porté par **aucun**
>   travail (c'est un état — la panne scriptée — pas un geste du joueur), donc
>   `HouseSceneView`/`scene.heatingState` ne pourra pas être intégralement
>   remplacé par `activeLayers` : il restera un champ d'état à côté, quelle
>   que soit la forme retenue pour le palier 4.

### `iconAsset(): string`
Remplace les 10 `elseif` de `QuoteCard`, qui devient
`{{ include(action.iconAsset) }}`.

Travail préalable : **6 icônes sont aujourd'hui du SVG inline dans le Twig**
(isolation, vitrage, VMC, kit solaire, calfeutrage, rideaux). Il faut les
extraire en fichiers d'assets. Corvée, mais elle sert la règle déjà posée :
« un seul dessin par équipement — l'icône du tiroir EST l'asset de la scène ».

## 4. Le catalogue

Liste explicite ordonnée. `src/Domain/**` a l'interdiction absolue d'importer
Symfony (CLAUDE.md §3), donc pas d'autoconfiguration par tag dans le domaine.

```php
final readonly class RenovationCatalog
{
    /** @param list<RenovationDefinition> $works */
    public function __construct(private array $works = [
        new BoilerRepairWork(),
        new RoofInsulationWork(),
        // …
        new WaterHeaterThermoWork(),   // <- la ligne à ajouter
    ]) {}
}
```

**L'ordre de la liste est l'ordre d'affichage des devis dans un tiroir.**
`worksOfSlot` l'encode aujourd'hui implicitement (`['boiler_repair',
'heat_pump', …]` — la réparation d'abord en cas de panne) ; un catalogue non
ordonné le perdrait silencieusement. Le rendre explicite et lisible d'un coup
d'œil est un bénéfice, pas un pis-aller.

Écarté : le *tagged iterator* Symfony (le catalogue devrait migrer hors du
domaine, et l'ordre passerait par un `sortOrder()` dispersé sur 15 classes) ;
l'attribut PHP + scan maison (découverte implicite, scanner à maintenir).

## 5. Devenir des classes existantes

| Classe | Devenir |
|---|---|
| `Renovation` (enum) | **supprimé** — le slug devient un `string`, résolu par le catalogue |
| `RenovationQuoter` (295 l.) | **réduit à ~40 l.** : politique prime/prêt, `Offer → Quote` |
| `RenovationAdvisor` (94 l.) | **supprimé** — absorbé par `adviceFor()` |
| `RenovationHandler` | `order(GameState, string $slug, string $financing)` ; re-cote toujours côté serveur |
| `HouseSceneView` | 7 champs de surface → `activeLayers: list<string>` |
| `GameView` | perd ~10 champs « done » → `doneLabelsBySlot` |
| `_slot.html.twig` | `worksOfSlot` et les 5 blocs done-chip supprimés, dérivés du catalogue |
| `QuoteCard` | 10 `elseif` → `{{ include(action.iconAsset) }}` |

## 6. Hors périmètre

**Les 91 ms/render.** Mesuré : `GameViewFactory::build()` prend **90,9 ms**,
parce que `AnnualOutcomeEstimator::estimate()` simule 365 jours et qu'il est
appelé 1 fois pour l'état courant + 2 fois pour l'aperçu du thermostat + **1
fois par travail disponible (13 aujourd'hui)** — soit ~5 000 jours simulés par
rendu, rejoués **toutes les 4 secondes** par le `data-poll`, que les tiroirs
soient ouverts ou non.

C'est un chantier **orthogonal**, à traiter séparément. Le catalogue le
*facilite* (il devient trivial de ne coter que le tiroir ouvert) mais ne le
règle pas. À inscrire au backlog.

**Le découpage de `GameView` en sous-vues par axe.** Séduisant, mais §1 montre
que le coût réel y est faible. Les étapes 4-6 ci-dessous la dégonflent déjà
d'une dizaine de champs ; au-delà, ce serait de l'esthétique. À revoir si et
seulement si le chantier perf le rend nécessaire.

## 7. Migration — 6 paliers, `make qa` vert à chaque

Conforme au §8 (« avancer par petites étapes démontrables »). Pas de big-bang.

1. **Poser les fondations.** `RenovationDefinition`, `RenovationOffer`,
   `SceneSlot`, `RenovationCatalog` (vide). `RenovationQuoter` et
   `RenovationAdvisor` consultent le catalogue d'abord, retombent sur leur
   `match` sinon. Rien ne change à l'écran.
2. **Migrer les 15 travaux**, par tiroir (chauffage → enveloppe →
   garage/toit/séjour). À chaque lot, des bras de `match` disparaissent.
3. **Supprimer** l'enum `Renovation`, `RenovationAdvisor`, et les `match`
   vidés. `RenovationHandler` passe au slug.
4. **Scène** : `activeLayers` + boucle dans `HouseShell`.
5. **Vue** : `doneLabelsBySlot` ; `_slot.html.twig` piloté par le catalogue.
6. **Icônes** : extraire les 6 SVG inline en assets, brancher `iconAsset()`.

Les paliers 1-3 ferment le domaine ; **4-6 sont ceux qui répondent à l'objectif
« rien changer d'autre »**, puisqu'après eux la couche vue ne connaît plus les
travaux nommément.

### Tests

Les tests unitaires de `RenovationQuoter` et `RenovationAdvisor` migrent vers
**un test par définition** : plus nombreux, plus courts, enfin isolés. Chaque
définition arrive avec son test dans le même commit (§5).

Le catalogue reçoit ses propres tests : unicité des slugs, cohérence
slot/travail, et — filet contre l'oubli que l'enum ne fournissait pas — que
tout travail déclarant un `sceneLayerFor()` non-null corresponde à un gate CSS
existant.

## 8. Le compromis, en clair

**On perd l'exhaustivité PHPStan sur les 3 `match`.** C'est réel.

L'échange reste gagnant : l'interface est un filet *plus serré*. On ne peut pas
implémenter `RenovationDefinition` sans fournir le slot, l'icône, le conseil et
le calque. Le compilateur passe de « te rappeler 3 endroits » à « t'imposer
8 méthodes dans 1 fichier », et il couvre désormais la couche Twig, qu'il ne
voyait pas du tout.

Second point : le slug arrive du formulaire en `string`.
`RenovationCatalog::get(string)` lève sur inconnu, et `RenovationHandler`
re-cote déjà côté serveur — la surface de risque ne bouge pas.

**Coût brut** : ~15 classes nouvelles contre ~390 lignes retirées de `Quoter` +
`Advisor`. Le total de lignes bouge peu ; le gain est la **localité** (un
travail = un fichier) et la **fermeture de la couche vue**.
