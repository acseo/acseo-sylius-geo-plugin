# Upgrade

## From 0.1 to 0.2

The `acseo_sylius_geo_plugin.http_cache.application_cache` option is removed. It had no effect. Delete it from your configuration, otherwise the container fails to compile with an unrecognized option error:

```yaml
acseo_sylius_geo_plugin:
    http_cache:
        application_cache: false # remove this line
```

The `llm_txt` limits move to a `catalog` node, because they also cap the GEO sitemap. Rename the key:

```yaml
acseo_sylius_geo_plugin:
    catalog: # was llm_txt
        product_limit: 50
        taxon_limit: 20
        product_scan_limit: 2000
```

`llm.txt` no longer publishes the channel code by default. To keep it, enable `channel_info.include_channel_code`:

```yaml
acseo_sylius_geo_plugin:
    channel_info:
        include_channel_code: true
```

`ProductMarkdownGenerator` now takes a required `TaxonGeoVisibilityCheckerInterface` as fourth argument. Services wired by the container are not affected; pass the taxon visibility checker service when you instantiate or decorate the generator yourself.

The index moves from `llm.txt` to `llms.txt`, the path that crawlers and AI tools look for: `/llms.txt` and `/{_locale}/llms.txt`. Update the links that point to the old path, for example in `robots.txt`. To keep the previous path, override the routing parameter:

```yaml
parameters:
    acseo_sylius_geo_plugin.routing.llm_txt_path: 'llm.txt'
```

PHP 8.2 is no longer supported: upgrade to PHP 8.3 or 8.4 before installing 0.2.
