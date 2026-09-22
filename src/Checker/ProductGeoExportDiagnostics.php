<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class ProductGeoExportDiagnostics implements ProductGeoExportDiagnosticsInterface
{
    public function __construct(
        private readonly ProductGeoVisibilityCheckerInterface $visibilityChecker,
    ) {
    }

    public function exclusionReasons(ProductInterface $product, ChannelInterface $channel, string $localeCode): array
    {
        $reasons = [];

        if (!$product->isEnabled()) {
            $reasons[] = 'Product is disabled.';
        }

        if (!$product->hasChannel($channel)) {
            $reasons[] = \sprintf('Product is not assigned to channel "%s".', (string) $channel->getCode());
        }

        if (!$this->visibilityChecker->isVisible($product, $channel)) {
            $reasons[] = 'Product visibility checker rejected this product.';
        }

        $slug = $product->getSlug();
        if (null === $slug || '' === trim($slug)) {
            $reasons[] = \sprintf('Product has no slug for locale "%s".', $localeCode);
        }

        $enabledVariants = $product->getEnabledVariants();
        if ($enabledVariants->isEmpty()) {
            $reasons[] = 'Product has no enabled variants.';
        } elseif (!$this->hasCoreVariant($enabledVariants->toArray())) {
            $reasons[] = 'Product enabled variants are not Sylius core product variants.';
        }

        return array_values(array_unique($reasons));
    }

    public function warnings(ProductInterface $product, ChannelInterface $channel): array
    {
        $warnings = [];

        foreach ($product->getEnabledVariants() as $variant) {
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            $channelPricing = $variant->getChannelPricingForChannel($channel);
            if (!$channelPricing instanceof ChannelPricingInterface || null === $channelPricing->getPrice()) {
                $warnings[] = \sprintf(
                    'Variant "%s" has no channel pricing for channel "%s"; price will be omitted from GEO output.',
                    (string) $variant->getCode(),
                    (string) $channel->getCode(),
                );
            }
        }

        return array_values(array_unique($warnings));
    }

    /** @param array<mixed> $variants */
    private function hasCoreVariant(array $variants): bool
    {
        foreach ($variants as $variant) {
            if ($variant instanceof ProductVariantInterface) {
                return true;
            }
        }

        return false;
    }
}
