<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product;

use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;

final readonly class ProductJsonLdBuilder
{
    public function __construct(
        private ProductMarkdownOptions $options,
        private ProductBrandResolver $brandResolver,
        private ProductImageUrls $imageUrls,
        private JsonLdOfferBuilder $offerBuilder,
        private CrawlerTextTranslator $texts,
    ) {
    }

    /** @return list<string> */
    public function build(ProductMarkdownContext $context, string $name, ?string $description, string $canonicalUrl): array
    {
        if (!$this->options->includeJsonLd) {
            return [];
        }

        $encoded = json_encode($this->data($context, $name, $description, $canonicalUrl), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
        if (!\is_string($encoded)) {
            return [];
        }

        return [
            '## ' . $this->texts->translate('acseo_sylius_geo_plugin.output.product.structured_data'),
            '',
            '```json',
            ...explode("\n", $encoded),
            '```',
            '',
        ];
    }

    /** @return array<string, mixed> */
    private function data(ProductMarkdownContext $context, string $name, ?string $description, string $canonicalUrl): array
    {
        $brand = $this->options->includeBrand ? $this->brandResolver->resolve($context->product, $context->localeCode) : null;
        $images = $this->options->includeImages ? $this->imageUrls->forProduct($context->product, $context->channel) : [];

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'sku' => (string) $context->product->getCode(),
            'url' => $canonicalUrl,
            'description' => $description,
            'brand' => null === $brand ? null : ['@type' => 'Brand', 'name' => $brand],
            'image' => [] === $images ? null : $images,
            'offers' => $this->offerBuilder->build($context, $canonicalUrl),
        ], static fn (mixed $value): bool => null !== $value);
    }
}
