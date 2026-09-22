# Commands

This page lists the custom commands and Makefile targets provided by the plugin.

## Symfony Commands

### Audit a channel and locale

```bash
bin/console acseo:geo:audit --channel=FASHION_WEB --locale=fr_FR
```

The audit command explains the global GEO export state for one channel and locale:

- exportable products
- excluded products
- exclusion reasons
- exportable taxons
- generated URLs
- configuration warnings

Unlike `acseo:geo:debug-product`, the audit does not report variants without channel pricing.

### Debug a product export

```bash
bin/console acseo:geo:debug-product product-slug --locale=fr_FR
```

Print the generated Markdown when the product is exportable:

```bash
bin/console acseo:geo:debug-product product-slug --locale=fr_FR --markdown
```

The product debug command explains common blocking or warning cases:

- route disabled by configuration
- missing localized slug
- disabled product
- product not assigned to the current channel
- missing enabled variants
- variants without channel pricing
- visibility checker rejection

### Debug a taxon export

```bash
bin/console acseo:geo:debug-taxon taxon-slug --locale=fr_FR
```

Print the generated Markdown when the taxon is exportable:

```bash
bin/console acseo:geo:debug-taxon taxon-slug --locale=fr_FR --markdown
```

The taxon debug command explains common blocking cases:

- route disabled by configuration
- missing localized slug
- disabled taxon
- visibility checker rejection

## Make Targets

Run `make` with no argument to list every target with its description.

### GEO commands

```bash
make geo-audit channel=FASHION_WEB locale=fr_FR
make geo-debug-product slug=product-slug locale=fr_FR
make geo-debug-taxon slug=taxon-slug locale=fr_FR
```

The debug Make targets print the Markdown preview by default by passing `--markdown` to the Symfony commands.

### Quality checks

```bash
make ci             # validate, lint, ecs, phpstan, phpunit, behat
make ecs            # check the coding standards
make ecs-fix        # fix the coding standards
make phpstan
make phpunit
make behat          # creates and migrates the test database first
```

The GitHub Actions workflow runs `make ci` across the Sylius/Symfony compatibility matrix.

### Local environment

```bash
make init           # install dependencies, build assets and start the containers
make up
make down
make php-shell
```

### Database and fixtures

```bash
make database-init
make database-reset
make load-fixtures
```
