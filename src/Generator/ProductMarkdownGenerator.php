<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\ProductMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Exception\ProductNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Provider\ProductMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Inventory\Checker\AvailabilityChecker;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ProductMarkdownGenerator implements ProductMarkdownGeneratorInterface
{
    public function __construct(
        private readonly ProductGeoVisibilityCheckerInterface $visibilityChecker,
        private readonly ProductPricing $pricing,
        private readonly GeoUrlBuilderInterface $urlBuilder,
        private readonly ProductMarkdownOptions $options = new ProductMarkdownOptions(),
        /** @var iterable<ProductMarkdownSectionProviderInterface> */
        private readonly iterable $sectionProviders = [],
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly MarkdownSanitizer $markdownSanitizer = new MarkdownSanitizer(),
        private readonly AvailabilityCheckerInterface $availabilityChecker = new AvailabilityChecker(),
    ) {
    }

    public function generate(ProductInterface $product, ChannelInterface $channel, string $localeCode): string
    {
        if (!$this->visibilityChecker->isVisible($product, $channel)) {
            throw new ProductNotAvailableForGeoException(\sprintf('Product "%s" is not available for GEO export on channel "%s".', (string) $product->getCode(), (string) $channel->getCode()));
        }

        $product->setCurrentLocale($localeCode);
        $product->setFallbackLocale($localeCode);

        $name = trim((string) $product->getName());
        if ('' === $name) {
            $name = (string) $product->getCode();
        }

        $slug = $product->getSlug();
        if (null === $slug || '' === trim($slug)) {
            throw new ProductNotAvailableForGeoException(\sprintf('Product "%s" has no slug for locale "%s".', (string) $product->getCode(), $localeCode));
        }

        $lines = [
            '# ' . $this->markdownSanitizer->text($name),
            '',
        ];

        $event = new ProductMarkdownGenerationEvent($product, $channel, $localeCode);
        $this->eventDispatcher?->dispatch($event, GeoEvents::BEFORE_PRODUCT_MARKDOWN_GENERATION);

        $description = $this->markdownSanitizer->plainText($product->getDescription() ?? $product->getShortDescription());
        if (null !== $description) {
            $lines[] = $description;
            $lines[] = '';
        }

        $brand = $this->options->includeBrand ? $this->resolveBrand($product, $localeCode) : null;
        if (null !== $brand) {
            $lines[] = '## Brand';
            $lines[] = '';
            $lines[] = $this->markdownSanitizer->text($brand);
            $lines[] = '';
        }

        $priceLine = $this->options->exposesPrices() ? $this->pricing->formatProductPrice($product, $channel, $localeCode) : null;
        if (null !== $priceLine) {
            $lines[] = '## Price';
            $lines[] = '';
            $lines[] = $priceLine;
            $lines[] = '';
        }

        $available = $this->options->exposesStock() ? $this->isAvailable($product) : null;
        if (null !== $available) {
            $lines[] = '## Availability';
            $lines[] = '';
            $lines[] = $available ? 'yes' : 'no';
            $lines[] = '';
        }

        if ($this->options->includeAttributes) {
            $this->appendAttributes($lines, $product, $localeCode);
        }
        if ($this->options->includeVariants) {
            $this->appendVariants($lines, $product, $channel, $localeCode);
        }
        if ($this->options->includeImages) {
            $this->appendImages($lines, $product, $channel);
        }
        if ($this->options->includeTaxons) {
            $this->appendTaxons($lines, $product, $channel, $localeCode);
        }
        array_push($lines, ...$this->markdownSanitizer->renderSections($event->getSections()));
        array_push($lines, ...$this->markdownSanitizer->renderProvidedSections(
            $this->sectionProviders,
            static fn (ProductMarkdownSectionProviderInterface $provider): bool => $provider->supports($product, $channel, $localeCode),
            static fn (ProductMarkdownSectionProviderInterface $provider): iterable => $provider->provide($product, $channel, $localeCode),
        ));

        $canonicalUrl = $this->urlBuilder->absolute(
            $channel,
            $this->urlBuilder->productCanonicalPath($localeCode, $slug),
        );

        $lines[] = '## Canonical URL';
        $lines[] = '';
        $lines[] = $canonicalUrl;
        $lines[] = '';

        if ($this->options->includeJsonLd) {
            $this->appendJsonLd($lines, $product, $channel, $localeCode, $name, $description, $brand, $canonicalUrl);
        }

        return implode("\n", $lines);
    }

    /** @param list<string> $lines */
    private function appendAttributes(array &$lines, ProductInterface $product, string $localeCode): void
    {
        $attributes = [];
        foreach ($product->getAttributesByLocale($localeCode, $localeCode) as $attribute) {
            if ($this->isBrandAttribute($attribute)) {
                continue;
            }

            $name = trim((string) ($attribute->getName() ?? $attribute->getCode()));
            $value = $this->formatAttributeValue($attribute->getValue());
            if ('' === $name || null === $value) {
                continue;
            }

            $attributes[] = \sprintf('- %s: %s', $this->markdownSanitizer->text($name), $this->markdownSanitizer->text($value));
        }

        if ([] === $attributes) {
            return;
        }

        $lines[] = '## Attributes';
        $lines[] = '';
        array_push($lines, ...$attributes);
        $lines[] = '';
    }

    /** @param list<string> $lines */
    private function appendVariants(array &$lines, ProductInterface $product, ChannelInterface $channel, string $localeCode): void
    {
        $variants = [];
        foreach ($product->getEnabledVariants() as $variant) {
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            $parts = [];
            $name = trim((string) ($variant->getName() ?? $variant->getCode()));
            if ('' !== $name) {
                $parts[] = $name;
            }

            $options = $this->formatOptionValues($variant, $localeCode);
            if ([] !== $options) {
                $parts[] = implode(', ', $options);
            }

            $variantPrice = $this->options->exposesPrices() ? $this->pricing->formatVariantPrice($variant, $channel, $localeCode) : null;
            if (null !== $variantPrice) {
                $parts[] = $variantPrice;
            }

            if (($this->options->exposesStock() && $this->options->exposesDetailedStock())) {
                $parts[] = 'available: ' . ($this->availabilityChecker->isStockAvailable($variant) ? 'yes' : 'no');
            }
            $variants[] = '- ' . $this->markdownSanitizer->text(implode(' | ', $parts));
        }

        if ([] === $variants) {
            return;
        }

        $lines[] = '## Variants';
        $lines[] = '';
        array_push($lines, ...$variants);
        $lines[] = '';
    }

    /** @param list<string> $lines */
    private function appendImages(array &$lines, ProductInterface $product, ChannelInterface $channel): void
    {
        $images = [];
        foreach ($product->getImages() as $image) {
            if (null === $image->getPath()) {
                continue;
            }

            $images[] = '- ' . $this->absoluteImageUrl($channel, $image->getPath());
        }

        if ([] === $images) {
            return;
        }

        $lines[] = '## Images';
        $lines[] = '';
        array_push($lines, ...\array_slice($images, 0, 5));
        $lines[] = '';
    }

    /** @param list<string> $lines */
    private function appendTaxons(array &$lines, ProductInterface $product, ChannelInterface $channel, string $localeCode): void
    {
        $taxons = [];
        foreach ($product->getProductTaxons() as $productTaxon) {
            $taxon = $productTaxon->getTaxon();
            if (!$taxon instanceof TaxonInterface) {
                continue;
            }

            $taxon->setCurrentLocale($localeCode);
            $taxon->setFallbackLocale($localeCode);

            $name = trim((string) ($taxon->getName() ?? $taxon->getCode()));
            $slug = $taxon->getSlug();
            if ('' === $name || null === $slug || '' === trim($slug)) {
                continue;
            }

            $taxons[] = \sprintf(
                '- [%s](%s)',
                $this->markdownSanitizer->linkText($name),
                $this->urlBuilder->absolute($channel, $this->urlBuilder->taxonCanonicalPath($localeCode, $slug)),
            );
        }

        if ([] === $taxons) {
            return;
        }

        $lines[] = '## Taxons';
        $lines[] = '';
        array_push($lines, ...$taxons);
        $lines[] = '';
    }

    /** @param list<string> $lines */
    private function appendJsonLd(
        array &$lines,
        ProductInterface $product,
        ChannelInterface $channel,
        string $localeCode,
        string $name,
        ?string $description,
        ?string $brand,
        string $canonicalUrl,
    ): void {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'sku' => (string) $product->getCode(),
            'url' => $canonicalUrl,
        ];

        if (null !== $description) {
            $data['description'] = $description;
        }

        if ($this->options->includeBrand && null !== $brand) {
            $data['brand'] = [
                '@type' => 'Brand',
                'name' => $brand,
            ];
        }

        $images = $this->jsonLdImages($product, $channel);
        if ($this->options->includeImages && [] !== $images) {
            $data['image'] = $images;
        }

        $offer = $this->jsonLdOffer($product, $channel, $localeCode, $canonicalUrl);
        if (null !== $offer) {
            $data['offers'] = $offer;
        }

        $encoded = json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
        if (!\is_string($encoded)) {
            return;
        }

        $lines[] = '## Structured Data';
        $lines[] = '';
        $lines[] = '```json';
        array_push($lines, ...explode("\n", $encoded));
        $lines[] = '```';
        $lines[] = '';
    }

    /** @return list<string> */
    private function jsonLdImages(ProductInterface $product, ChannelInterface $channel): array
    {
        $images = [];
        foreach ($product->getImages() as $image) {
            if (null === $image->getPath()) {
                continue;
            }

            $images[] = $this->absoluteImageUrl($channel, $image->getPath());
        }

        return \array_slice(array_values(array_unique($images)), 0, 5);
    }

    /** @return array<string, mixed>|null */
    private function jsonLdOffer(ProductInterface $product, ChannelInterface $channel, string $localeCode, string $canonicalUrl): ?array
    {
        if (!$this->options->exposesPrices() && !$this->options->exposesStock()) {
            return null;
        }

        $variant = $this->pricing->referenceVariant($product, $channel);
        $currency = $channel->getBaseCurrency();
        if (!$variant instanceof ProductVariantInterface || !$currency instanceof CurrencyInterface || null === $currency->getCode()) {
            return null;
        }

        $offer = [
            '@type' => 'Offer',
            'url' => $canonicalUrl,
        ];

        if ($this->options->exposesPrices()) {
            $offer += $this->pricing->jsonLdPriceProperties($variant, $channel) ?? [];
        }

        if ($this->options->exposesStock()) {
            $offer['availability'] = true === $this->isAvailable($product) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
        }

        return \count($offer) > 2 ? $offer : null;
    }

    private function isAvailable(ProductInterface $product): ?bool
    {
        $hasStockInformation = false;
        foreach ($product->getEnabledVariants() as $variant) {
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            $hasStockInformation = true;
            if ($this->availabilityChecker->isStockAvailable($variant)) {
                return true;
            }
        }

        return $hasStockInformation ? false : null;
    }

    /** @return list<string> */
    private function formatOptionValues(ProductVariantInterface $variant, string $localeCode): array
    {
        $options = [];
        foreach ($variant->getOptionValues() as $optionValue) {
            $optionValue->setCurrentLocale($localeCode);
            $optionValue->setFallbackLocale($localeCode);

            $name = trim((string) ($optionValue->getName() ?? $optionValue->getValue() ?? $optionValue->getCode()));
            if ('' !== $name) {
                $options[] = $this->markdownSanitizer->text($name);
            }
        }

        return $options;
    }

    private function resolveBrand(ProductInterface $product, string $localeCode): ?string
    {
        foreach ($product->getAttributesByLocale($localeCode, $localeCode) as $attribute) {
            if (!$this->isBrandAttribute($attribute)) {
                continue;
            }

            return $this->formatAttributeValue($attribute->getValue());
        }

        return null;
    }

    private function isBrandAttribute(AttributeValueInterface $attribute): bool
    {
        $code = mb_strtolower((string) $attribute->getCode());
        $name = mb_strtolower((string) $attribute->getName());

        return \in_array($code, ['brand', 'manufacturer', 'marque'], true) ||
            \in_array($name, ['brand', 'manufacturer', 'marque'], true);
    }

    private function formatAttributeValue(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (\is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (\is_array($value)) {
            $parts = array_filter(array_map(
                fn (mixed $item): ?string => $this->formatAttributeValue($item),
                $value,
            ), static fn (?string $part): bool => null !== $part);

            return [] === $parts ? null : implode(', ', $parts);
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            return null;
        }

        $formatted = trim((string) $value);

        return '' === $formatted ? null : $formatted;
    }

    private function absoluteImageUrl(ChannelInterface $channel, string $path): string
    {
        $path = trim($path);
        if (false !== filter_var($path, \FILTER_VALIDATE_URL) && \in_array(parse_url($path, \PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $path;
        }

        return $this->urlBuilder->absolute($channel, '/media/image/' . ltrim($path, '/'));
    }
}
