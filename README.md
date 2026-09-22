# ACSEO Sylius GEO Plugin

[![CI](https://github.com/acseo/acseo-sylius-geo-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/acseo/acseo-sylius-geo-plugin/actions/workflows/build.yaml)
[![PHP](https://img.shields.io/badge/PHP-8.2%E2%80%938.4-777BB4)](docs/compatibility.md)
[![Sylius](https://img.shields.io/badge/Sylius-2.0%20%7C%202.1%20%7C%202.2%20%7C%202.3-22B99A)](docs/compatibility.md)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.4-000000)](docs/compatibility.md)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![By ACSEO](https://img.shields.io/badge/By-ACSEO-E95420)](https://www.acseo.fr/)
[![Sylius Premium Solution Partner](https://img.shields.io/badge/Sylius-Premium%20Solution%20Partner-22B99A)](https://sylius.com/fr/partner/acseo/)

Generative Engine Optimization for Sylius. The plugin exposes a shop-aware `llm.txt` index and Markdown representations of catalog pages so AI crawlers can read accurate, public commerce data without guessing from HTML.

| | |
|--|--|
| Package | `acseo/sylius-geo-plugin` |
| Requirements | PHP 8.2–8.4 (8.3+ for Sylius 2.3), Sylius 2.0–2.3; see [compatibility](docs/compatibility.md) for Symfony combinations |
| Base | [Sylius PluginSkeleton](https://github.com/Sylius/PluginSkeleton) |

## Preview

![Sylius GEO llm.txt endpoint](docs/screenshots/llm-txt-endpoint.png)

## Features

- `llm.txt` per channel locale with products, taxons, channel information, and crawler policy.
- GEO sitemap endpoints for search and AI crawlers.
- Markdown product pages with description, brand, price, stock, attributes, variants, images, taxons, canonical URL, and optional JSON-LD.
- Markdown taxon pages with description, subcategories, and canonical URL.
- HTTP content negotiation on canonical product and taxon URLs.
- Public Shop API GEO endpoints.
- HTTP cache headers on public GEO responses: `Cache-Control` and `ETag`.
- Fine-grained configuration for catalog limits, route opt-outs, product exclusions, stock strategy, and price strategy.
- Symfony events before `llm.txt`, product Markdown, taxon Markdown, and HTTP response creation.
- Extension points for custom product, taxon, `llm.txt`, and CMS sections.
- Diagnostic and audit commands to explain product, taxon, channel, and locale export state.
- GitHub Actions CI with Composer validation, PHPUnit, Behat, PHPStan (level max), and ECS across a Sylius/Symfony compatibility matrix.

## Quick start

See the [installation guide](docs/installation.md) for Composer setup, configuration import, and route registration.

## Documentation

- [Compatibility policy](docs/compatibility.md)
- [Installation](docs/installation.md)
- [Endpoints and content negotiation](docs/endpoints.md)
- [Documentation examples](docs/examples.md)
- [Configuration](docs/configuration.md)
- [Commands](docs/commands.md)
- [Diagnostics](docs/diagnostics.md)
- [Extension points](docs/extension-points.md)
- [Local development](docs/development.md)

## About ACSEO

[![ACSEO — Projets web & mobiles](docs/screenshots/acseo-logo.png)](https://www.acseo.fr/)

Developed and maintained by [ACSEO](https://www.acseo.fr/), a French web and mobile development agency founded in 2009, with teams in Aix-en-Provence and Montpellier. ACSEO builds Symfony business applications and e-commerce platforms for startups and large companies, with services spanning UI/UX design, development, and DevOps infrastructure.

ACSEO has been a Sylius partner since 2022 and is listed as a **Premium Solution Partner** in the [official Sylius partner directory](https://sylius.com/fr/partner/acseo/). This plugin brings ACSEO’s development and SEO/GEO expertise to Sylius catalogs.

For project support or custom development, [contact ACSEO](https://www.acseo.fr/).
