<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product;

use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final readonly class ProductPriceFormatter
{
    public function __construct(
        private VariantPriceResolver $prices,
        private MoneyFormatterInterface $moneyFormatter,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    public function forProduct(ProductInterface $product, ChannelInterface $channel, string $localeCode): ?string
    {
        $variant = $this->prices->referenceVariant($product, $channel);

        return $variant instanceof ProductVariantInterface ? $this->forVariant($variant, $channel, $localeCode) : null;
    }

    public function forVariant(ProductVariantInterface $variant, ChannelInterface $channel, string $localeCode): ?string
    {
        $currency = $channel->getBaseCurrency()?->getCode();
        $amount = $this->prices->amount($variant, $channel);
        if (null === $currency || null === $amount) {
            return null;
        }

        $price = $this->sanitizer->text($this->moneyFormatter->format($amount, $currency, $localeCode));
        $originalAmount = $this->prices->originalAmount($variant, $channel);
        if (null === $originalAmount || $originalAmount <= $amount) {
            return $price;
        }

        return $this->texts->translate('acseo_sylius_geo_plugin.output.product.price_with_original', [
            '%price%' => $price,
            '%original%' => $this->sanitizer->text($this->moneyFormatter->format($originalAmount, $currency, $localeCode)),
        ]);
    }
}
