<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\ProductMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Exception\ProductNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGenerator;
use ACSEO\SyliusGeoPlugin\Generator\ProductPricing;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Provider\ProductMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ProductMarkdownGeneratorTest extends TestCase
{
    private ProductGeoVisibilityCheckerInterface&MockObject $visibilityChecker;

    private ProductVariantPricesCalculatorInterface&MockObject $priceCalculator;

    private MoneyFormatterInterface&MockObject $moneyFormatter;

    private ProductMarkdownGenerator $generator;

    protected function setUp(): void
    {
        $this->visibilityChecker = $this->createMock(ProductGeoVisibilityCheckerInterface::class);
        $this->priceCalculator = $this->createMock(ProductVariantPricesCalculatorInterface::class);
        $this->moneyFormatter = $this->createMock(MoneyFormatterInterface::class);

        $this->generator = new ProductMarkdownGenerator(
            $this->visibilityChecker,
            new ProductPricing($this->priceCalculator, $this->moneyFormatter),
            new GeoUrlBuilder(),
        );
    }

    public function testItGeneratesMarkdownWithNameDescriptionPriceAndCanonicalUrl(): void
    {
        $channel = $this->createChannel();
        $optionValue = $this->createMock(ProductOptionValueInterface::class);
        $optionValue->method('getName')->willReturn('Large');
        $optionValue->method('getValue')->willReturn('large');
        $optionValue->method('getCode')->willReturn('large');
        $optionValue->expects(self::any())->method('setCurrentLocale');
        $optionValue->expects(self::any())->method('setFallbackLocale');
        $variant = $this->createVariant('BLUE-MUG', 'Blue Mug / Large', true, [$optionValue]);
        $product = $this->createProduct('Blue Mug', 'blue-mug', '<p>Nice mug</p>', $variant);

        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);
        $this->priceCalculator->method('calculate')->with($variant, ['channel' => $channel])->willReturn(1999);
        $this->moneyFormatter
            ->method('format')
            ->willReturnCallback(static fn (int $amount): string => match ($amount) {
                1999 => '€19.99',
                2499 => '€24.99',
                default => (string) $amount,
            })
        ;

        $output = $this->generator->generate($product, $channel, 'en_US');

        self::assertStringContainsString('# Blue Mug', $output);
        self::assertStringContainsString('Nice mug', $output);
        self::assertStringContainsString('## Brand', $output);
        self::assertStringContainsString('ACSEO', $output);
        self::assertStringContainsString('## Price', $output);
        self::assertStringContainsString('€19.99', $output);
        self::assertStringContainsString('original price: €24.99', $output);
        self::assertStringContainsString('## Availability', $output);
        self::assertStringContainsString("Availability\n\nyes", $output);
        self::assertStringContainsString('## Attributes', $output);
        self::assertStringContainsString('- Material: ceramic', $output);
        self::assertStringContainsString('## Variants', $output);
        self::assertStringContainsString('- Blue Mug / Large | Large | €19.99 (original price: €24.99) | available: yes', $output);
        self::assertStringContainsString('## Images', $output);
        self::assertStringContainsString('- https://fashion.example/media/image/products/blue-mug.jpg', $output);
        self::assertStringContainsString('## Taxons', $output);
        self::assertStringContainsString('[Mugs](https://fashion.example/en_US/taxons/mugs)', $output);
        self::assertStringContainsString('## Canonical URL', $output);
        self::assertStringContainsString('https://fashion.example/en_US/products/blue-mug', $output);
        self::assertStringContainsString('## Structured Data', $output);
        self::assertStringContainsString('"@context": "https://schema.org"', $output);
        self::assertStringContainsString('"@type": "Product"', $output);
        self::assertStringContainsString('"sku": "BLUE-MUG"', $output);
        self::assertStringContainsString('"brand": {', $output);
        self::assertStringContainsString('"price": "19.99"', $output);
        self::assertStringContainsString('"priceCurrency": "EUR"', $output);
        self::assertStringContainsString('"availability": "https://schema.org/InStock"', $output);
        self::assertStringContainsString('"image": [', $output);
        self::assertStringNotContainsString('<p>', $output);
    }

    public function testItThrowsWhenProductIsNotVisible(): void
    {
        $channel = $this->createChannel();
        $product = $this->createProduct('Disabled', 'disabled', null, null);

        $this->visibilityChecker->method('isVisible')->willReturn(false);

        $this->expectException(ProductNotAvailableForGeoException::class);

        $this->generator->generate($product, $channel, 'en_US');
    }

    public function testItCanHideOptionalMarkdownSections(): void
    {
        $generator = new ProductMarkdownGenerator(
            $this->visibilityChecker,
            new ProductPricing($this->priceCalculator, $this->moneyFormatter, new ProductMarkdownOptions(includeBrand: false, priceStrategy: 'hidden', stockStrategy: 'hidden', includeImages: false, includeAttributes: false, includeVariants: false, includeTaxons: false, includeJsonLd: false)),
            new GeoUrlBuilder(),
            options: new ProductMarkdownOptions(includeBrand: false, priceStrategy: 'hidden', stockStrategy: 'hidden', includeImages: false, includeAttributes: false, includeVariants: false, includeTaxons: false, includeJsonLd: false),
        );
        $channel = $this->createChannel();
        $variant = $this->createVariant('BLUE-MUG', 'Blue Mug / Large', true);
        $product = $this->createProduct('Blue Mug', 'blue-mug', '<p>Nice mug</p>', $variant);

        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);
        $this->priceCalculator->expects(self::never())->method('calculate');

        $output = $generator->generate($product, $channel, 'en_US');

        self::assertStringContainsString('# Blue Mug', $output);
        self::assertStringContainsString('Nice mug', $output);
        self::assertStringContainsString('## Canonical URL', $output);
        self::assertStringNotContainsString('## Brand', $output);
        self::assertStringNotContainsString('## Price', $output);
        self::assertStringNotContainsString('## Availability', $output);
        self::assertStringNotContainsString('## Attributes', $output);
        self::assertStringNotContainsString('## Variants', $output);
        self::assertStringNotContainsString('## Images', $output);
        self::assertStringNotContainsString('## Taxons', $output);
        self::assertStringNotContainsString('## Structured Data', $output);
    }

    public function testItCanUseTaxExcludedPriceStrategy(): void
    {
        $generator = new ProductMarkdownGenerator(
            $this->visibilityChecker,
            new ProductPricing($this->priceCalculator, $this->moneyFormatter, new ProductMarkdownOptions(priceStrategy: 'base', includeJsonLd: false)),
            new GeoUrlBuilder(),
            options: new ProductMarkdownOptions(priceStrategy: 'base', includeJsonLd: false),
        );
        $channel = $this->createChannel();
        $variant = $this->createVariant('BLUE-MUG', 'Blue Mug / Large', true, channelPrice: 1666);
        $product = $this->createProduct('Blue Mug', 'blue-mug', '<p>Nice mug</p>', $variant);

        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);
        $this->priceCalculator->expects(self::never())->method('calculate');
        $this->moneyFormatter
            ->method('format')
            ->willReturnCallback(static fn (int $amount): string => match ($amount) {
                1666 => '€16.66',
                2499 => '€24.99',
                default => (string) $amount,
            })
        ;

        $output = $generator->generate($product, $channel, 'en_US');

        self::assertStringContainsString('€16.66', $output);
    }

    public function testItCanExposeAvailabilityOnlyStockStrategy(): void
    {
        $generator = new ProductMarkdownGenerator(
            $this->visibilityChecker,
            new ProductPricing($this->priceCalculator, $this->moneyFormatter, new ProductMarkdownOptions(stockStrategy: 'availability_only', priceStrategy: 'hidden', includeJsonLd: false)),
            new GeoUrlBuilder(),
            options: new ProductMarkdownOptions(stockStrategy: 'availability_only', priceStrategy: 'hidden', includeJsonLd: false),
        );
        $channel = $this->createChannel();
        $variant = $this->createVariant('BLUE-MUG', 'Blue Mug / Large', true);
        $product = $this->createProduct('Blue Mug', 'blue-mug', '<p>Nice mug</p>', $variant);

        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);

        $output = $generator->generate($product, $channel, 'en_US');

        self::assertStringContainsString('## Availability', $output);
        self::assertStringContainsString("Availability\n\nyes", $output);
        self::assertStringNotContainsString('available: yes', $output);
    }

    public function testItCanHidePriceWithPriceStrategy(): void
    {
        $generator = new ProductMarkdownGenerator(
            $this->visibilityChecker,
            new ProductPricing($this->priceCalculator, $this->moneyFormatter, new ProductMarkdownOptions(priceStrategy: 'hidden')),
            new GeoUrlBuilder(),
            options: new ProductMarkdownOptions(priceStrategy: 'hidden'),
        );
        $channel = $this->createChannel();
        $variant = $this->createVariant('BLUE-MUG', 'Blue Mug / Large', true);
        $product = $this->createProduct('Blue Mug', 'blue-mug', '<p>Nice mug</p>', $variant);

        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);
        $this->priceCalculator->expects(self::never())->method('calculate');

        $output = $generator->generate($product, $channel, 'en_US');

        self::assertStringNotContainsString('## Price', $output);
        self::assertStringNotContainsString('"price"', $output);
    }

    public function testItSkipsVariantsWithoutPrices(): void
    {
        $generator = new ProductMarkdownGenerator(
            $this->visibilityChecker,
            new ProductPricing($this->priceCalculator, $this->moneyFormatter, new ProductMarkdownOptions(priceStrategy: 'base', stockStrategy: 'hidden', includeJsonLd: false)),
            new GeoUrlBuilder(),
            options: new ProductMarkdownOptions(priceStrategy: 'base', stockStrategy: 'hidden', includeJsonLd: false),
        );
        $channel = $this->createChannel();
        $variant = $this->createVariant('BLUE-MUG', 'Blue Mug / Large', true, channelPrice: null);
        $product = $this->createProduct('Blue Mug', 'blue-mug', '<p>Nice mug</p>', $variant);

        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);
        $this->priceCalculator->expects(self::never())->method('calculate');
        $this->moneyFormatter->expects(self::never())->method('format');

        $output = $generator->generate($product, $channel, 'en_US');

        self::assertStringNotContainsString('## Price', $output);
        self::assertStringContainsString('## Variants', $output);
        self::assertStringContainsString('- Blue Mug / Large', $output);
        self::assertStringNotContainsString('€', $output);
    }

    public function testItAppendsCustomProviderSections(): void
    {
        $channel = $this->createChannel();
        $variant = $this->createVariant('BLUE-MUG', 'Blue Mug / Large', true);
        $product = $this->createProduct('Blue Mug', 'blue-mug', '<p>Nice mug</p>', $variant);
        $generator = new ProductMarkdownGenerator(
            $this->visibilityChecker,
            new ProductPricing($this->priceCalculator, $this->moneyFormatter, new ProductMarkdownOptions(includeJsonLd: false)),
            new GeoUrlBuilder(),
            options: new ProductMarkdownOptions(includeJsonLd: false),
            sectionProviders: [new class() implements ProductMarkdownSectionProviderInterface {
                public function supports(ProductInterface $product, ChannelInterface $channel, string $localeCode): bool
                {
                    return true;
                }

                public function provide(ProductInterface $product, ChannelInterface $channel, string $localeCode): array
                {
                    return [new MarkdownSection('Eco score', ['A'])];
                }
            }],
        );

        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);
        $this->priceCalculator->method('calculate')->willReturn(1999);
        $this->moneyFormatter->method('format')->willReturn('€19.99');

        $output = $generator->generate($product, $channel, 'en_US');

        self::assertStringContainsString("## Eco score\n\nA", $output);
    }

    public function testItDispatchesBeforeProductMarkdownGenerationEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GeoEvents::BEFORE_PRODUCT_MARKDOWN_GENERATION, static function (ProductMarkdownGenerationEvent $event): void {
            $event->addSection(new MarkdownSection('Merchandising note', [
                \sprintf('Channel: %s', (string) $event->getChannel()->getCode()),
            ]));
        });
        $generator = new ProductMarkdownGenerator(
            $this->visibilityChecker,
            new ProductPricing($this->priceCalculator, $this->moneyFormatter, new ProductMarkdownOptions(priceStrategy: 'hidden', includeJsonLd: false)),
            new GeoUrlBuilder(),
            options: new ProductMarkdownOptions(priceStrategy: 'hidden', includeJsonLd: false),
            eventDispatcher: $dispatcher,
        );
        $channel = $this->createChannel();
        $variant = $this->createVariant('BLUE-MUG', 'Blue Mug / Large', true);
        $product = $this->createProduct('Blue Mug', 'blue-mug', '<p>Nice mug</p>', $variant);

        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);

        $output = $generator->generate($product, $channel, 'en_US');

        self::assertStringContainsString("## Merchandising note\n\nChannel: FASHION_WEB", $output);
    }

    public function testItSanitizesMarkdownSensitiveProductFields(): void
    {
        $channel = $this->createChannel();
        $variant = $this->createVariant('BLUE-MUG', "Blue\nMug", true);
        $product = $this->createProduct('Blue] Mug', 'blue-mug', "<p>Nice\nmug</p>", $variant);

        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);
        $this->priceCalculator->method('calculate')->willReturn(1999);
        $this->moneyFormatter->method('format')->willReturn('€19.99');

        $output = $this->generator->generate($product, $channel, 'en_US');

        self::assertStringContainsString('# Blue] Mug', $output);
        self::assertStringContainsString('- Blue Mug | €19.99', $output);
        self::assertStringContainsString('[Mugs](https://fashion.example/en_US/taxons/mugs)', $output);
        self::assertStringNotContainsString("Blue\nMug", $output);
    }

    private function createChannel(): ChannelInterface
    {
        $currency = $this->createMock(CurrencyInterface::class);
        $currency->method('getCode')->willReturn('EUR');

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('FASHION_WEB');
        $channel->method('getHostname')->willReturn('fashion.example');
        $channel->method('getBaseCurrency')->willReturn($currency);

        return $channel;
    }

    /** @param list<ProductOptionValueInterface> $optionValues */
    private function createVariant(
        string $code,
        string $name,
        bool $inStock,
        array $optionValues = [],
        ?int $channelPrice = 1999,
    ): ProductVariantInterface&MockObject {
        $channelPricing = $this->createMock(ChannelPricingInterface::class);
        $channelPricing->method('getPrice')->willReturn($channelPrice);
        $channelPricing->method('getOriginalPrice')->willReturn(2499);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getCode')->willReturn($code);
        $variant->method('getName')->willReturn($name);
        $variant->method('isTracked')->willReturn(true);
        $variant->method('getOnHand')->willReturn($inStock ? 5 : 0);
        $variant->method('getOnHold')->willReturn(0);
        $variant->method('getChannelPricingForChannel')->willReturn($channelPricing);
        $variant->method('getOptionValues')->willReturn(new ArrayCollection($optionValues));

        return $variant;
    }

    private function createProduct(
        string $name,
        string $slug,
        ?string $description,
        ?ProductVariantInterface $variant,
    ): ProductInterface {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn(strtoupper($slug));
        $product->method('getName')->willReturn($name);
        $product->method('getSlug')->willReturn($slug);
        $product->method('getDescription')->willReturn($description);
        $product->method('getShortDescription')->willReturn(null);
        $product->method('getEnabledVariants')->willReturn(new ArrayCollection(
            null === $variant ? [] : [$variant],
        ));
        $product->method('getAttributesByLocale')->willReturn(new ArrayCollection([
            $this->createAttribute('brand', 'Brand', 'ACSEO'),
            $this->createAttribute('material', 'Material', 'ceramic'),
        ]));
        $product->method('getAttributes')->willReturn(new ArrayCollection([
            $this->createAttribute('brand', 'Brand', 'ACSEO'),
            $this->createAttribute('material', 'Material', 'ceramic'),
        ]));
        $image = $this->createMock(ProductImageInterface::class);
        $image->method('getPath')->willReturn('products/blue-mug.jpg');
        $product->method('getImages')->willReturn(new ArrayCollection([$image]));
        $taxon = $this->createTaxon('Mugs', 'mugs');
        $productTaxon = $this->createMock(ProductTaxonInterface::class);
        $productTaxon->method('getTaxon')->willReturn($taxon);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection([$productTaxon]));
        $product->expects(self::any())->method('setCurrentLocale');
        $product->expects(self::any())->method('setFallbackLocale');

        return $product;
    }

    private function createAttribute(string $code, string $name, mixed $value): AttributeValueInterface
    {
        $attribute = $this->createMock(AttributeValueInterface::class);
        $attribute->method('getCode')->willReturn($code);
        $attribute->method('getName')->willReturn($name);
        $attribute->method('getValue')->willReturn($value);

        return $attribute;
    }

    private function createTaxon(string $name, string $slug): TaxonInterface
    {
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getCode')->willReturn(strtoupper($slug));
        $taxon->method('getName')->willReturn($name);
        $taxon->method('getSlug')->willReturn($slug);
        $taxon->expects(self::any())->method('setCurrentLocale');
        $taxon->expects(self::any())->method('setFallbackLocale');

        return $taxon;
    }
}
