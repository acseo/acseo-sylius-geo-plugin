<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Support;

use ACSEO\SyliusGeoPlugin\Attribute\SelectAttributeLabelResolver;
use ACSEO\SyliusGeoPlugin\Generator\Product\AttributeValueFormatter;
use ACSEO\SyliusGeoPlugin\Generator\Product\JsonLdOfferBuilder;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductAvailability;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductBrandResolver;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductImageUrls;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductJsonLdBuilder;
use ACSEO\SyliusGeoPlugin\Generator\Product\VariantPriceResolver;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityChecker;

final class ProductMarkdownFixtures
{
    public static function variant(string $code, ChannelInterface $channel, int $price, ?int $originalPrice = null, bool $inStock = true): ProductVariantInterface
    {
        $pricing = new ChannelPricing();
        $pricing->setChannelCode((string) $channel->getCode());
        $pricing->setPrice($price);
        $pricing->setOriginalPrice($originalPrice);

        $variant = new ProductVariant();
        $variant->setCode($code);
        $variant->addChannelPricing($pricing);
        $variant->setTracked(!$inStock);
        $variant->setOnHand(0);

        return $variant;
    }

    public static function offerBuilder(ProductMarkdownOptions $options, ProductVariantPricesCalculatorInterface $calculator): JsonLdOfferBuilder
    {
        return new JsonLdOfferBuilder(
            $options,
            new VariantPriceResolver($options, $calculator),
            new ProductAvailability(new AvailabilityChecker()),
            TranslatorFactory::crawlerTexts(),
        );
    }

    public static function jsonLdBuilder(ProductMarkdownOptions $options, ProductVariantPricesCalculatorInterface $calculator): ProductJsonLdBuilder
    {
        $texts = TranslatorFactory::crawlerTexts();

        return new ProductJsonLdBuilder(
            $options,
            new ProductBrandResolver(new AttributeValueFormatter(new SelectAttributeLabelResolver(), $texts)),
            new ProductImageUrls(new GeoUrlBuilder()),
            self::offerBuilder($options, $calculator),
            $texts,
        );
    }
}
