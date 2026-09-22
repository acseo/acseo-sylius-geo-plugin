.DEFAULT_GOAL := help

DOCKER_COMPOSE ?= docker compose
DOCKER_USER ?= "$(shell id -u):$(shell id -g)"
ENV ?= dev

RUN       ?= ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm php
BEHAT_RUN ?= ENV=$(ENV) $(DOCKER_COMPOSE) exec -T -u root php

##@ Environment

init: ## Install dependencies and start the test application
	@make -s docker-compose-check
	@if [ ! -e compose.override.yml ]; then \
		cp compose.override.dist.yml compose.override.yml; \
	fi
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm php composer install --no-interaction --no-scripts --no-plugins
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm nodejs
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d

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

##@ Quality

ci: ## Run every check of the CI
ci: validate lint ecs phpstan phpunit behat

validate: ## Validate composer.json
	@$(RUN) composer validate --strict --no-check-all

lint: ## Lint YAML files and the service container
	@$(RUN) vendor/bin/console lint:yaml config --parse-tags
	@$(RUN) vendor/bin/console lint:container

ecs: ## Check the coding standards without fixing
	@$(RUN) vendor/bin/ecs check

ecs-fix: ## Fix the coding standards
	@$(RUN) vendor/bin/ecs check --fix

phpstan: ## Run PHPStan
	@$(RUN) vendor/bin/phpstan analyse

phpunit: ## Run PHPUnit (creates and migrates the test database first)
	@mkdir -p etc/build
	@$(RUN) vendor/bin/console --env=test doctrine:database:create --if-not-exists
	@$(RUN) vendor/bin/console --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration
	@$(RUN) vendor/bin/phpunit --colors=always

behat: ## Run Behat (creates and migrates the test database first)
	@mkdir -p etc/build
	@$(RUN) vendor/bin/console --env=test doctrine:database:create --if-not-exists
	@$(RUN) vendor/bin/console --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration
	@if [ -n "$(BEHAT_RUN)" ]; then ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up -d php chrome; fi
	@$(BEHAT_RUN) php -d memory_limit=1G vendor/bin/behat --strict --no-interaction --colors

##@ GEO

channel ?= FASHION_WEB
slug_product    ?= casual-coastal-cap
slug_taxon    ?= caps/simple
locale  ?= en_US

geo-audit: ## Audit the GEO export of a channel (ex: make geo-audit channel=FASHION_WEB locale=fr_FR)
	@$(RUN) vendor/bin/console acseo:geo:audit --channel=$(channel) --locale=$(locale)

geo-debug-product: ## Debug a product Markdown export (ex: make geo-debug-product slug=my-product locale=en_US)
	@$(RUN) vendor/bin/console acseo:geo:debug-product $(slug_product) --locale=$(locale) --markdown

geo-debug-taxon: ## Debug a taxon Markdown export (ex: make geo-debug-taxon slug=dresses locale=fr_FR)
	@$(RUN) vendor/bin/console acseo:geo:debug-taxon $(slug_taxon) --locale=$(locale) --markdown

##@ Helpers

help: ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

.PHONY: init up down clean php-shell node-shell node-watch docker-compose-check database-init database-reset load-fixtures ci validate lint ecs ecs-fix phpstan phpunit behat geo-audit geo-debug-product geo-debug-taxon help
