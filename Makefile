## EcoSim — developer tasks
## Run `make help` for the list of targets.

SHELL := bash
PHP := php
CONSOLE := $(PHP) bin/console
PHPSTAN := $(PHP) tools/phpstan.phar
PHP_CS_FIXER := $(PHP) vendor/bin/php-cs-fixer
TWIG_CS_FIXER := $(PHP) vendor/bin/twig-cs-fixer

.DEFAULT_GOAL := help

.PHONY: help
help: ## List available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

.PHONY: install
install: ## Install PHP dependencies + PHAR tools
	composer install
	bin/install-tools.sh

.PHONY: tools
tools: ## Install standalone PHAR tools (phpstan, ...)
	bin/install-tools.sh

## ---- Quality ----------------------------------------------------------------

.PHONY: cs
cs: ## Check code style (php-cs-fixer, dry-run)
	$(PHP_CS_FIXER) check --config=.php-cs-fixer.dist.php --diff

.PHONY: cs-fix
cs-fix: ## Fix code style (php-cs-fixer)
	$(PHP_CS_FIXER) fix --config=.php-cs-fixer.dist.php

.PHONY: stan
stan: ## Static analysis (phpstan)
	$(PHPSTAN) analyse --no-progress

.PHONY: twig
twig: ## Lint Twig (syntax + twig-cs-fixer)
	$(CONSOLE) lint:twig templates
	$(TWIG_CS_FIXER) lint --config=.twig-cs-fixer.dist.php

.PHONY: twig-fix
twig-fix: ## Fix Twig style (twig-cs-fixer)
	$(TWIG_CS_FIXER) lint --fix --config=.twig-cs-fixer.dist.php

.PHONY: test
test: ## Run the test suite (phpunit)
	$(PHP) vendor/bin/phpunit

.PHONY: qa
qa: cs stan twig test ## Run the full quality gate (cs + stan + twig + tests)
