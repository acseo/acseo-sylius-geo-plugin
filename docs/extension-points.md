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

Sylius core does not ship CMS pages by default. Projects can still expose editorial pages in `llm.txt` by implementing:

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

The plugin aggregates provided pages into a `CMS pages` section in `llm.txt`. This keeps integrations with `sylius/cms-plugin`, BitBag CMS, custom Symfony routes, or static pages optional.

## Events

Projects can listen to Symfony events to adjust generated content or response metadata:

| Event constant | Event name | Purpose |
|----------------|------------|---------|
| `GeoEvents::BEFORE_LLM_TXT_GENERATION` | `acseo_sylius_geo_plugin.before_llm_txt_generation` | Adjust the generated `llm.txt` content before it is returned |
| `GeoEvents::BEFORE_PRODUCT_MARKDOWN_GENERATION` | `acseo_sylius_geo_plugin.before_product_markdown_generation` | Adjust product Markdown before it is returned |
| `GeoEvents::BEFORE_TAXON_MARKDOWN_GENERATION` | `acseo_sylius_geo_plugin.before_taxon_markdown_generation` | Adjust taxon Markdown before it is returned |
| `GeoEvents::BEFORE_RESPONSE_CREATION` | `acseo_sylius_geo_plugin.before_response_creation` | Adjust GEO response headers, status, or body before response creation finishes |

Example subscriber:

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\ProductMarkdownGenerationEvent;
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
        $event->setMarkdown($event->getMarkdown() . "\n\n## Extra information\n\nCustom project content.\n");
    }
}
```
