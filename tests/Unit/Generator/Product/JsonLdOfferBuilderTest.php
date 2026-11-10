<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator\Product;

use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownContext;
use ACSEO\SyliusGeoPlugin\Model\PriceStrategy;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Model\StockStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;
use Tests\ACSEO\SyliusGeoPlugin\Support\ProductMarkdownFixtures;

final class JsonLdOfferBuilderTest extends TestCase
{
    /** @return iterable<string, array{int, string}> */
    public static function amounts(): iterable
    {
        yield 'cents' => [5, '0.05'];
        yield 'regular amount' => [1234, '12.34'];
        yield 'round amount' => [10000, '100.00'];
    }

    #[DataProvider('amounts')]
    #[TestDox('The amount $amount is exported as the decimal $expected')]
    public function test_price_is_exported_without_floats(int $amount, string $expected): void
    {
        $context = $this->context(ProductMarkdownFixtures::variant('mug-s', CatalogFactory::channel(), $amount));

        $offer = ProductMarkdownFixtures::offerBuilder(new ProductMarkdownOptions(), $this->calculator())->build($context, 'https://shop.example.com/en_US/products/mug');

        self::assertSame($expected, $offer['price'] ?? null);
        self::assertSame('USD', $offer['priceCurrency'] ?? null);
        self::assertSame('https://schema.org/InStock', $offer['availability'] ?? null);
    }

    #[TestDox('A reduced price exposes the original price specification')]
    public function test_reduced_price_exposes_the_original_price(): void
    {
        $context = $this->context(ProductMarkdownFixtures::variant('mug-s', CatalogFactory::channel(), 1000, 1500));

        $offer = ProductMarkdownFixtures::offerBuilder(new ProductMarkdownOptions(), $this->calculator())->build($context, 'https://shop.example.com/p');

        self::assertSame([
            '@type' => 'UnitPriceSpecification',
            'price' => '15.00',
            'priceCurrency' => 'USD',
            'name' => 'Original price',
        ], $offer['priceSpecification'] ?? null);
    }

    #[TestDox('The cheapest priced variant is the reference variant')]
    public function test_cheapest_variant_is_the_reference(): void
    {
        $channel = CatalogFactory::channel();
        $product = CatalogFactory::product('mug', $channel);
        $product->addVariant(ProductMarkdownFixtures::variant('expensive', $channel, 3000));
        $product->addVariant(ProductMarkdownFixtures::variant('cheap', $channel, 1000));

        $offer = ProductMarkdownFixtures::offerBuilder(new ProductMarkdownOptions(), $this->calculator())->build(new ProductMarkdownContext($product, $channel, 'en_US'), 'https://shop.example.com/p');

        self::assertSame('10.00', $offer['price'] ?? null);
    }

    #[TestDox('An out of stock product is exported as OutOfStock')]
    public function test_out_of_stock_product(): void
    {
        $context = $this->context(ProductMarkdownFixtures::variant('mug-s', CatalogFactory::channel(), 1000, null, false));

        $offer = ProductMarkdownFixtures::offerBuilder(new ProductMarkdownOptions(), $this->calculator())->build($context, 'https://shop.example.com/p');

        self::assertSame('https://schema.org/OutOfStock', $offer['availability'] ?? null);
    }

    #[TestDox('Hidden strategies remove prices and stock from the offer')]
    public function test_strategies_hide_prices_and_stock(): void
    {
        $context = $this->context(ProductMarkdownFixtures::variant('mug-s', CatalogFactory::channel(), 1000));

        $priceHidden = ProductMarkdownFixtures::offerBuilder(new ProductMarkdownOptions(priceStrategy: PriceStrategy::Hidden), $this->calculator())->build($context, 'https://shop.example.com/p');
        $allHidden = ProductMarkdownFixtures::offerBuilder(new ProductMarkdownOptions(stockStrategy: StockStrategy::Hidden, priceStrategy: PriceStrategy::Hidden), $this->calculator())->build($context, 'https://shop.example.com/p');

        self::assertArrayNotHasKey('price', $priceHidden ?? []);
        self::assertArrayHasKey('availability', $priceHidden ?? []);
        self::assertNull($allHidden);
    }

    private function calculator(): ProductVariantPricesCalculatorInterface
    {
        $calculator = $this->createStub(ProductVariantPricesCalculatorInterface::class);
        $calculator->method('calculate')->willReturnCallback(
            static fn (ProductVariantInterface $variant, array $context): int => (int) $variant->getChannelPricingForChannel($context['channel'])?->getPrice(),
        );

        return $calculator;
    }

    private function context(ProductVariantInterface $variant): ProductMarkdownContext
    {
        $channel = CatalogFactory::channel();
        $product = CatalogFactory::product('mug', $channel);
        $product->addVariant($variant);

        return new ProductMarkdownContext($product, $channel, 'en_US');
    }
}
