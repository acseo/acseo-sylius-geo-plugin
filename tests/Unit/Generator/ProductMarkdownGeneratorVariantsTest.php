<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityChecker;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGenerator;
use ACSEO\SyliusGeoPlugin\Generator\ProductPricing;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
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

final class ProductMarkdownGeneratorVariantsTest extends TestCase
{
    private ProductVariantPricesCalculatorInterface&MockObject $priceCalculator;

    private Channel $channel;

    private ProductMarkdownGenerator $generator;

    protected function setUp(): void
    {
        $currency = new Currency();
        $currency->setCode('EUR');
        $this->channel = new Channel();
        $this->channel->setCode('FASHION_WEB');
        $this->channel->setHostname('fashion.example');
        $this->channel->setBaseCurrency($currency);

        $this->priceCalculator = $this->createMock(ProductVariantPricesCalculatorInterface::class);
        $this->priceCalculator
            ->method('calculate')
            ->willReturnCallback(static function (ProductVariant $variant, array $context): int {
                $price = $variant->getChannelPricingForChannel($context['channel'])?->getPrice();

                return $price ?? throw new MissingChannelConfigurationException('No pricing.');
            });

        $moneyFormatter = $this->createMock(MoneyFormatterInterface::class);
        $moneyFormatter->method('format')->willReturnCallback(static fn (int $amount): string => \sprintf('%.2f EUR', $amount / 100));

        $this->generator = new ProductMarkdownGenerator(
            new ProductGeoVisibilityChecker(),
            new ProductPricing($this->priceCalculator, $moneyFormatter),
            new GeoUrlBuilder(),
        );
    }

    public function testProductPriceIsTheCheapestEnabledVariantEvenWhenItIsNotTheFirst(): void
    {
        $product = $this->product([
            $this->variant('L', 3000),
            $this->variant('S', 1500),
            $this->variant('M', 2000),
        ]);

        $output = $this->generator->generate($product, $this->channel, 'en_US');

        self::assertStringContainsString("## Price\n\n15.00 EUR", $output);
        self::assertStringContainsString('"price": "15.00"', $output);
    }

    public function testJsonLdAvailabilityIsAggregatedAcrossVariants(): void
    {
        $outOfStock = $this->variant('S', 1500);
        $outOfStock->setTracked(true);
        $outOfStock->setOnHand(0);
        $product = $this->product([$outOfStock, $this->variant('M', 2000)]);

        $output = $this->generator->generate($product, $this->channel, 'en_US');

        self::assertStringContainsString("## Availability\n\nyes", $output);
        self::assertStringContainsString('"availability": "https://schema.org/InStock"', $output);
        self::assertStringContainsString('- S | 15.00 EUR | available: no', $output);
    }

    public function testAnUntrackedVariantIsAlwaysAvailable(): void
    {
        $untracked = $this->variant('S', 1500);
        $untracked->setTracked(false);
        $untracked->setOnHand(0);
        $product = $this->product([$untracked]);

        $output = $this->generator->generate($product, $this->channel, 'en_US');

        self::assertStringContainsString("## Availability\n\nyes", $output);
        self::assertStringContainsString('"availability": "https://schema.org/InStock"', $output);
        self::assertStringContainsString('available: yes', $output);
    }

    public function testStockHeldByCartsIsNotAvailable(): void
    {
        $held = $this->variant('S', 1500);
        $held->setOnHand(2);
        $held->setOnHold(2);
        $product = $this->product([$held]);

        $output = $this->generator->generate($product, $this->channel, 'en_US');

        self::assertStringContainsString("## Availability\n\nno", $output);
        self::assertStringContainsString('"availability": "https://schema.org/OutOfStock"', $output);
    }

    public function testNoPriceIsExposedWhenNoVariantIsPriced(): void
    {
        $product = $this->product([$this->variant('S', null)]);

        $output = $this->generator->generate($product, $this->channel, 'en_US');

        self::assertStringNotContainsString('## Price', $output);
    }

    /** @param list<ProductVariant> $variants */
    private function product(array $variants): Product
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('SHIRT');
        $product->setName('Shirt');
        $product->setSlug('shirt');
        $product->addChannel($this->channel);
        foreach ($variants as $variant) {
            $product->addVariant($variant);
        }

        return $product;
    }

    private function variant(string $code, ?int $price): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setCode($code);
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setName($code);
        $variant->setTracked(true);
        $variant->setOnHand(5);

        if (null !== $price) {
            $pricing = new ChannelPricing();
            $pricing->setChannelCode((string) $this->channel->getCode());
            $pricing->setPrice($price);
            $variant->addChannelPricing($pricing);
        }

        return $variant;
    }
}
