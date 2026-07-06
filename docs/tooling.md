# Outillage qualité — EcoSim

Objectif : un socle de qualité minimal mais réel dès la Phase 0-1, avec un cœur
logique (`src/Domain`) analysé strictement et découplé du framework.

## Installation

```bash
composer install     # installe aussi les outils qualité (dev)
```

## Commandes

Tout passe par le `Makefile` (ou les scripts Composer équivalents) :

| Make | Composer | Rôle |
|---|---|---|
| `make cs` | `composer cs` | Style de code (php-cs-fixer, lecture seule) |
| `make cs-fix` | `composer cs-fix` | Corrige le style |
| `make stan` | `composer stan` | Analyse statique (PHPStan) |
| `make twig` | `composer twig` | Lint Twig (syntaxe + twig-cs-fixer) |
| `make test` | `composer test` | Tests (PHPUnit) |
| `make qa` | `composer qa` | Chaîne complète : cs + stan + twig + test |

## Outils (tous via Composer, `require-dev`)

- **php-cs-fixer** — profil `@Symfony` + `@PHP84Migration`, `declare(strict_types=1)`
  et comparaisons strictes imposés. Config : `.php-cs-fixer.dist.php`.
- **twig-cs-fixer** + `bin/console lint:twig`. Config : `.twig-cs-fixer.dist.php`.
- **PHPStan** niveau 8 (`phpstan.neon`), avec les extensions **phpstan-symfony**
  et **phpstan-doctrine** auto-enregistrées par **phpstan/extension-installer**
  (elles apprennent à PHPStan la « magie » du framework : appels par réflexion,
  conteneur, métadonnées ORM). Le cœur `src/Domain` étant du PHP pur, il est de
  toute façon analysé sans faux positif.

Règle PHPStan (philosophie de l'outil) : **corriger la cause**, jamais suppresser.
Pas de `@phpstan-ignore`, pas de baseline, pas de `assert()`/`@var` pour forcer un
type, pas de cast ni d'élargissement de type pour faire taire une erreur.

## Note d'environnement (bac à sable Claude Code web)

Le développement en local et la CI GitHub installent tout normalement. Dans les
sessions Claude Code web en revanche, la politique d'egress bloque les hôtes
GitHub Releases / jsdelivr : `composer install` n'y peut pas récupérer le PHAR
« dist-only » de PHPStan. `make cs`, `make twig` et `make test` y fonctionnent ;
pour l'analyse statique on s'appuie alors sur la CI (egress libre). Ce n'est
qu'une limite du bac à sable, pas de la configuration du projet.

## CI

`.github/workflows/ci.yml` rejoue la chaîne qualité complète sur GitHub Actions
(`composer install` + `vendor/bin/…`), où l'egress n'est pas restreint.
