# Commands

This page lists the custom commands and Makefile targets provided by the plugin.

## Symfony Commands

The console output is translated in the locale of the application (English and French are shipped).

### Audit a channel and locale

```bash
bin/console acseo_sylius_geo_plugin:audit --channel=FASHION_WEB --locale=fr_FR
```

The audit command explains the global GEO export state for one channel and locale:

- exportable products
- excluded products
- exclusion reasons
- exportable taxons
- generated URLs
- configuration warnings

Unlike `acseo_sylius_geo_plugin:debug-product`, the audit does not report variants without channel pricing. Both options are required.

The audit reads the latest `catalog.product_limit` enabled products of the channel in that locale, so disabled products and products not assigned to the channel are not listed at all; the excluded products it lists are the ones rejected by the visibility rules, without a slug or without an enabled variant.

### Debug a product export

```bash
bin/console acseo_sylius_geo_plugin:debug-product product-slug --locale=fr_FR
```

Print the generated Markdown when the product is exportable:

```bash
bin/console acseo_sylius_geo_plugin:debug-product product-slug --locale=fr_FR --markdown
```

The product debug command explains common blocking or warning cases:

- endpoint disabled by configuration
- missing localized slug
- missing enabled variants
- variants without channel pricing
- visibility checker rejection (for example an excluded taxon or attribute)

A disabled product and a product that is not assigned to the current channel are not diagnosed: the product lookup only finds enabled products of the current channel, so the command answers `Product "<slug>" was not found on channel "<code>" for locale "<locale>".` and exits with a failure status.

### Debug a taxon export

```bash
bin/console acseo_sylius_geo_plugin:debug-taxon taxon-slug --locale=fr_FR
```

Print the generated Markdown when the taxon is exportable:

```bash
bin/console acseo_sylius_geo_plugin:debug-taxon taxon-slug --locale=fr_FR --markdown
```

The taxon debug command explains common blocking cases:

- endpoint disabled by configuration
- missing localized slug
- disabled taxon
- visibility checker rejection

The debug commands work on the current channel (the only channel of the shop, or the one the Sylius channel context resolves). Use `acseo_sylius_geo_plugin:audit` to target a channel by code.

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
make tests          # PHPStan, ECS, PHPUnit and Behat
make ci             # composer validate, lint, then make tests
make ecs-fix        # fix the coding standards
make rector         # check that the code is modern (dry run)
make phpmd          # size and complexity
make deptrac        # architecture layers
make duplication    # duplicated code
make audit          # composer audit
```

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
