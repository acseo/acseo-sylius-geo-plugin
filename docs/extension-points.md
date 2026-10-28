# Extension Points

Projects can add Markdown sections without replacing the built-in generators.

## Markdown Section Providers

Implement one of these interfaces:

- `ACSEO\SyliusGeoPlugin\Provider\ProductMarkdownSectionProviderInterface`
- `ACSEO\SyliusGeoPlugin\Provider\TaxonMarkdownSectionProviderInterface`
- `ACSEO\SyliusGeoPlugin\Provider\LlmTxtSectionProviderInterface`

With autoconfiguration enabled, providers are tagged automatically. Without autoconfiguration, tag services manually:

```yaml
services:
    App\Geo\ProductEcoScoreSectionProvider:
        tags:
            - { name: 'acseo_sylius_geo_plugin.product_markdown_section_provider', priority: 100 }

    App\Geo\TaxonBuyingGuideSectionProvider:
        tags:
            - { name: 'acseo_sylius_geo_plugin.taxon_markdown_section_provider', priority: 100 }

    App\Geo\StoreServicesLlmTxtSectionProvider:
        tags:
            - { name: 'acseo_sylius_geo_plugin.llm_txt_section_provider', priority: 100 }
```

## CMS Pages

Sylius core does not ship CMS pages by default. Projects can still expose editorial pages in `llms.txt` by implementing:

```php
ACSEO\SyliusGeoPlugin\Provider\CmsPageProviderInterface
```

Tag custom providers with:

```yaml
services:
    App\Geo\CmsPageProvider:
        tags:
            - { name: 'acseo_sylius_geo_plugin.cms_page_provider', priority: 100 }
```

The plugin aggregates provided pages into a `CMS pages` section in `llms.txt`. This keeps integrations with `sylius/cms-plugin`, BitBag CMS, custom Symfony routes, or static pages optional.

## Events

Projects can listen to Symfony events to add generated content or adjust response metadata:

| Event constant | Event name | Event class | Purpose |
|----------------|------------|-------------|---------|
| `GeoEvents::BEFORE_LLM_TXT_GENERATION` | `acseo_sylius_geo_plugin.before_llm_txt_generation` | `LlmTxtGenerationEvent` | Add sections to `llms.txt` with `addSection()` |
| `GeoEvents::BEFORE_PRODUCT_MARKDOWN_GENERATION` | `acseo_sylius_geo_plugin.before_product_markdown_generation` | `ProductMarkdownGenerationEvent` | Add sections to product Markdown with `addSection()` |
| `GeoEvents::BEFORE_TAXON_MARKDOWN_GENERATION` | `acseo_sylius_geo_plugin.before_taxon_markdown_generation` | `TaxonMarkdownGenerationEvent` | Add sections to taxon Markdown with `addSection()` |
| `GeoEvents::BEFORE_RESPONSE_CREATION` | `acseo_sylius_geo_plugin.before_response_creation` | `GeoResponseCreationEvent` | Change the body (`setBody()`), the content type (`setContentType()`), or add headers (`setHeader()`) |

Product and taxon generation events are dispatched once the item has passed the GEO visibility check. The sections that generation events collect are rendered through the same sanitizer as section providers. They do not expose the generated Markdown itself.

`GeoResponseCreationEvent` cannot change the status code. The plugin sets `Content-Type` and `ETag` after the listeners run, and always sets `Cache-Control` with the configured `max-age`, public for anonymous requests and private for authenticated ones, so a `Cache-Control` header set by a listener is overridden.

Example subscriber:

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\ProductMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class GeoMarkdownSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            GeoEvents::BEFORE_PRODUCT_MARKDOWN_GENERATION => 'onProductMarkdown',
        ];
    }

    public function onProductMarkdown(ProductMarkdownGenerationEvent $event): void
    {
        $event->addSection(new MarkdownSection('Extra information', ['Custom project content.']));
    }
}
```
