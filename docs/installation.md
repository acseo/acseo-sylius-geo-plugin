# Installation

Install the plugin:

```bash
composer require acseo/sylius-geo-plugin
```

Import the plugin configuration:

```yaml
# config/packages/acseo_sylius_geo_plugin.yaml
imports:
    - { resource: "@ACSEOSyliusGeoPlugin/config/config.yaml" }
```

Import the routes:

```yaml
# config/routes/acseo_sylius_geo_plugin.yaml
acseo_sylius_geo_plugin_shop:
    resource: "@ACSEOSyliusGeoPlugin/config/routes/shop.yaml"
    prefix: /{_locale}
    requirements:
        _locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$

acseo_sylius_geo_plugin_shop_root:
    resource: "@ACSEOSyliusGeoPlugin/config/routes/shop_root.yaml"

acseo_sylius_geo_plugin_api_shop:
    resource: "@ACSEOSyliusGeoPlugin/config/routes/api_shop.yaml"
    prefix: '%sylius.security.api_route%'
```

Each file matches a Sylius section, so you can import only what you need:

| File | Section | Mounted under | Routes |
|------|---------|---------------|--------|
| `shop.yaml` | Shop | `/{_locale}` | `llm.txt`, `geo/sitemap.xml`, `geo/products/{slug}.md` |
| `shop_root.yaml` | Shop | no prefix | `/llm.txt` (redirects to the channel default locale), `/geo/sitemap.xml` |
| `api_shop.yaml` | Shop API | `%sylius.security.api_route%` | JSON GEO endpoints |

Shop API GEO routes are public. Locale defaults to the channel default when `locale` is omitted.

## Production deployment

Run Symfony with `APP_ENV=prod` and `APP_DEBUG=0`. With debug enabled, error pages expose exception messages, which would let a client distinguish an unknown product from one that exists but is hidden from GEO export.
