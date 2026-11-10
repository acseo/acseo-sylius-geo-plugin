# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - Unreleased

### Added

- `llms.txt` per channel locale, with a root `/llms.txt` redirect to the channel default locale, and GEO sitemap endpoints.
- Markdown product and taxon pages, and HTTP content negotiation on canonical URLs (`Accept: text/markdown`).
- Public Shop API GEO endpoints and HTTP cache headers (`Cache-Control`, `ETag`, conditional requests).
- Visibility rules by channel, taxon subtree and attribute, and configuration of catalog limits, stock and price strategies.
- Symfony events, extension points for custom sections and replaceable services behind interfaces.
- Diagnostic and audit commands, with English and French translations.
