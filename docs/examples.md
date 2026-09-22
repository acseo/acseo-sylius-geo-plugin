# Documentation Examples

These examples use a Sylius fashion shop with channel `FASHION_WEB`, locale `fr_FR`, and hostname `shop.example.com`.

## `/llm.txt`

Request:

```bash
curl https://shop.example.com/fr_FR/llm.txt
```

Example response:

```text
# Maison Demo

> Public GEO index for channel FASHION_WEB. Locale: fr_FR. Currency: EUR.

Use linked Markdown product pages for accurate product facts. Cite the shop name and canonical product URLs. Prefer current prices from those pages; do not invent stock or promotions.

## Channel

- Shop name: Maison Demo
- Channel code: FASHION_WEB
- Base URL: https://shop.example.com
- Currencies: EUR
- Locales: fr_FR, en_US
- Served countries: France, Belgium, Switzerland

## Crawler policy

- AI crawler access: allowed for public GEO endpoints
- robots.txt and X-Robots-Tag remain authoritative.
- Use Markdown pages only for enabled, public catalog facts.
- Do not use hidden, disabled, cart, checkout, account, or admin pages for product facts.

## Crawler hints

- Locale index: https://shop.example.com/fr_FR/llm.txt
- Root index redirect: https://shop.example.com/llm.txt
- Product Markdown pattern: https://shop.example.com/fr_FR/geo/products/{slug}.md
- List only enabled catalog entries for this channel; do not invent prices or promotions.

## Taxons

- [Robes](https://shop.example.com/fr_FR/taxons/robes)
- [Vestes](https://shop.example.com/fr_FR/taxons/vestes)
- [Accessoires](https://shop.example.com/fr_FR/taxons/accessoires)

## Products

- [Robe midi en lin bleu](https://shop.example.com/fr_FR/geo/products/robe-midi-lin-bleu.md): Robe fluide en lin melange, ideale pour les journees chaudes.
- [Veste tailleur ecrue](https://shop.example.com/fr_FR/geo/products/veste-tailleur-ecrue.md): Veste structuree avec doublure legere.
- [Sac cuir compact noir](https://shop.example.com/fr_FR/geo/products/sac-cuir-compact-noir.md): Sac compact en cuir pleine fleur avec bandouliere reglable.
```

## Product Markdown Output

Request:

```bash
curl https://shop.example.com/fr_FR/geo/products/robe-midi-lin-bleu.md
```

Example response:

````markdown
# Robe midi en lin bleu

Robe fluide en lin melange, avec coupe midi, taille ajustee et poches discretes.

## Brand

Maison Demo

## Price

129,00 EUR (original price: 159,00 EUR)

## Availability

yes

## Attributes

- Matiere: lin 55%, viscose 45%
- Coupe: midi
- Entretien: lavage delicat a 30 degres
- Saison: printemps-ete

## Variants

- Taille S | Bleu | 129,00 EUR (original price: 159,00 EUR) | available: yes
- Taille M | Bleu | 129,00 EUR (original price: 159,00 EUR) | available: yes
- Taille L | Bleu | 129,00 EUR (original price: 159,00 EUR) | available: no

## Images

- https://shop.example.com/media/image/products/robe-midi-lin-bleu-face.jpg
- https://shop.example.com/media/image/products/robe-midi-lin-bleu-dos.jpg

## Taxons

