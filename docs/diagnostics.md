# Diagnostics

Use the product debug command to understand why a product is or is not exportable:

```bash
bin/console acseo:geo:debug-product product-slug --locale=fr_FR
```

It checks:

- current channel
- locale support
- product lookup by channel, locale, and slug
- enabled state
- channel assignment
- localized slug
- enabled variants
- GEO visibility checker result
- variant channel pricing warnings

Print the generated Markdown when the product is exportable:

```bash
bin/console acseo:geo:debug-product product-slug --locale=fr_FR --markdown
```

With the provided Makefile:

```bash
make geo-debug-product slug=product-slug locale=fr_FR
```

Use the taxon debug command to understand why a taxon is or is not exportable:

```bash
bin/console acseo:geo:debug-taxon taxon-slug --locale=fr_FR
```

It checks:

- current channel
- locale support
- taxon lookup by locale and slug
- enabled state
- localized slug
- GEO visibility checker result

Print the generated Markdown when the taxon is exportable:

```bash
bin/console acseo:geo:debug-taxon taxon-slug --locale=fr_FR --markdown
```

With the provided Makefile:

```bash
make geo-debug-taxon slug=taxon-slug locale=fr_FR
```

Use the global audit command to inspect a channel and locale:

```bash
bin/console acseo:geo:audit --channel=FASHION_WEB --locale=fr_FR
```

See [documentation examples](examples.md) for realistic output and troubleshooting cases.
