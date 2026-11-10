<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product;

use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Sylius\Component\Core\Model\ProductVariantInterface;

final readonly class JsonLdOfferBuilder
{
    private const string IN_STOCK = 'https://schema.org/InStock';

    private const string OUT_OF_STOCK = 'https://schema.org/OutOfStock';

    public function __construct(
        private ProductMarkdownOptions $options,
        private VariantPriceResolver $prices,
        private ProductAvailability $availability,
        private CrawlerTextTranslator $texts,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function build(ProductMarkdownContext $context, string $canonicalUrl): ?array
    {
        $variant = $this->prices->referenceVariant($context->product, $context->channel);
        $currency = $context->channel->getBaseCurrency()?->getCode();
        if (!$variant instanceof ProductVariantInterface || null === $currency) {
            return null;
        }

        $offer = ['@type' => 'Offer', 'url' => $canonicalUrl];
        if ($this->options->exposesPrices()) {
            $offer += $this->priceProperties($variant, $context, $currency);
        }

        if ($this->options->exposesStock()) {
            $offer['availability'] = true === $this->availability->ofProduct($context->product) ? self::IN_STOCK : self::OUT_OF_STOCK;
        }

        return \count($offer) > 2 ? $offer : null;
    }

    /** @return array<string, mixed> */
    private function priceProperties(ProductVariantInterface $variant, ProductMarkdownContext $context, string $currency): array
    {
        $amount = $this->prices->amount($variant, $context->channel);
        if (null === $amount) {
            return [];
        }

        $properties = ['price' => $this->decimal($amount), 'priceCurrency' => $currency];
        $originalAmount = $this->prices->originalAmount($variant, $context->channel);
        if (null !== $originalAmount && $originalAmount > $amount) {
            $properties['priceSpecification'] = [
                '@type' => 'UnitPriceSpecification',
                'price' => $this->decimal($originalAmount),
                'priceCurrency' => $currency,
                'name' => $this->texts->translate('acseo_sylius_geo_plugin.output.product.original_price_name'),
            ];
        }

        return $properties;
    }

    private function decimal(int $amount): string
    {
        return \sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
    }
}
