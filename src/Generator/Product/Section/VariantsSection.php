<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product\Section;

use ACSEO\SyliusGeoPlugin\Generator\Product\ProductAvailability;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownContext;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownSectionBuilderInterface;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductPriceFormatter;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 60)]
final readonly class VariantsSection implements ProductMarkdownSectionBuilderInterface
{
    public function __construct(
        private ProductMarkdownOptions $options,
        private ProductPriceFormatter $priceFormatter,
        private ProductAvailability $availability,
        private ResourceLocalizer $localizer,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    #[\Override]
    public function build(ProductMarkdownContext $context): ?MarkdownSection
    {
        if (!$this->options->includeVariants) {
            return null;
        }

        $lines = [];
        foreach ($context->product->getEnabledVariants() as $variant) {
            if ($variant instanceof ProductVariantInterface) {
                $lines[] = '- ' . $this->sanitizer->text(implode(' | ', $this->parts($variant, $context)));
            }
        }

        return new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.product.variants'), $lines);
    }

    /** @return list<string> */
    private function parts(ProductVariantInterface $variant, ProductMarkdownContext $context): array
    {
        $options = [];
        foreach ($variant->getOptionValues() as $optionValue) {
            $this->localizer->localize($optionValue, $context->localeCode);
            $options[] = trim((string) ($optionValue->getName() ?? $optionValue->getValue() ?? $optionValue->getCode()));
        }

        $price = $this->options->exposesPrices() ? $this->priceFormatter->forVariant($variant, $context->channel, $context->localeCode) : null;
        $stock = $this->options->exposesDetailedStock()
            ? $this->texts->translate('acseo_sylius_geo_plugin.output.product.variant_available', [
                '%value%' => $this->texts->translate($this->availability->ofVariant($variant) ? 'acseo_sylius_geo_plugin.output.product.yes' : 'acseo_sylius_geo_plugin.output.product.no'),
            ])
            : null;

        return array_values(array_filter([
            trim((string) ($variant->getName() ?? $variant->getCode())),
            implode(', ', array_filter($options, static fn (string $option): bool => '' !== $option)),
            $price,
            $stock,
        ], static fn (?string $part): bool => null !== $part && '' !== $part));
    }
}
