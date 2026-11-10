# Installation

Check the [compatibility policy](compatibility.md) before installing. PHP 8.3 or higher and the XMLWriter extension are required; the supported Symfony versions depend on the Sylius minor.

Install the plugin:

```bash
composer require acseo/sylius-geo-plugin
```

With Symfony Flex, `composer require` registers the bundle. Check that the line below is present in `config/bundles.php`, and add it yourself if your application does not use Flex:

```php
// config/bundles.php
return [
    // ...
    ACSEO\SyliusGeoPlugin\ACSEOSyliusGeoPlugin::class => ['all' => true],
];
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
| `shop.yaml` | Shop | `/{_locale}` | `llms.txt`, `geo/sitemap.xml`, `geo/products/{slug}.md` |
| `shop_root.yaml` | Shop | no prefix | `/llms.txt` (redirects to the channel default locale), `/geo/sitemap.xml` |
| `api_shop.yaml` | Shop API | `%sylius.security.api_route%` | `shop/geo/llm-txt`, `shop/geo/products/{slug}` |

Shop API GEO routes are public. The locale defaults to the channel default locale when `locale` is omitted.

The plugin has no database table, no migration and no front-end asset.

Clear the cache:

```bash
bin/console cache:clear
```

## Translations

The plugin ships English and French translations of its console output in the `messages` domain, under the `acseo_sylius_geo_plugin` key. The texts written in `llms.txt` and in the Markdown pages are English and live in the `acseo_sylius_geo_plugin_output` domain; override a key of either domain in your application `translations/` directory (see [configuration](configuration.md#output-texts)).

## Production deployment

Run Symfony with `APP_ENV=prod` and `APP_DEBUG=0`. The plugin answers `404` for an unknown product and for a product that exists but is hidden from GEO export, with no message that would tell them apart; debug error pages expose technical details that must not be public.
