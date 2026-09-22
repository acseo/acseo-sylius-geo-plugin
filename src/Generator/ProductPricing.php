<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Exception\MissingChannelConfigurationException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;

final class ProductPricing
{
    public function __construct(
        private readonly ProductVariantPricesCalculatorInterface $priceCalculator,
        private readonly MoneyFormatterInterface $moneyFormatter,
        private readonly ProductMarkdownOptions $options = new ProductMarkdownOptions(),
        private readonly MarkdownSanitizer $markdownSanitizer = new MarkdownSanitizer(),
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

    public function formatProductPrice(ProductInterface $product, ChannelInterface $channel, string $localeCode): ?string
    {
        $variant = $this->referenceVariant($product, $channel);
        if (!$variant instanceof ProductVariantInterface) {
            return null;
        }

        return $this->formatVariantPrice($variant, $channel, $localeCode);
    }

    public function formatVariantPrice(ProductVariantInterface $variant, ChannelInterface $channel, string $localeCode): ?string
    {
        $currency = $channel->getBaseCurrency();
        if (!$currency instanceof CurrencyInterface || null === $currency->getCode()) {
            return null;
        }

        $amount = $this->amount($variant, $channel);
        if (null === $amount) {
            return null;
        }

        $formattedPrice = $this->moneyFormatter->format($amount, $currency->getCode(), $localeCode);
        $originalPrice = $this->originalAmount($variant, $channel);
        if (null === $originalPrice || $originalPrice <= $amount) {
            return $this->markdownSanitizer->text($formattedPrice);
        }

        return \sprintf(
            '%s (original price: %s)',
            $formattedPrice,
            $this->markdownSanitizer->text($this->moneyFormatter->format($originalPrice, $currency->getCode(), $localeCode)),
        );
    }

    /** @return array<string, mixed>|null */
    public function jsonLdPriceProperties(ProductVariantInterface $variant, ChannelInterface $channel): ?array
    {
        $currency = $channel->getBaseCurrency();
        $amount = $this->amount($variant, $channel);
        if (!$currency instanceof CurrencyInterface || null === $currency->getCode() || null === $amount) {
            return null;
        }

        $properties = [
            'price' => $this->decimal($amount),
            'priceCurrency' => $currency->getCode(),
        ];

        $originalPrice = $this->originalAmount($variant, $channel);
        if (null !== $originalPrice && $originalPrice > $amount) {
            $properties['priceSpecification'] = [
                '@type' => 'UnitPriceSpecification',
                'price' => $this->decimal($originalPrice),
                'priceCurrency' => $currency->getCode(),
                'name' => 'Original price',
            ];
        }

        return $properties;
    }

    private function amount(ProductVariantInterface $variant, ChannelInterface $channel): ?int
    {
        if (!$this->options->exposesPrices()) {
            return null;
        }

        if (ProductMarkdownOptions::PRICE_STRATEGY_BASE === $this->options->priceStrategy) {
            $channelPricing = $variant->getChannelPricingForChannel($channel);

            return $channelPricing instanceof ChannelPricingInterface ? $channelPricing->getPrice() : null;
        }

        try {
            return $this->priceCalculator->calculate($variant, ['channel' => $channel]);
        } catch (MissingChannelConfigurationException) {
            return null;
        }
    }

    private function originalAmount(ProductVariantInterface $variant, ChannelInterface $channel): ?int
    {
        $channelPricing = $variant->getChannelPricingForChannel($channel);

        return $channelPricing instanceof ChannelPricingInterface ? $channelPricing->getOriginalPrice() : null;
    }

    private function decimal(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
