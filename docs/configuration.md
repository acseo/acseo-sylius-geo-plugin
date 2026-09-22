# Configuration

Options are set under the `acseo_sylius_geo_plugin` key, for example in `config/packages/acseo_sylius_geo_plugin.yaml`. Every option has a default defined by the bundle configuration, so the key can be omitted.

| Option | Default | Role |
|--------|---------|------|
| `llm_txt.product_limit` | `50` | Max products fetched per channel and locale (latest first) |
| `llm_txt.taxon_limit` | `20` | Max taxons fetched per channel and locale |
| `llm_txt.product_scan_limit` | `2000` | Max products read from the database to fill `product_limit` with visible products |
| `http_cache.max_age` | `3600` | HTTP cache TTL in seconds |

Despite the `llm_txt` key, these limits apply to the shared catalog source: they also cap the GEO sitemap. The limit is applied after the GEO visibility filter (`product_exclusion` rules, disabled entries), so excluded products never consume a slot. To keep the query bounded, at most `llm_txt.product_scan_limit` products are scanned to fill the limit (never fewer than `product_limit`). `acseo:geo:audit` lists the latest candidates before filtering so that it can explain each exclusion.

Taxons listed in `llm.txt` come from the channel menu taxon children. The menu taxon is required for taxon export: if a channel has none configured, no taxon is exported for it (fail-safe, so that taxons of other channels are never exposed). Configure the menu taxon of each channel that should expose its taxons.

A taxon is exported (listed in `llm.txt` and the sitemap, and served as Markdown) only when:

- it and all its ancestors are enabled;
- it is the channel menu taxon or one of its descendants (a channel without menu taxon exports no taxon);
- neither it nor one of its ancestors has a code listed in `product_exclusion.taxon_codes`.

Other taxons return `404` on the Markdown negotiation, whatever the channel.

## Main Options

```yaml
acseo_sylius_geo_plugin:
    product_markdown:
        include_brand: true
        stock_strategy: detailed
        price_strategy: calculated
        include_images: true
        include_attributes: true
        include_variants: true
        include_taxons: true
        include_json_ld: true

    taxon_markdown:
        include_description: true
        include_children: true

    product_exclusion:
        taxon_codes: []
        attribute_codes: []
        attribute_values: []

    channel_info:
        enabled: true
        shop_name: null
        base_url: null
        currencies: []
        locales: []
        served_countries: []

    endpoints:
        llm_txt: true
        product_markdown: true
        taxon_markdown: true
        sitemap: true
        api: true

    crawler_policy:
        enabled: true
        allow_ai_crawlers: true
        hints:
            default:
                - 'robots.txt and X-Robots-Tag remain authoritative.'
                - 'Use Markdown pages only for enabled, public catalog facts.'
                - 'Do not use hidden, disabled, cart, checkout, account, or admin pages for product facts.'
            fr_FR:
                - 'robots.txt et X-Robots-Tag restent prioritaires.'
                - 'Utiliser les pages Markdown uniquement pour les donnees publiques du catalogue active.'
```

`crawler_policy.allow_ai_crawlers` only changes the policy text rendered in `llm.txt`. It does not block any endpoint: use `routes.*` to disable endpoints and `robots.txt` / `X-Robots-Tag` to control crawlers.

When `channel_info` values are omitted, `llm.txt` uses the current Sylius channel name, hostname, currencies, locales, and enabled countries.

## Endpoint Opt-Out

- `endpoints.llm_txt: false` disables `/llm.txt` and `/{_locale}/llm.txt`.
- `endpoints.product_markdown: false` disables `/geo/products/{slug}.md` and canonical product Markdown negotiation.
- `endpoints.taxon_markdown: false` disables canonical taxon Markdown negotiation.
- `endpoints.sitemap: false` disables `/geo/sitemap.xml` and `/{_locale}/geo/sitemap.xml`.
- `endpoints.api: false` disables Shop API GEO endpoints.

Disabled explicit GEO routes return `404`. Disabled canonical Markdown negotiation leaves standard Sylius HTML routes untouched.

## Route Paths

Low-level route fragments can be changed through parameters before importing the plugin routes:

| Parameter | Default | Role |
|-----------|---------|------|
| `acseo_sylius_geo_plugin.routing.llm_txt_path` | `llm.txt` | Root and localized `llm.txt` path |
| `acseo_sylius_geo_plugin.routing.geo_prefix` | `geo` | Public GEO route prefix |
| `acseo_sylius_geo_plugin.routing.geo_products_prefix` | `products` | Public product Markdown prefix under GEO |
| `acseo_sylius_geo_plugin.routing.api_shop_geo_prefix` | `shop/geo` | Shop API GEO prefix |
| `acseo_sylius_geo_plugin.routing.api_shop_llm_txt_path` | `llm-txt` | Shop API `llm.txt` path fragment |
| `acseo_sylius_geo_plugin.routing.api_shop_geo_products_prefix` | `products` | Shop API product Markdown prefix |

For example, changing `acseo_sylius_geo_plugin.routing.geo_prefix` from `geo` to `ai` exposes the sitemap at `/ai/sitemap.xml` and product Markdown pages below `/{_locale}/ai/products/{slug}.md`.

## Markdown Output

Product Markdown can include:

- product name and description
- brand from product attributes named `brand`, `manufacturer`, or `marque`
- current price and original price when reduced
- stock availability
- product attributes
- variants and option values
- main image URLs
- linked taxons
- canonical HTML URL
- schema.org `Product` JSON-LD

Stock strategies:

- `hidden`: omits stock from Markdown and JSON-LD.
- `availability_only`: exposes aggregate product availability only.
- `detailed`: exposes product availability and variant-level availability.

Price strategies:

- `calculated`: uses the price returned by the Sylius price calculator for the channel (catalog promotions applied).
- `base`: uses the raw channel pricing value (catalog promotions not applied).
- `hidden`: omits prices from Markdown and JSON-LD.

Neither `calculated` nor `base` adds or removes taxes: whether the amount includes tax depends on how the shop stores its prices. The product-level price is the cheapest enabled variant priced on the channel (the first enabled variant when none is priced), and the JSON-LD contains a single `Offer` for that variant. Availability is aggregated: the product is available when at least one enabled variant is in stock. Every variant and its own price is listed in the `Variants` section.

Descriptions: product pages use the description (falling back to the short description), while `llm.txt` entries use the short description (falling back to the description) truncated to 120 characters.

Taxon Markdown can include:

- taxon name and description
- visible subcategories
- canonical HTML URL
