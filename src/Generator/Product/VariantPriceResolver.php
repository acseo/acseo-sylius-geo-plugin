<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product;

use ACSEO\SyliusGeoPlugin\Model\PriceStrategy;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Exception\MissingChannelConfigurationException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final readonly class VariantPriceResolver
{
    public function __construct(
        private ProductMarkdownOptions $options,
        private ProductVariantPricesCalculatorInterface $priceCalculator,
    ) {
    }

    public function referenceVariant(ProductInterface $product, ChannelInterface $channel): ?ProductVariantInterface
    {
        $first = null;
        $cheapest = null;
        $cheapestAmount = null;
        foreach ($product->getEnabledVariants() as $variant) {
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            $first ??= $variant;
            $amount = $this->amount($variant, $channel);
            if (null !== $amount && (null === $cheapestAmount || $amount < $cheapestAmount)) {
                $cheapest = $variant;
                $cheapestAmount = $amount;
            }
        }

        return $cheapest ?? $first;
    }

    public function amount(ProductVariantInterface $variant, ChannelInterface $channel): ?int
    {
        if (!$this->options->exposesPrices()) {
            return null;
        }

        if (PriceStrategy::Base === $this->options->priceStrategy) {
            return $variant->getChannelPricingForChannel($channel)?->getPrice();
        }

        try {
            return $this->priceCalculator->calculate($variant, ['channel' => $channel]);
        } catch (MissingChannelConfigurationException) {
            return null;
        }
    }

    public function originalAmount(ProductVariantInterface $variant, ChannelInterface $channel): ?int
    {
        return $variant->getChannelPricingForChannel($channel)?->getOriginalPrice();
    }
}
