# Security policy

## Supported versions

Security fixes are released for the latest minor version only.

## Reporting a vulnerability

Please do not open a public issue, pull request or discussion for a security problem.

Report it privately through GitHub: open the **Security** tab of the repository and choose **Report a vulnerability** ([direct link](https://github.com/acseo/acseo-sylius-geo-plugin/security/advisories/new)).

Include, when you can:

- the plugin, Sylius, Symfony and PHP versions;
- the endpoint involved (`llms.txt`, GEO sitemap, product or taxon Markdown, Shop API) and the relevant configuration;
- the steps to reproduce, and the impact you observed.

We will acknowledge your report, keep you informed of the progress and credit you in the release notes if you wish.

## Scope

The plugin publishes read-only catalog data for search and AI crawlers. These areas are the most sensitive:

- visibility rules: a disabled product, a product outside the channel or its availability window, a taxon outside the channel menu, or an entry excluded by `product_exclusion` must never be exported, whatever the endpoint;
- channel and locale isolation: an endpoint must only expose the catalog of the current channel, in a locale enabled on that channel;
- HTTP caching: a response computed for a logged in user must never be stored by a shared cache, and a public response must not carry cookies;
- output encoding of catalog data in Markdown, JSON-LD and the XML sitemap.

### Known design choices

These are intentional and not considered vulnerabilities:

- Every GEO endpoint is public and answers anonymous requests: it only exposes catalog data that the shop already publishes.
- A product or taxon that cannot be exported answers `404`, so that the reason is not revealed.
- Markdown negotiated on the canonical product and taxon URLs is public and relies on shared caches honouring `Vary: Accept`; see [endpoints](docs/endpoints.md).

Values set by the integrator in the application configuration, and code registered through the extension points, are trusted. Vulnerabilities in Sylius, Symfony or other dependencies should be reported to their own maintainers.
