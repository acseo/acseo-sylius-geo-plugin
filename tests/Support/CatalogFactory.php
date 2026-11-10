<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Support;

use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxon;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Model\ProductAttributeValue;

final class CatalogFactory
{
    public const string LOCALE = 'en_US';

    public static function channel(string $code = 'WEB', ?string $hostname = 'shop.example.com'): ChannelInterface
    {
        $locale = new Locale();
        $locale->setCode(self::LOCALE);
        $currency = new Currency();
        $currency->setCode('USD');

        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName('Web store');
        $channel->setHostname($hostname);
        $channel->addLocale($locale);
        $channel->setDefaultLocale($locale);
        $channel->setBaseCurrency($currency);
        $channel->addCurrency($currency);

        return $channel;
    }

    public static function taxon(string $code, ?TaxonInterface $parent = null, bool $enabled = true, ?string $slug = null): TaxonInterface
    {
        $taxon = new Taxon();
        $taxon->setCurrentLocale(self::LOCALE);
        $taxon->setFallbackLocale(self::LOCALE);
        $taxon->setCode($code);
        $taxon->setName(ucfirst($code));
        $taxon->setSlug($slug ?? $code);
        $taxon->setEnabled($enabled);
        $taxon->setParent($parent);

        return $taxon;
    }

    public static function product(string $code, ?ChannelInterface $channel = null, bool $enabled = true): ProductInterface
    {
        $product = new Product();
        $product->setCurrentLocale(self::LOCALE);
        $product->setFallbackLocale(self::LOCALE);
        $product->setCode($code);
        $product->setName(ucfirst($code));
        $product->setSlug($code);
        $product->setEnabled($enabled);

        if ($channel instanceof ChannelInterface) {
            $product->addChannel($channel);
        }

        return $product;
    }

    public static function classify(ProductInterface $product, TaxonInterface $taxon): void
    {
        $productTaxon = new ProductTaxon();
        $productTaxon->setTaxon($taxon);
        $product->addProductTaxon($productTaxon);
    }

    /** @param array<string, mixed> $configuration */
    public static function attribute(ProductInterface $product, string $code, mixed $value, string $type = 'text', array $configuration = []): void
    {
        $attribute = new ProductAttribute();
        $attribute->setCurrentLocale(self::LOCALE);
        $attribute->setFallbackLocale(self::LOCALE);
        $attribute->setCode($code);
        $attribute->setName($code);
        $attribute->setType($type);
        $attribute->setStorageType('select' === $type ? 'json' : 'text');
        $attribute->setConfiguration($configuration);

        $attributeValue = new ProductAttributeValue();
        $attributeValue->setAttribute($attribute);
        $attributeValue->setLocaleCode(self::LOCALE);
        $attributeValue->setValue($value);
        $product->addAttribute($attributeValue);
    }
}
