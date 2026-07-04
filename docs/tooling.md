# Outillage qualité — EcoSim

Objectif : un socle de qualité minimal mais réel dès la Phase 0-1, avec un cœur
logique (`src/Domain`) analysé strictement et découplé du framework.

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

## Outils installés

- **php-cs-fixer** (`vendor/bin`, via Composer) — profil `@Symfony` + `@PHP84Migration`,
  `declare(strict_types=1)` et comparaisons strictes imposés. Config : `.php-cs-fixer.dist.php`.
- **twig-cs-fixer** (`vendor/bin`, via Composer). Config : `.twig-cs-fixer.dist.php`.
- **PHPStan** (`tools/phpstan.phar`, niveau 8). Config : `phpstan.neon`.

## PHPStan : pourquoi un PHAR et pas Composer

`phpstan/phpstan` est distribué en paquet « dist-only » servi depuis GitHub
Releases, **bloqué par la politique d'egress du bac à sable** de développement
(comme `codeload.github.com`, l'API zipball GitHub et `cdn.jsdelivr.net`). On
l'installe donc via `bin/install-tools.sh`, qui récupère le PHAR par un `git
clone` shallow du dépôt de release (les clones git, eux, passent le proxy).

Le PHAR n'est pas versionné (voir `.gitignore`) ; exécuter `make tools` (ou
`bin/install-tools.sh`) le récupère. Version épinglée dans le script.

## Différé (à réactiver quand l'egress le permet)

- **Extensions PHPStan** `phpstan-symfony` / `phpstan-doctrine` / `phpstan-strict-rules` :
  elles requièrent `phpstan/phpstan` comme dépendance Composer (donc son dist
  bloqué). En attendant, `src/Kernel.php` (appelé par réflexion par
  `MicroKernelTrait`) est exclu de l'analyse plutôt que suppressé en ligne. Le
  cœur `src/Domain` étant du PHP pur sans magie framework, il est analysé sans
  faux positifs.
- **PHPMD** : incompatible avec Symfony 8 en dépendance Composer (sa dépendance
  `pdepend` plafonne `symfony/config` à v7) et son PHAR est sur GitHub Releases
  (bloqué). Couverture partiellement assurée par `php-cs-fixer` + le futur
  `phpstan-strict-rules`.

## CI

`.github/workflows/ci.yml` rejoue la chaîne qualité sur GitHub Actions, où
l'egress n'est pas restreint : PHPStan (+ extensions) et les linters y tournent
via `shivammathur/setup-php`.
