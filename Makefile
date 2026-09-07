HOST_UID := $(shell id -u)
HOST_GID := $(shell id -g)
export HOST_UID
export HOST_GID

DC   = docker compose
EXEC = $(DC) exec -T --user $(HOST_UID):$(HOST_GID)
PHP  = $(EXEC) php
NODE = $(EXEC) node
CONSOLE = $(PHP) php bin/console

.DEFAULT_GOAL := help
.PHONY: help setup up down shell check config-check fix phpcs phpcs-fix phpstan rector rector-fix test test-db migrate reset fixtures types fe-check fe-test fe-fix

help: ## List the available targets
	@grep -E '^[a-z-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

## --- environment ---

setup: ## Fresh clone: containers, dependencies, database and fixtures. Drops any data already there
	@$(MAKE) --no-print-directory up
	$(PHP) composer install --no-interaction
	@$(MAKE) --no-print-directory reset

up: ## Start the containers and wait until they are healthy
	$(DC) up -d --wait
	@$(DC) ps --format '{{.Service}}\t{{.Status}}'

down: ## Stop the containers
	$(DC) down

shell: ## Shell in the php container
	$(DC) exec --user $(HOST_UID):$(HOST_GID) php bash

## --- quality ---

check: phpcs phpstan rector config-check test types fe-check ## The whole gate, backend and frontend

phpcs: ## Coding standard (Doctrine + Slevomat)
	$(PHP) vendor/bin/phpcs

fix: rector-fix phpcs-fix fe-fix ## Apply every automatic fix, backend and frontend

phpcs-fix:
	$(PHP) vendor/bin/phpcbf

phpstan:
	$(PHP) vendor/bin/phpstan analyse --no-progress --memory-limit=256M

rector: ## Rector, dry run
	$(PHP) vendor/bin/rector process --dry-run --clear-cache --no-progress-bar

rector-fix: ## Rector fix
	$(PHP) vendor/bin/rector process --clear-cache --no-progress-bar

config-check:
	$(PHP) php tools/check-config.php

test-db: ## Create the test database and migrate it; PHPUnit loads the fixtures itself
	$(CONSOLE) doctrine:database:create --env=test --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --env=test --no-interaction

test: test-db
	$(PHP) vendor/bin/phpunit

types: ## Regenerate frontend/src/api/schema/schema.d.ts from the OpenAPI document; fail if it differs from the committed file
	$(CONSOLE) cache:pool:clear --all
	$(CONSOLE) api:openapi:export --output=var/openapi.json
	$(NODE) npx openapi-typescript ../var/openapi.json -o src/api/schema/schema.d.ts
	git diff --exit-code -- frontend/src/api/schema/schema.d.ts || (echo 'schema.d.ts differs from the API: commit the regenerated file, never edit it by hand' && exit 1)

fe-check: ## tsc + ESLint + Vitest
	$(NODE) npx tsc --noEmit
	$(NODE) npx eslint .
	$(NODE) npx vitest run

fe-test: ## Vitest only
	$(NODE) npx vitest run

fe-fix: ## ESLint fix
	$(NODE) npx eslint . --fix

## --- data ---

migrate: ## Run the migrations and validate the schema
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) doctrine:schema:validate

reset: ## Empty the database, migrate and load all fixtures (test + demo data)
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	@$(MAKE) --no-print-directory migrate
	@$(MAKE) --no-print-directory fixtures

fixtures:
	$(CONSOLE) doctrine:fixtures:load --no-interaction
