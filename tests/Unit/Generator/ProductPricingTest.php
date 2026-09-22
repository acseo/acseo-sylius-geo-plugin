<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Generator\ProductPricing;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Exception\MissingChannelConfigurationException;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Model\Currency;

final class ProductPricingTest extends TestCase
{
    private ProductVariantPricesCalculatorInterface&MockObject $calculator;

    private Channel $channel;

    protected function setUp(): void
    {
        $currency = new Currency();
        $currency->setCode('EUR');
        $this->channel = new Channel();
        $this->channel->setCode('WEB');
        $this->channel->setBaseCurrency($currency);

        $this->calculator = $this->createMock(ProductVariantPricesCalculatorInterface::class);
    }

    public function testCalculatedStrategyUsesTheSyliusCalculator(): void
    {
        $variant = $this->variant('A', price: 2000);
        $this->calculator->method('calculate')->willReturn(1500);

        self::assertSame('15.00 EUR', $this->pricing()->formatVariantPrice($variant, $this->channel, 'en_US'));
    }

    public function testBaseStrategyUsesTheRawChannelPricing(): void
    {
        $variant = $this->variant('A', price: 2000, originalPrice: 2000);
        $this->calculator->expects(self::never())->method('calculate');

        $pricing = $this->pricing(new ProductMarkdownOptions(priceStrategy: ProductMarkdownOptions::PRICE_STRATEGY_BASE));

        self::assertSame('20.00 EUR', $pricing->formatVariantPrice($variant, $this->channel, 'en_US'));
    }

    public function testItShowsTheOriginalPriceWhenReduced(): void
    {
        $variant = $this->variant('A', price: 1500, originalPrice: 2500);
        $pricing = $this->pricing(new ProductMarkdownOptions(priceStrategy: ProductMarkdownOptions::PRICE_STRATEGY_BASE));

        self::assertSame('15.00 EUR (original price: 25.00 EUR)', $pricing->formatVariantPrice($variant, $this->channel, 'en_US'));
        self::assertSame([
            'price' => '15.00',
            'priceCurrency' => 'EUR',
            'priceSpecification' => [
                '@type' => 'UnitPriceSpecification',
                'price' => '25.00',
                'priceCurrency' => 'EUR',
                'name' => 'Original price',
            ],
        ], $pricing->jsonLdPriceProperties($variant, $this->channel));
    }

    public function testItIgnoresAnOriginalPriceThatIsNotHigher(): void
    {
        $variant = $this->variant('A', price: 1500, originalPrice: 1500);
        $pricing = $this->pricing(new ProductMarkdownOptions(priceStrategy: ProductMarkdownOptions::PRICE_STRATEGY_BASE));

        self::assertSame('15.00 EUR', $pricing->formatVariantPrice($variant, $this->channel, 'en_US'));
        self::assertSame(['price' => '15.00', 'priceCurrency' => 'EUR'], $pricing->jsonLdPriceProperties($variant, $this->channel));
    }

    public function testItExposesNothingWhenPricesAreHidden(): void
    {
        $variant = $this->variant('A', price: 1500, originalPrice: null);
        $pricing = $this->pricing(new ProductMarkdownOptions(priceStrategy: ProductMarkdownOptions::PRICE_STRATEGY_HIDDEN));

        self::assertNull($pricing->formatVariantPrice($variant, $this->channel, 'en_US'));
        self::assertNull($pricing->jsonLdPriceProperties($variant, $this->channel));
    }

    public function testItExposesNothingWithoutBaseCurrency(): void
    {
        $channel = new Channel();
        $channel->setCode('WEB');
        $variant = $this->variant('A', price: 1500, originalPrice: null);
        $pricing = $this->pricing(new ProductMarkdownOptions(priceStrategy: ProductMarkdownOptions::PRICE_STRATEGY_BASE));

        self::assertNull($pricing->formatVariantPrice($variant, $channel, 'en_US'));
        self::assertNull($pricing->jsonLdPriceProperties($variant, $channel));
    }

    public function testItExposesNothingWhenTheCalculatorHasNoChannelConfiguration(): void
    {
        $variant = $this->variant('A', price: 1500, originalPrice: null);
        $this->calculator->method('calculate')->willThrowException(new MissingChannelConfigurationException('No pricing.'));

        self::assertNull($this->pricing()->formatVariantPrice($variant, $this->channel, 'en_US'));
    }

    public function testTheReferenceVariantIsTheCheapestPricedOne(): void
    {
        $product = $this->product([$this->variant('L', 3000), $this->variant('S', 1500), $this->variant('M', 2000)]);
        $pricing = $this->pricing(new ProductMarkdownOptions(priceStrategy: ProductMarkdownOptions::PRICE_STRATEGY_BASE));

        self::assertSame('S', $pricing->referenceVariant($product, $this->channel)?->getCode());
        self::assertSame('15.00 EUR', $pricing->formatProductPrice($product, $this->channel, 'en_US'));
    }

    public function testTheReferenceVariantFallsBackToTheFirstWhenNoneIsPriced(): void
    {
        $product = $this->product([$this->variant('FIRST', null), $this->variant('SECOND', null)]);
        $pricing = $this->pricing(new ProductMarkdownOptions(priceStrategy: ProductMarkdownOptions::PRICE_STRATEGY_BASE));

        self::assertSame('FIRST', $pricing->referenceVariant($product, $this->channel)?->getCode());
        self::assertNull($pricing->formatProductPrice($product, $this->channel, 'en_US'));
    }

    public function testAProductWithoutEnabledVariantHasNoReferenceVariant(): void
    {
        $variant = $this->variant('A', 1500);
        $variant->setEnabled(false);
        $product = $this->product([$variant]);

        self::assertNull($this->pricing()->referenceVariant($product, $this->channel));
        self::assertNull($this->pricing()->formatProductPrice($product, $this->channel, 'en_US'));
    }

    private function pricing(?ProductMarkdownOptions $options = null): ProductPricing
    {
        $formatter = $this->createMock(MoneyFormatterInterface::class);
        $formatter->method('format')->willReturnCallback(static fn (int $amount, string $currency): string => \sprintf('%.2f %s', $amount / 100, $currency));

        return new ProductPricing($this->calculator, $formatter, $options ?? new ProductMarkdownOptions());
    }

    /** @param list<ProductVariant> $variants */
    private function product(array $variants): Product
    {
        $product = new Product();
        $product->setCode('P');
        foreach ($variants as $variant) {
            $product->addVariant($variant);
        }

        return $product;
    }

    private function variant(string $code, ?int $price, ?int $originalPrice = null): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setCode($code);

        if (null !== $price) {
            $pricing = new ChannelPricing();
            $pricing->setChannelCode('WEB');
            $pricing->setPrice($price);
            $pricing->setOriginalPrice($originalPrice);
            $variant->addChannelPricing($pricing);
        }

        return $variant;
    }
}
