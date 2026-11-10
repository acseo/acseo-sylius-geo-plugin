.DEFAULT_GOAL := help

DOCKER_COMPOSE ?= docker compose
DOCKER_USER ?= "$(shell id -u):$(shell id -g)"
ENV ?= dev

RUN        ?= ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm php
RUN_NODEPS ?= ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm --no-deps php
RUN_NODE   ?= ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm --no-deps -e npm_config_cache=/tmp/.npm --entrypoint "" nodejs
PREPARE_TEST_DATABASE = $(RUN) vendor/bin/console --env=test doctrine:database:create --if-not-exists -q && $(RUN) vendor/bin/console --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration -q

##@ Environment

init: ## Install dependencies and start the test application
	@make -s docker-compose-check
	@if [ ! -e compose.override.yml ]; then \
		cp compose.override.dist.yml compose.override.yml; \
	fi
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm php composer install --no-interaction --no-scripts --no-plugins
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm nodejs
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d

composer-update: ## Update the Composer dependencies
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm php composer update --no-interaction --no-scripts --no-plugins

run: ## Start the containers
	@make -s up

debug: ## Start the containers with Xdebug
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) -f compose.yml -f compose.override.yml -f compose.debug.yml up -d

up: ## Start the containers
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d

down: ## Stop the containers
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) down

clean: ## Stop the containers and remove their volumes
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) down -v

php-shell: ## Open a shell in the php container
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec php sh

node-shell: ## Open a shell in the nodejs container
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm -i nodejs sh

node-watch: ## Rebuild the front-end assets on change
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm -i nodejs "npm run watch"

docker-compose-check:
	@$(DOCKER_COMPOSE) version >/dev/null 2>&1 || (echo "Please install docker compose binary or set DOCKER_COMPOSE=\"docker-compose\" for legacy binary" && exit 1)
	@echo "You are using \"$(DOCKER_COMPOSE)\" binary"
	@echo "Current version is \"$$($(DOCKER_COMPOSE) version)\""

cache-clear: ## Clear and warm the Symfony cache
	@$(RUN) vendor/bin/console cache:clear

screenshots: ## Regenerate the screenshots of docs/screenshots from the running test application (needs data: make load-fixtures)
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) DOCKER_COMPOSE="$(DOCKER_COMPOSE)" bash etc/screenshots.sh

##@ Database

database-init: ## Create the database and run migrations
	@$(RUN) vendor/bin/console doctrine:database:create -n --if-not-exists
	@$(RUN) vendor/bin/console doctrine:migrations:migrate -n --allow-no-migration

database-reset: ## Drop, recreate and migrate the database
	@$(RUN) vendor/bin/console doctrine:database:drop -n --force --if-exists
	@$(RUN) vendor/bin/console doctrine:database:create -n
	@$(RUN) vendor/bin/console doctrine:migrations:migrate -n --allow-no-migration

load-fixtures: ## Load the Sylius fixtures (the database is purged)
	@$(RUN) vendor/bin/console sylius:fixtures:load -n

##@ Tests

tests: ## Run PHPStan, ECS, PHPUnit and Behat
tests: phpstan ecs phpunit behat

quality: ## Run PHPStan, ECS, Rector, PHPMD and Deptrac
quality: phpstan ecs rector phpmd deptrac

phpstan: ## Run PHPStan
	@$(RUN_NODEPS) vendor/bin/phpstan analyse -c phpstan.neon

ecs: ## Check the coding standards without fixing
	@$(RUN_NODEPS) vendor/bin/ecs check

ecs-fix: ## Fix the coding standards
	@$(RUN_NODEPS) vendor/bin/ecs check --fix

phpunit: ## Run PHPUnit (creates and migrates the test database first)
	@$(PREPARE_TEST_DATABASE)
	@$(RUN) vendor/bin/phpunit --testsuite=all

