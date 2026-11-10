<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\ProductMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Exception\ProductNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownContext;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownSectionBuilderInterface;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGenerator;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSectionRenderer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Provider\ProductMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;
use Tests\ACSEO\SyliusGeoPlugin\Support\ProductMarkdownFixtures;
use Tests\ACSEO\SyliusGeoPlugin\Support\TranslatorFactory;

final class ProductMarkdownGeneratorTest extends TestCase
{
    #[TestDox('A product rejected by the visibility checker is not exported')]
    public function test_hidden_product_is_not_exported(): void
    {
        $this->expectException(ProductNotAvailableForGeoException::class);

        $this->generate(CatalogFactory::product('mug', CatalogFactory::channel()), visible: false);
    }

    #[TestDox('A product without a slug in the locale is not exported')]
    public function test_product_without_slug_is_not_exported(): void
    {
        $product = CatalogFactory::product('mug', CatalogFactory::channel());
        $product->setSlug(null);

        $this->expectException(ProductNotAvailableForGeoException::class);

        $this->generate($product);
    }

    #[TestDox('The page lists the description, the built-in sections, the event sections, the provider sections and the canonical URL in order')]
    public function test_document_structure(): void
    {
        $product = CatalogFactory::product('mug', CatalogFactory::channel());
        $product->setDescription('A <b>sturdy</b> mug');

        $content = $this->generate($product);

        self::assertSame(['# Mug', '## Built-in', '## From event', '## From provider', '## Canonical URL'], $this->headings($content));
        self::assertStringContainsString("A sturdy mug\n", $content);
        self::assertStringContainsString("https://shop.example.com/en_US/products/mug\n", $content);
        self::assertStringContainsString("- [x](#)\n", $content);
    }

    #[TestDox('The structured data is appended when enabled')]
    public function test_structured_data_is_appended_when_enabled(): void
    {
        $product = CatalogFactory::product('mug', CatalogFactory::channel());

        self::assertSame(['# Mug', '## Built-in', '## From event', '## From provider', '## Canonical URL', '## Structured Data'], $this->headings($this->generate($product, new ProductMarkdownOptions())));
        self::assertStringContainsString('"@type": "Product"', $this->generate($product, new ProductMarkdownOptions()));
        self::assertNotContains('## Structured Data', $this->headings($this->generate($product, new ProductMarkdownOptions(includeJsonLd: false))));
    }

    /** @return list<string> */
    private function headings(string $content): array
    {
        return array_values(array_filter(explode("\n", $content), static fn (string $line): bool => str_starts_with($line, '#')));
    }

    private function generate(ProductInterface $product, ?ProductMarkdownOptions $options = null, bool $visible = true): string
    {
        $options ??= new ProductMarkdownOptions(includeJsonLd: false);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GeoEvents::BEFORE_PRODUCT_MARKDOWN_GENERATION, static function (ProductMarkdownGenerationEvent $event): void {
            $event->addSection(new MarkdownSection('From event', ['- [x](javascript:alert(1))']));
        });

        $checker = new readonly class($visible) implements ProductGeoVisibilityCheckerInterface {
            public function __construct(private bool $visible)
            {
            }

            public function isVisible(ProductInterface $product, ChannelInterface $channel): bool
            {
                return $this->visible;
            }
        };
        $builder = new class() implements ProductMarkdownSectionBuilderInterface {
            public function build(ProductMarkdownContext $context): MarkdownSection
            {
                return new MarkdownSection('Built-in', ['content']);
            }
        };
        $provider = new class() implements ProductMarkdownSectionProviderInterface {
            public function supports(ProductInterface $product, ChannelInterface $channel, string $localeCode): bool
            {
                return true;
            }

            public function provide(ProductInterface $product, ChannelInterface $channel, string $localeCode): array
            {
                return [new MarkdownSection('From provider', ['provided'])];
            }
        };

        $sanitizer = new MarkdownSanitizer();
        $texts = TranslatorFactory::crawlerTexts();

        return (new ProductMarkdownGenerator(
            $checker,
            new ResourceLocalizer(),
            new GeoUrlBuilder(),
            ProductMarkdownFixtures::jsonLdBuilder($options, $this->createStub(ProductVariantPricesCalculatorInterface::class)),
            new MarkdownSectionRenderer($sanitizer),
            $sanitizer,
            $texts,
            $dispatcher,
            [$builder],
            [$provider],
        ))->generate($product, CatalogFactory::channel(), CatalogFactory::LOCALE);
    }
}
