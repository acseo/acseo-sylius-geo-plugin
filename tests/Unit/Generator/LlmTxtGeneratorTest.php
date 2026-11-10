<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\LlmTxtGenerationEvent;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\ChannelSection;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\CrawlerHintsSection;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\CrawlerPolicySection;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\LlmTxtChannelInfo;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\LlmTxtHeader;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\ProductsSection;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\TaxonsSection;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGenerator;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSectionRenderer;
use ACSEO\SyliusGeoPlugin\Model\ChannelInfoOptions;
use ACSEO\SyliusGeoPlugin\Model\CrawlerPolicyOptions;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Provider\LlmTxtSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;
use Tests\ACSEO\SyliusGeoPlugin\Support\TranslatorFactory;

final class LlmTxtGeneratorTest extends TestCase
{
    #[TestDox('The index lists the channel facts, the crawler policy, the taxons and the products')]
    public function test_full_index(): void
    {
        $product = CatalogFactory::product('mug', CatalogFactory::channel());
        $product->setShortDescription('A <b>sturdy</b> mug');

        $content = $this->generate(products: [$product], taxons: [CatalogFactory::taxon('mugs')]);

        self::assertSame(['# Web store', '## Channel', '## Crawler policy', '## Crawler hints', '## Taxons', '## Products'], $this->headings($content));
        self::assertStringContainsString("> Locale: en_US. Currency: USD. Available locales: en_US.\n", $content);
        self::assertStringContainsString("- Base URL: https://shop.example.com\n", $content);
        self::assertStringContainsString("- AI crawler access: allowed for public GEO endpoints\n- First hint\n", $content);
        self::assertStringContainsString("- Product Markdown pattern: https://shop.example.com/en_US/geo/products/{slug}.md\n", $content);
        self::assertStringContainsString("- [Mugs](https://shop.example.com/en_US/taxons/mugs)\n", $content);
        self::assertStringContainsString("- [Mug](https://shop.example.com/en_US/geo/products/mug.md): A sturdy mug\n", $content);
        self::assertStringNotContainsString('Channel code', $content);
    }

    #[TestDox('Empty catalogs are announced instead of leaving empty sections')]
    public function test_empty_catalogs_are_announced(): void
    {
        $content = $this->generate();

        self::assertStringContainsString('- No enabled taxons are currently listed for this channel.', $content);
        self::assertStringContainsString('- No enabled products are currently listed for this channel.', $content);
    }

    #[TestDox('Disabling the channel information removes its section and reduces the summary')]
    public function test_channel_info_can_be_disabled(): void
    {
        $content = $this->generate(channelInfo: new ChannelInfoOptions(enabled: false, includeChannelCode: true));

        self::assertNotContains('## Channel', $this->headings($content));
        self::assertStringContainsString("> Locale: en_US. Currency: USD.\n", $content);
        self::assertStringNotContainsString('WEB', $content);
    }

    #[TestDox('The channel code is published only on request')]
    public function test_channel_code_is_opt_in(): void
    {
        $content = $this->generate(channelInfo: new ChannelInfoOptions(includeChannelCode: true));

        self::assertStringContainsString('> Channel: WEB. Locale: en_US.', $content);
        self::assertStringContainsString('- Channel code: WEB', $content);
    }

    #[TestDox('The crawler policy states when AI crawlers are not allowed')]
    public function test_crawler_policy_can_refuse_ai_crawlers(): void
    {
        $content = $this->generate(policy: new CrawlerPolicyOptions(allowAiCrawlers: false, hints: ['default' => ['Stay out']]));

        self::assertStringContainsString("- AI crawler access: not allowed; do not crawl or use GEO endpoints\n- Stay out\n", $content);
    }

    #[TestDox('The crawler policy can be removed and hints follow the locale')]
    public function test_crawler_policy_can_be_removed(): void
    {
        self::assertNotContains('## Crawler policy', $this->headings($this->generate(policy: new CrawlerPolicyOptions(enabled: false))));

        $localized = new CrawlerPolicyOptions(hints: ['default' => ['Default hint'], 'en_US' => ['Local hint']]);
        self::assertStringContainsString('- Local hint', $this->generate(policy: $localized));
        self::assertStringNotContainsString('Default hint', $this->generate(policy: $localized));
    }