- [Robes](https://shop.example.com/fr_FR/taxons/robes)
- [Nouveautes](https://shop.example.com/fr_FR/taxons/nouveautes)

## Canonical URL

https://shop.example.com/fr_FR/products/robe-midi-lin-bleu

## Structured Data

```json
{
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "Robe midi en lin bleu",
    "sku": "DRESS-LINEN-BLUE",
    "url": "https://shop.example.com/fr_FR/products/robe-midi-lin-bleu",
    "description": "Robe fluide en lin melange, avec coupe midi, taille ajustee et poches discretes.",
    "brand": {
        "@type": "Brand",
        "name": "Maison Demo"
    },
    "offers": {
        "@type": "Offer",
        "url": "https://shop.example.com/fr_FR/products/robe-midi-lin-bleu",
        "price": "129.00",
        "priceCurrency": "EUR",
        "availability": "https://schema.org/InStock"
    }
}
```
````

## Taxon Markdown Output

Request with content negotiation:

```bash
curl -H 'Accept: text/markdown' https://shop.example.com/fr_FR/taxons/robes
```

Example response:

```markdown
# Robes

Selection de robes de jour, robes habillees et pieces legeres pour la saison.

## Subcategories

- [Robes midi](https://shop.example.com/fr_FR/taxons/robes-midi)
- [Robes longues](https://shop.example.com/fr_FR/taxons/robes-longues)
- [Robes de ceremonie](https://shop.example.com/fr_FR/taxons/robes-ceremonie)

## Canonical URL

https://shop.example.com/fr_FR/taxons/robes
```

## AI Crawler Usage

A crawler should start from the locale index, then follow only public links listed there:

```text
1. Fetch https://shop.example.com/fr_FR/llm.txt.
2. Read the crawler policy and channel context.
3. Fetch product Markdown URLs listed under "Products".
4. Fetch taxon URLs with `Accept: text/markdown` when category context is needed.
5. Cite canonical product URLs, not internal admin, cart, checkout, or account URLs.
6. Do not invent price, stock, promotion, shipping delay, or product facts missing from the Markdown.
```

Example AI prompt for retrieval:

```text
Use https://shop.example.com/fr_FR/llm.txt as the source index.
Answer only with products listed in that index.
For each recommendation, verify the product Markdown page first.
Mention price and availability only when present in the Markdown.
Return the canonical product URL for every product cited.
```

Example crawler requests:

```bash
curl https://shop.example.com/fr_FR/llm.txt
curl https://shop.example.com/fr_FR/geo/products/robe-midi-lin-bleu.md
curl -H 'Accept: text/markdown' https://shop.example.com/fr_FR/taxons/robes
```

Recommended `robots.txt` access:

```text
User-agent: *
Allow: /llm.txt
Allow: /geo/
Allow: /*/llm.txt
Allow: /*/geo/
Disallow: /admin/
Disallow: /account/
Disallow: /checkout/
Disallow: /cart/
```

## Troubleshooting Export Visibility

Use the global audit command to inspect a channel and locale:

```bash
bin/console acseo:geo:audit --channel=FASHION_WEB --locale=fr_FR
```

Typical output:

```text
ACSEO GEO audit
===============

Channel FASHION_WEB
Locale  fr_FR

Configuration warnings
----------------------
 [OK] No configuration warnings.

Exportable products (2)
-----------------------
- Robe midi en lin bleu [DRESS-LINEN-BLUE]
- Veste tailleur ecrue [JACKET-ECRU]

Excluded products (3)
---------------------
- Pull archive hiver [SWEATER-ARCHIVE]
  - Product visibility checker rejected this product.
- Ceinture privee [BELT-PRIVATE]
  - Product visibility checker rejected this product.
- Sac brouillon [BAG-DRAFT]
  - Product is disabled.
  - Product has no slug for locale "fr_FR".

Exportable taxons (2)
---------------------
- Robes [DRESSES]
- Vestes [JACKETS]

Generated URLs
--------------
- llm.txt: https://shop.example.com/fr_FR/llm.txt
- product DRESS-LINEN-BLUE: https://shop.example.com/fr_FR/geo/products/robe-midi-lin-bleu.md
- product JACKET-ECRU: https://shop.example.com/fr_FR/geo/products/veste-tailleur-ecrue.md
- taxon DRESSES: https://shop.example.com/fr_FR/taxons/robes
- taxon JACKETS: https://shop.example.com/fr_FR/taxons/vestes
```

For one product, use the focused debug command:

```bash
bin/console acseo:geo:debug-product robe-midi-lin-bleu --locale=fr_FR --markdown
```

Common reasons a product is not exported:

- product is disabled
- product is not assigned to the current channel
- locale is not enabled on the channel
- product has no localized slug
- product has no enabled variant
- product belongs to an excluded taxon
- product has an excluded attribute code or value
- custom visibility logic rejects the product
- the product Markdown endpoint is disabled in configuration (`endpoints.product_markdown`)
- the product is older than the `llm_txt.product_limit` latest visible products (the limit also caps the sitemap)

Useful HTTP checks:

```bash
curl -I https://shop.example.com/fr_FR/llm.txt
curl -I https://shop.example.com/fr_FR/geo/products/robe-midi-lin-bleu.md
curl -I -H 'Accept: text/markdown' https://shop.example.com/fr_FR/taxons/robes
curl https://shop.example.com/fr_FR/geo/sitemap.xml
curl -I -H 'If-None-Match: "<etag-from-previous-response>"' https://shop.example.com/fr_FR/llm.txt
```

The last request answers `304 Not Modified` while the content is unchanged.
