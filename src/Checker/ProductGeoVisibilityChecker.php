<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

final readonly class ProductGeoVisibilityChecker implements ProductGeoVisibilityCheckerInterface
{
    public function __construct(
        private ExcludedTaxonMatcher $excludedTaxons,
        private ExcludedAttributeMatcher $excludedAttributes,
    ) {
    }

    #[\Override]
    public function isVisible(ProductInterface $product, ChannelInterface $channel): bool
    {
        return $product->isEnabled() &&
            $product->hasChannel($channel) &&
            !$this->excludedTaxons->matches($product) &&
            !$this->excludedAttributes->matches($product);
    }
}