    #[TestDox('Event sections come before the crawler hints and provider sections after them, both sanitized')]
    public function test_extension_sections_are_rendered_in_order_and_sanitized(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GeoEvents::BEFORE_LLM_TXT_GENERATION, static function (LlmTxtGenerationEvent $event): void {
            $event->addSection(new MarkdownSection('From event', ['- [x](javascript:alert(1))']));
        });
        $provider = new class() implements LlmTxtSectionProviderInterface {
            public function supports(ChannelInterface $channel, string $localeCode): bool
            {
                return true;
            }

            public function provide(ChannelInterface $channel, string $localeCode): array
            {
                return [new MarkdownSection('From provider', ['- <i>provided</i>'])];
            }
        };

        $content = $this->generate(dispatcher: $dispatcher, providers: [$provider]);

        self::assertSame(
            ['# Web store', '## Channel', '## Crawler policy', '## From event', '## Crawler hints', '## From provider', '## Taxons', '## Products'],
            $this->headings($content),
        );
        self::assertStringContainsString('- [x](#)', $content);
        self::assertStringContainsString("- provided\n", $content);
    }

    /** @return list<string> */
    private function headings(string $content): array
    {
        return array_values(array_filter(explode("\n", $content), static fn (string $line): bool => str_starts_with($line, '#')));
    }

    /**
     * @param list<ProductInterface> $products
     * @param list<TaxonInterface> $taxons
     * @param list<LlmTxtSectionProviderInterface> $providers
     */
    private function generate(
        array $products = [],
        array $taxons = [],
        ?ChannelInfoOptions $channelInfo = null,
        ?CrawlerPolicyOptions $policy = null,
        ?EventDispatcher $dispatcher = null,
        array $providers = [],
    ): string {
        $channelInfo ??= new ChannelInfoOptions();
        $policy ??= new CrawlerPolicyOptions(hints: ['default' => ['First hint']]);
        $texts = TranslatorFactory::crawlerTexts();
        $sanitizer = new MarkdownSanitizer();
        $urls = new GeoUrlBuilder();
        $localizer = new ResourceLocalizer();
        $info = new LlmTxtChannelInfo($channelInfo, $urls, $texts);

        $productCatalog = new readonly class($products) implements ChannelProductCatalogInterface {
            /** @param list<ProductInterface> $products */
            public function __construct(private array $products)
            {
            }

            public function findEnabledProducts(ChannelInterface $channel, string $localeCode): array
            {
                return $this->products;
            }

            public function findCandidateProducts(ChannelInterface $channel, string $localeCode): array
            {
                return $this->products;
            }
        };
        $taxonCatalog = new readonly class($taxons) implements ChannelTaxonCatalogInterface {
            /** @param list<TaxonInterface> $taxons */
            public function __construct(private array $taxons)
            {
            }

            public function findEnabledTaxons(ChannelInterface $channel, string $localeCode): array
            {
                return $this->taxons;
            }

            public function findCandidateTaxons(ChannelInterface $channel, string $localeCode): array
            {
                return $this->taxons;
            }
        };

        return (new LlmTxtGenerator(
            new LlmTxtHeader($channelInfo, $info, $sanitizer, $texts),
            new ChannelSection($channelInfo, $info, $sanitizer, $texts),
            new CrawlerPolicySection($policy, $sanitizer, $texts),
            new CrawlerHintsSection($urls, $texts),
            new TaxonsSection($taxonCatalog, $urls, $localizer, $sanitizer, $texts),
            new ProductsSection($productCatalog, $urls, $localizer, $sanitizer, $texts),
            new MarkdownSectionRenderer($sanitizer),
            $dispatcher ?? new EventDispatcher(),
            $providers,
        ))->generate(CatalogFactory::channel(), CatalogFactory::LOCALE);
    }
}
