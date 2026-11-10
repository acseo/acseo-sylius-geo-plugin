<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product\Section;

use ACSEO\SyliusGeoPlugin\Generator\Product\ProductBrandResolver;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownContext;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownSectionBuilderInterface;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 100)]
final readonly class BrandSection implements ProductMarkdownSectionBuilderInterface
{
    public function __construct(
        private ProductMarkdownOptions $options,
        private ProductBrandResolver $brandResolver,
        private CrawlerTextTranslator $texts,
    ) {
    }

    #[\Override]
    public function build(ProductMarkdownContext $context): ?MarkdownSection
    {
        $brand = $this->options->includeBrand ? $this->brandResolver->resolve($context->product, $context->localeCode) : null;

        return null === $brand ? null : new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.product.brand'), [$brand]);
    }
}
