<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product\Section;

use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownContext;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownSectionBuilderInterface;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductPriceFormatter;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 90)]
final readonly class PriceSection implements ProductMarkdownSectionBuilderInterface
{
    public function __construct(
        private ProductMarkdownOptions $options,
        private ProductPriceFormatter $priceFormatter,
        private CrawlerTextTranslator $texts,
    ) {
    }

    #[\Override]
    public function build(ProductMarkdownContext $context): ?MarkdownSection
    {
        $price = $this->options->exposesPrices()
            ? $this->priceFormatter->forProduct($context->product, $context->channel, $context->localeCode)
            : null;

        return null === $price ? null : new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.product.price'), [$price]);
    }
}
