<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product\Section;

use ACSEO\SyliusGeoPlugin\Generator\Product\ProductAvailability;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownContext;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownSectionBuilderInterface;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 80)]
final readonly class AvailabilitySection implements ProductMarkdownSectionBuilderInterface
{
    public function __construct(
        private ProductMarkdownOptions $options,
        private ProductAvailability $availability,
        private CrawlerTextTranslator $texts,
    ) {
    }

    #[\Override]
    public function build(ProductMarkdownContext $context): ?MarkdownSection
    {
        $available = $this->options->exposesStock() ? $this->availability->ofProduct($context->product) : null;
        if (null === $available) {
            return null;
        }

        return new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.product.availability'), [
            $this->texts->translate($available ? 'acseo_sylius_geo_plugin.output.product.yes' : 'acseo_sylius_geo_plugin.output.product.no'),
        ]);
    }
}
