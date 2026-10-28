# Changelog

All notable changes to this package are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and releases use [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - Unreleased

### Security

- The taxons section of a product Markdown export only lists taxons that pass the taxon visibility checker.
- `product_exclusion.taxon_codes` also excludes the products of descendant taxons.
- The Markdown negotiation subscribers run after the security firewall.
- `product_exclusion.taxon_codes` also applies to the product main taxon.
- `llm.txt` only renders the sections of providers whose `supports()` accepts the channel and locale.
- GEO responses to authenticated requests are `private` and keep their cookies, so a shared cache cannot serve them to other visitors.
- With `channel_info.enabled: false`, the `llm.txt` summary no longer reveals the channel code, the platform name or the channel locales.
- `llm.txt` no longer publishes the internal channel code unless `channel_info.include_channel_code` is enabled.

### Added

- `channel_info.include_channel_code` option, disabled by default, to publish the channel code in `llm.txt`.

### Removed

- Support for PHP 8.2, to align with the other ACSEO plugins; PHP 8.3 or 8.4 is required. See [UPGRADE](UPGRADE.md).
- The unused `acseo_sylius_geo_plugin.http_cache.application_cache` option; see [UPGRADE](UPGRADE.md).

### Fixed

- Select attributes show the labels of their choices instead of the stored keys in product Markdown and JSON-LD, and `product_exclusion.attribute_values` also matches those labels.
- A `Cache-Control` header set by a `GeoResponseCreationEvent` listener is fully replaced by the plugin cache policy, as documented, instead of keeping directives such as `no-store` next to `public`.
- A scalar `crawler_policy.hints` value is rejected with a configuration error instead of a PHP `TypeError`.
- Markdown negotiation only applies to the Sylius `sylius_shop_product_show` and `sylius_shop_product_index` routes, so other routes such as `/api/products/{id}` are no longer answered with `406`.
- The shop name of the `llm.txt` channel section is sanitized.
- The channel taxon list is queried once per channel and locale.
- The HTML responses of the canonical product and taxon pages carry `Vary: Accept` when Markdown negotiation is enabled.
- Section and CMS page providers of the host application are tagged automatically when autoconfiguration is enabled, as documented.
- The installation guide ends with clearing the cache.
- `make geo-debug-product` and `make geo-debug-taxon` read `slug=` as documented.
- The documentation references the `endpoints.*` options and lists the Shop API routes.
- The installation guide shows the bundle registration in `config/bundles.php`.

### Changed

- The default index path is `llms.txt` instead of `llm.txt`, following the llmstxt.org convention: `/llms.txt` and `/{_locale}/llms.txt`; see [UPGRADE](UPGRADE.md) to keep the previous path.
- The `llm_txt.product_limit`, `llm_txt.taxon_limit` and `llm_txt.product_scan_limit` options move to `catalog.*`, since they also cap the GEO sitemap; see [UPGRADE](UPGRADE.md).
- `ProductMarkdownGenerator` requires its `TaxonGeoVisibilityCheckerInterface` argument, so a manual instantiation cannot skip the taxon exclusion rules.
- The `llm.txt` summary names the channel as `Channel: <code>.` when the code is published, and the title falls back to the hostname instead of the channel code.
- The CI runs PHPStan in a dedicated step on Symfony 7.4 only.
- The CI token is read-only, the GitHub Actions are pinned to commit SHAs and Dependabot updates them weekly.
- `make ci-matrix` replays the Sylius and Symfony combinations of the CI locally.
- The Docker host ports can be changed with `HTTP_PORT`, `MYSQL_PORT` and `MAILHOG_PORT`.
- The coding standard also covers the unit and integration tests.
- `SECURITY.md` describes private vulnerability reporting.
- `composer.json` declares the `support.issues` and `support.source` links.

## [0.1.0] - 2026-10-09

### Added

- `llm.txt` per channel locale with products, taxons, channel information and crawler policy.
- GEO sitemap endpoints for search and AI crawlers.
- Markdown product and taxon pages, served on dedicated routes and through HTTP content negotiation on canonical URLs.
- Public Shop API GEO endpoints.
- `Cache-Control` and `ETag` headers on public GEO responses.
- Configuration for catalog limits, route opt-outs, product exclusions, stock strategy and price strategy.
- Events and extension points for `llm.txt`, product and taxon Markdown and HTTP responses.
- Diagnostic and audit commands for products, taxons, channels and locales.