behat: ## Run Behat (creates and migrates the test database first)
	@$(PREPARE_TEST_DATABASE)
	@ENV=$(ENV) DOCKER_USER=root $(DOCKER_COMPOSE) run --rm php vendor/bin/behat --strict --no-interaction

##@ Quality

ci: ## Run every check of the CI
ci: validate lint lint-twig ecs phpstan rector phpmd deptrac eslint stylelint duplication audit phpunit behat

validate: ## Validate composer.json
	@$(RUN_NODEPS) composer validate --strict

lint: ## Lint the YAML files, the Twig templates and the service container
	@$(RUN_NODEPS) vendor/bin/console lint:yaml config translations --parse-tags
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm --no-deps -e APP_ENV=test php vendor/bin/console lint:twig templates
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm --no-deps -e APP_ENV=test php vendor/bin/console lint:container

lint-twig: ## Lint the Twig templates
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm --no-deps -e APP_ENV=test php vendor/bin/console lint:twig templates

eslint: ## Lint the JavaScript assets
	@$(RUN_NODE) npx --yes eslint@9.39.1 --no-error-on-unmatched-pattern assets

stylelint: ## Lint the SCSS assets
	@$(RUN_NODE) npx --yes stylelint@16.26.1 --allow-empty-input 'assets/**/*.scss' --config .stylelintrc.json

rector: ## Check that the code is modern (dry run)
	@$(RUN_NODEPS) vendor/bin/rector process --dry-run --no-progress-bar

rector-fix: ## Modernize the code
	@$(RUN_NODEPS) vendor/bin/rector process --no-progress-bar

phpmd: ## Check the size and complexity of the code
	@$(RUN_NODEPS) vendor/bin/phpmd src text phpmd.xml

deptrac: ## Check the architecture layers
	@$(RUN_NODEPS) vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress

duplication: ## Detect duplicated code
	@$(RUN_NODE) npx --yes jscpd@4.0.5 --config .jscpd.json

audit: ## Audit the Composer dependencies (abandoned packages are reported, not failed)
	@$(RUN_NODEPS) composer audit --abandoned=report

ci-matrix: ## Replay every Sylius and Symfony combination of the CI locally (ex: make ci-matrix SYLIUS=~2.0.0 SYMFONY=^6.4 KEEP=1)
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) DOCKER_COMPOSE="$(DOCKER_COMPOSE)" SYLIUS="$(SYLIUS)" SYMFONY="$(SYMFONY)" KEEP="$(KEEP)" bash etc/ci-matrix.sh

##@ GEO

channel ?= FASHION_WEB
locale  ?= en_US

geo-audit: ## Audit the GEO export of a channel (ex: make geo-audit channel=FASHION_WEB locale=fr_FR)
	@$(RUN) vendor/bin/console acseo_sylius_geo_plugin:audit --channel=$(channel) --locale=$(locale)

geo-debug-product: slug ?= casual-coastal-cap
geo-debug-product: ## Debug a product Markdown export (ex: make geo-debug-product slug=my-product locale=en_US)
	@$(RUN) vendor/bin/console acseo_sylius_geo_plugin:debug-product $(slug) --locale=$(locale) --markdown

geo-debug-taxon: slug ?= caps/simple
geo-debug-taxon: ## Debug a taxon Markdown export (ex: make geo-debug-taxon slug=dresses locale=fr_FR)
	@$(RUN) vendor/bin/console acseo_sylius_geo_plugin:debug-taxon $(slug) --locale=$(locale) --markdown

##@ Helpers

help: ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

.PHONY: init cache-clear screenshots composer-update run debug up down clean php-shell node-shell node-watch docker-compose-check database-init database-reset load-fixtures tests phpstan ecs ecs-fix phpunit behat ci validate lint rector rector-fix phpmd deptrac duplication audit ci-matrix geo-audit geo-debug-product geo-debug-taxon help eslint stylelint quality lint-twig