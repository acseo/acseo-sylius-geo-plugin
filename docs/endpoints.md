# Endpoints and Content Negotiation

## Public Endpoints

| Method | Path | Response |
|--------|------|----------|
| `GET` | `/llm.txt` | `302` to `/{channelDefaultLocale}/llm.txt` |
| `GET` | `/{_locale}/llm.txt` | `200` `text/plain` |
| `GET` | `/geo/sitemap.xml` | `200` `application/xml` |
| `GET` | `/{_locale}/geo/sitemap.xml` | `200` `application/xml` |
| `GET` | `/{_locale}/geo/products/{slug}.md` | `200` `text/markdown` or `404` |
| `GET` | `/{_locale}/products/{slug}` with `Accept: text/markdown` | `200` `text/markdown`, `406`, or standard Sylius HTML |
| `GET` | `/{_locale}/taxons/{slug}` with `Accept: text/markdown` | `200` `text/markdown`, `406`, or standard Sylius HTML |
| `GET` | `/api/v2/shop/geo/llm-txt` | `200` `text/plain`, optional `?locale=` |
| `GET` | `/api/v2/shop/geo/products/{slug}` | `200` `text/markdown`, optional `?locale=`, or `404` |

Successful GEO responses use public HTTP cache headers:

```http
Cache-Control: public, max-age=3600, must-revalidate
ETag: "{sha256-body-hash}"
```

No `Last-Modified` header is sent: the body is generated per request, so `ETag` is the only validator. A request with a matching `If-None-Match` header receives `304 Not Modified` without a body.

The GEO sitemap lists the Markdown URL (`/{_locale}/geo/products/{slug}.md`) for products and the canonical HTML URL for taxons.

## `robots.txt` Guidance

Keep `robots.txt` explicit for GEO endpoints. Search and AI crawlers should be able to discover the public index and generated Markdown surfaces, while private areas stay blocked by the shop rules.

Recommended baseline:

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

If you maintain separate rules for known AI crawlers, keep the same explicit GEO access:

```text
User-agent: GPTBot
Allow: /llm.txt
Allow: /geo/
Allow: /*/llm.txt
Allow: /*/geo/

User-agent: ChatGPT-User
Allow: /llm.txt
Allow: /geo/
Allow: /*/llm.txt
Allow: /*/geo/

User-agent: PerplexityBot
Allow: /llm.txt
Allow: /geo/
Allow: /*/llm.txt
Allow: /*/geo/
```

`robots.txt` remains authoritative. The crawler policy rendered in `llm.txt` should repeat the same intent, but it does not replace crawl rules enforced by robots directives or `X-Robots-Tag`.

## Content Negotiation

Canonical product and taxon URLs return Markdown when the request explicitly asks for `text/markdown`. Product and taxon negotiation follow the same rules, based on [acceptmarkdown.com](https://acceptmarkdown.com):

| `Accept` | Response |
|----------|----------|
| `text/markdown` | `200` Markdown |
| `text/markdown, text/html` (same quality) | `200` Markdown |
| `text/markdown;q=0.9, */*;q=0.8` | `200` Markdown |
| `text/markdown;q=0.5, text/html;q=0.9` | standard Sylius HTML (higher quality wins) |
| `*/*`, `text/*` or no `Accept` header | standard Sylius HTML |
| `text/markdown;q=0` or `text/markdown;q=0, text/html` | standard Sylius HTML (`q=0` is respected, never a `406`) |
| `application/json` (only unsupported types) | `406 Not Acceptable` |

Rules:

- Markdown is served only when `text/markdown` is listed explicitly; wildcards never select it.
- `Accept` values are parsed and sorted by quality. When Markdown and HTML have the same quality, Markdown wins because the client asked for it explicitly.
- `406` is returned only when the client accepts something, but neither Markdown nor HTML.
- Only `GET` and `HEAD` requests are negotiated.
- Hierarchical taxon slugs (`/{_locale}/taxons/caps/simple`) are supported.

```bash
curl -k -I -H 'Accept: text/markdown' https://127.0.0.1:8000/fr_FR/products/{slug}
curl -k -I -H 'Accept: text/markdown' https://127.0.0.1:8000/fr_FR/taxons/{slug}
```

Expected Markdown response headers:

```http
HTTP/2 200
content-type: text/markdown; charset=utf-8
vary: Accept
```

HTML remains the response when it has the highest accepted quality or when only wildcards are sent:

```bash
curl -k -I -H 'Accept: text/markdown;q=0.5,text/html;q=0.9' https://127.0.0.1:8000/fr_FR/products/{slug}
curl -k -I -H 'Accept: */*' https://127.0.0.1:8000/fr_FR/products/{slug}
```

Unsupported accepted types are rejected:

```bash
curl -k -I -H 'Accept: application/json' https://127.0.0.1:8000/fr_FR/products/{slug}
```

Expected status:

```http
HTTP/2 406
vary: Accept
```

Markdown and `406` responses carry `Vary: Accept`, so shared caches store one representation per `Accept` value.
