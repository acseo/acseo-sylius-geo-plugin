<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\LlmTxtGenerationEvent;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtChannelInfo;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGenerator;
use ACSEO\SyliusGeoPlugin\Model\LlmTxtOptions;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Provider\CmsPageLink;
use ACSEO\SyliusGeoPlugin\Provider\CmsPageProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\LlmTxtCmsPagesSectionProvider;
use ACSEO\SyliusGeoPlugin\Provider\LlmTxtSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class LlmTxtGeneratorTest extends TestCase
{
    private ChannelProductCatalogInterface&MockObject $productCatalog;

    private ChannelTaxonCatalogInterface&MockObject $taxonCatalog;

    private LlmTxtGenerator $generator;

    protected function setUp(): void
    {
        $this->productCatalog = $this->createMock(ChannelProductCatalogInterface::class);
        $this->taxonCatalog = $this->createMock(ChannelTaxonCatalogInterface::class);
        $this->generator = new LlmTxtGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            new GeoUrlBuilder(),
            new LlmTxtChannelInfo(new LlmTxtOptions(), new GeoUrlBuilder()),
        );
    }

    public function testItGeneratesSummaryLinksAndGuidanceForChannel(): void
    {
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');
        $product = $this->createProduct('Mug', 'mug', 'Ceramic mug');
        $taxon = $this->createTaxon('Caps', 'caps');

        $this->productCatalog->method('findEnabledProducts')->with($channel, 'en_US')->willReturn([$product]);
        $this->taxonCatalog->method('findEnabledTaxons')->with($channel, 'en_US')->willReturn([$taxon]);

        $output = $this->generator->generate($channel, 'en_US');

        self::assertStringContainsString('# Fashion Web', $output);
        self::assertStringContainsString('channel FASHION_WEB', $output);
        self::assertStringContainsString('Locale: en_US.', $output);
        self::assertStringContainsString('Currency: EUR.', $output);
        self::assertStringContainsString('## Channel', $output);
        self::assertStringContainsString('- Shop name: Fashion Web', $output);
        self::assertStringContainsString('- Channel code: FASHION_WEB', $output);
        self::assertStringContainsString('- Base URL: https://fashion.example', $output);
        self::assertStringContainsString('- Currencies: EUR, USD', $output);
        self::assertStringContainsString('- Locales: en_US', $output);
        self::assertStringContainsString('- Served countries: France', $output);
        self::assertStringContainsString('## Crawler policy', $output);
        self::assertStringContainsString('- AI crawler access: allowed for public GEO endpoints', $output);
        self::assertStringContainsString('## Crawler hints', $output);
        self::assertStringContainsString('https://fashion.example/en_US/llm.txt', $output);
        self::assertStringContainsString('https://fashion.example/llm.txt', $output);
        self::assertStringContainsString('/geo/products/{slug}.md', $output);
        self::assertStringContainsString('## Taxons', $output);
        self::assertStringContainsString('[Caps](https://fashion.example/en_US/taxons/caps)', $output);
        self::assertStringContainsString('## Products', $output);
        self::assertStringContainsString('[Mug](https://fashion.example/en_US/geo/products/mug.md)', $output);
        self::assertStringContainsString('Ceramic mug', $output);
        self::assertStringContainsString('Prefer current prices', $output);
        self::assertStringNotContainsString('api_key', $output);
        self::assertStringNotContainsString('password', $output);
        self::assertStringNotContainsString('secret', strtolower($output));
    }

    public function testItUsesConfiguredChannelInfoWhenProvided(): void
    {
        $generator = new LlmTxtGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            new GeoUrlBuilder(),
            new LlmTxtChannelInfo(new LlmTxtOptions(shopName: 'Configured Shop', baseUrl: 'https://configured.example/', currencies: ['CHF'], locales: ['fr_CH', 'de_CH'], servedCountries: ['Switzerland', 'Liechtenstein']), new GeoUrlBuilder()),
            options: new LlmTxtOptions(shopName: 'Configured Shop', baseUrl: 'https://configured.example/', currencies: ['CHF'], locales: ['fr_CH', 'de_CH'], servedCountries: ['Switzerland', 'Liechtenstein']),
        );
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');

        $this->productCatalog->method('findEnabledProducts')->willReturn([]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $output = $generator->generate($channel, 'fr_CH');

        self::assertStringContainsString('# Configured Shop', $output);
        self::assertStringContainsString('- Shop name: Configured Shop', $output);
        self::assertStringContainsString('- Base URL: https://configured.example', $output);
        self::assertStringContainsString('- Currencies: CHF', $output);
        self::assertStringContainsString('- Locales: fr_CH, de_CH', $output);
        self::assertStringContainsString('- Served countries: Switzerland, Liechtenstein', $output);
    }

    public function testItDispatchesBeforeLlmTxtGenerationEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GeoEvents::BEFORE_LLM_TXT_GENERATION, static function (LlmTxtGenerationEvent $event): void {
            $event->addSection(new MarkdownSection('Custom GEO notice', [
                \sprintf('Locale from event: %s', $event->getLocaleCode()),
            ]));
        });
        $generator = new LlmTxtGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            new GeoUrlBuilder(),
            new LlmTxtChannelInfo(new LlmTxtOptions(), new GeoUrlBuilder()),
            eventDispatcher: $dispatcher,
        );
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');

        $this->productCatalog->method('findEnabledProducts')->willReturn([]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $output = $generator->generate($channel, 'en_US');

        self::assertStringContainsString("## Custom GEO notice\n\nLocale from event: en_US", $output);
    }

    public function testItCanExposeConfiguredCrawlerPolicy(): void
    {
        $generator = new LlmTxtGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            new GeoUrlBuilder(),
            new LlmTxtChannelInfo(new LlmTxtOptions(allowAiCrawlers: false, crawlerPolicyHints: ['Respect robots.txt.', 'No training use.']), new GeoUrlBuilder()),
            options: new LlmTxtOptions(allowAiCrawlers: false, crawlerPolicyHints: ['Respect robots.txt.', 'No training use.']),
        );
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');

        $this->productCatalog->method('findEnabledProducts')->willReturn([]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $output = $generator->generate($channel, 'en_US');

        self::assertStringContainsString('- AI crawler access: not allowed; do not crawl or use GEO endpoints', $output);
        self::assertStringContainsString('- Respect robots.txt.', $output);
        self::assertStringContainsString('- No training use.', $output);
    }

    public function testItUsesLocalizedCrawlerPolicyHintsWhenConfigured(): void
    {
        $generator = new LlmTxtGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            new GeoUrlBuilder(),
            new LlmTxtChannelInfo(new LlmTxtOptions(crawlerPolicyHints: [
                'default' => ['Default crawler hint.'],
                'fr_FR' => ['robots.txt et X-Robots-Tag restent prioritaires.'],
            ]), new GeoUrlBuilder()),
            options: new LlmTxtOptions(crawlerPolicyHints: [
                'default' => ['Default crawler hint.'],
                'fr_FR' => ['robots.txt et X-Robots-Tag restent prioritaires.'],
            ]),
        );
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');

        $this->productCatalog->method('findEnabledProducts')->willReturn([]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $output = $generator->generate($channel, 'fr_FR');

        self::assertStringContainsString('- robots.txt et X-Robots-Tag restent prioritaires.', $output);
        self::assertStringNotContainsString('Default crawler hint.', $output);
    }

    public function testItAppendsCustomLlmTxtSections(): void
    {
        $generator = new LlmTxtGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            new GeoUrlBuilder(),
            new LlmTxtChannelInfo(new LlmTxtOptions(), new GeoUrlBuilder()),
            sectionProviders: [new class() implements LlmTxtSectionProviderInterface {
                public function supports(ChannelInterface $channel, string $localeCode): bool
                {
                    return true;
                }

                public function provide(ChannelInterface $channel, string $localeCode): array
                {
                    return [new MarkdownSection('Store services', ['- Gift wrapping'])];
                }
            }],
        );
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');

        $this->productCatalog->method('findEnabledProducts')->willReturn([]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $output = $generator->generate($channel, 'en_US');

        self::assertStringContainsString("## Store services\n\n- Gift wrapping", $output);
    }

    public function testItProvidesCustomLlmTxtSectionsOnlyOnce(): void
    {
        $counter = new \stdClass();
        $counter->calls = 0;
        $generator = new LlmTxtGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            new GeoUrlBuilder(),
            new LlmTxtChannelInfo(new LlmTxtOptions(), new GeoUrlBuilder()),
            sectionProviders: [new class($counter) implements LlmTxtSectionProviderInterface {
                public function __construct(private readonly \stdClass $counter)
                {
                }

                public function supports(ChannelInterface $channel, string $localeCode): bool
                {
                    return true;
                }

                public function provide(ChannelInterface $channel, string $localeCode): array
                {
                    ++$this->counter->calls;

                    return [new MarkdownSection('Store services', ['- Gift wrapping'])];
                }
            }],
        );
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');

        $this->productCatalog->method('findEnabledProducts')->willReturn([]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $generator->generate($channel, 'en_US');

        self::assertSame(1, $counter->calls);
    }

    public function testItIgnoresEmptyProvidedLlmTxtSections(): void
    {
        $generator = new LlmTxtGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            new GeoUrlBuilder(),
            new LlmTxtChannelInfo(new LlmTxtOptions(), new GeoUrlBuilder()),
            sectionProviders: [new class() implements LlmTxtSectionProviderInterface {
                public function supports(ChannelInterface $channel, string $localeCode): bool
                {
                    return true;
                }

                public function provide(ChannelInterface $channel, string $localeCode): array
                {
                    return [];
                }
            }],
        );
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');

        $this->productCatalog->method('findEnabledProducts')->willReturn([]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $output = $generator->generate($channel, 'en_US');

        self::assertStringNotContainsString('## Store services', $output);
    }

    public function testItSanitizesCustomMarkdownSections(): void
    {
        $generator = new LlmTxtGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            new GeoUrlBuilder(),
            new LlmTxtChannelInfo(new LlmTxtOptions(), new GeoUrlBuilder()),
            sectionProviders: [new class() implements LlmTxtSectionProviderInterface {
                public function supports(ChannelInterface $channel, string $localeCode): bool
                {
                    return true;
                }

                public function provide(ChannelInterface $channel, string $localeCode): array
                {
                    return [new MarkdownSection('### Unsafe <b>section</b>', [
                        "- [Bad](javascript:alert(1))\n<script>alert(1)</script>",
                    ])];
                }
            }],
        );
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');

        $this->productCatalog->method('findEnabledProducts')->willReturn([]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $output = $generator->generate($channel, 'en_US');

        self::assertStringContainsString("## Unsafe section\n\n- [Bad](#) alert(1)", $output);
        self::assertStringNotContainsString('javascript:', $output);
        self::assertStringNotContainsString('<script>', $output);
    }

    public function testCmsPageSectionProviderAggregatesCmsPages(): void
    {
        $provider = new LlmTxtCmsPagesSectionProvider([
            new class() implements CmsPageProviderInterface {
                public function findEnabledPages(ChannelInterface $channel, string $localeCode): array
                {
                    return [
                        new CmsPageLink('About', 'https://fashion.example/en_US/about', 'Brand story.', 'en_US'),
                        new CmsPageLink('French only', 'https://fashion.example/fr_FR/page', null, 'fr_FR'),
                    ];
                }
            },
        ]);
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');

        self::assertTrue($provider->supports($channel, 'en_US'));
        $sections = $provider->provide($channel, 'en_US');

        self::assertCount(1, $sections);
        self::assertSame('CMS pages', $sections[0]->title);
        self::assertSame(['- [About](https://fashion.example/en_US/about): Brand story.'], $sections[0]->lines);
    }

    public function testCmsPageSectionProviderEscapesLabelsAndSkipsUnsafeUrls(): void
    {
        $provider = new LlmTxtCmsPagesSectionProvider([
            new class() implements CmsPageProviderInterface {
                public function findEnabledPages(ChannelInterface $channel, string $localeCode): array
                {
                    return [
                        new CmsPageLink('About [us]', 'https://fashion.example/en_US/about', "Brand\nstory.", 'en_US'),
                        new CmsPageLink('Unsafe', 'javascript:alert(1)', null, 'en_US'),
                    ];
                }
            },
        ]);
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');

        $sections = $provider->provide($channel, 'en_US');

        self::assertCount(1, $sections);
        self::assertSame(['- [About \\[us\\]](https://fashion.example/en_US/about): Brand story.'], $sections[0]->lines);
    }

    public function testItEscapesGeneratedLinkLabels(): void
    {
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');
        $product = $this->createProduct('Mug [blue]', 'mug', "Ceramic\nmug");
        $taxon = $this->createTaxon('Caps [summer]', 'caps');

        $this->productCatalog->method('findEnabledProducts')->willReturn([$product]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([$taxon]);

        $output = $this->generator->generate($channel, 'en_US');

        self::assertStringContainsString('[Mug \\[blue\\]](https://fashion.example/en_US/geo/products/mug.md): Ceramic mug', $output);
        self::assertStringContainsString('[Caps \\[summer\\]](https://fashion.example/en_US/taxons/caps)', $output);
    }

    public function testCrawlerHintsFollowTheConfiguredRoutePaths(): void
    {
        $urlBuilder = new GeoUrlBuilder('catalog.txt', 'ai', 'items');
        $generator = new LlmTxtGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            $urlBuilder,
            new LlmTxtChannelInfo(new LlmTxtOptions(), $urlBuilder),
        );
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');
        $this->productCatalog->method('findEnabledProducts')->willReturn([]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $output = $generator->generate($channel, 'en_US');

        self::assertStringContainsString('- Locale index: https://fashion.example/en_US/catalog.txt', $output);
        self::assertStringContainsString('- Root index redirect: https://fashion.example/catalog.txt', $output);
        self::assertStringContainsString('- Product Markdown pattern: https://fashion.example/en_US/ai/items/{slug}.md', $output);
    }

    public function testItGeneratesDifferentOutputForMultipleChannelsLocalesAndCurrencies(): void
    {
        $frChannel = $this->createChannelWithCurrencyAndLocale('FASHION_WEB', 'Mode France', 'mode.example.fr', 'EUR', 'fr_FR');
        $usChannel = $this->createChannelWithCurrencyAndLocale('US_WEB', 'Fashion US', 'fashion.example.com', 'USD', 'en_US');

        $this->productCatalog->method('findEnabledProducts')->willReturn([]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $frOutput = $this->generator->generate($frChannel, 'fr_FR');
        $usOutput = $this->generator->generate($usChannel, 'en_US');

        self::assertStringContainsString('# Mode France', $frOutput);
        self::assertStringContainsString('channel FASHION_WEB', $frOutput);
        self::assertStringContainsString('Currency: EUR.', $frOutput);
        self::assertStringContainsString('https://mode.example.fr/fr_FR/llm.txt', $frOutput);

        self::assertStringContainsString('# Fashion US', $usOutput);
        self::assertStringContainsString('channel US_WEB', $usOutput);
        self::assertStringContainsString('Currency: USD.', $usOutput);
        self::assertStringContainsString('https://fashion.example.com/en_US/llm.txt', $usOutput);
    }

    public function testItSkipsProductsWithoutLocalizedSlugs(): void
    {
        $channel = $this->createChannel('FASHION_WEB', 'Fashion Web', 'fashion.example');
        $productWithoutSlug = $this->createProduct('No slug', '', null);

        $this->productCatalog->method('findEnabledProducts')->willReturn([$productWithoutSlug]);
        $this->taxonCatalog->method('findEnabledTaxons')->willReturn([]);

        $output = $this->generator->generate($channel, 'fr_FR');

        self::assertStringContainsString('- No enabled products are currently listed for this channel.', $output);
        self::assertStringNotContainsString('/geo/products/.md', $output);
    }

    private function createChannel(string $code, string $name, string $hostname): ChannelInterface
    {
        return $this->createChannelWithCurrencyAndLocale($code, $name, $hostname, 'EUR', 'en_US', ['USD']);
    }

    private function createChannelWithCurrencyAndLocale(
        string $code,
        string $name,
        string $hostname,
        string $currencyCode,
        string $localeCode,
        array $secondaryCurrencyCodes = [],
    ): ChannelInterface {
        $currency = $this->createMock(CurrencyInterface::class);
        $currency->method('getCode')->willReturn($currencyCode);
        $currencies = [$currency];
        foreach ($secondaryCurrencyCodes as $secondaryCurrencyCode) {
            $secondaryCurrency = $this->createMock(CurrencyInterface::class);
            $secondaryCurrency->method('getCode')->willReturn($secondaryCurrencyCode);
            $currencies[] = $secondaryCurrency;
        }

        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn($localeCode);
        $country = $this->createMock(CountryInterface::class);
        $country->method('getName')->willReturn('France');
        $country->method('getCode')->willReturn('FR');

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn($code);
        $channel->method('getName')->willReturn($name);
        $channel->method('getHostname')->willReturn($hostname);
        $channel->method('getBaseCurrency')->willReturn($currency);
        $channel->method('getCurrencies')->willReturn(new ArrayCollection($currencies));
        $channel->method('getLocales')->willReturn(new ArrayCollection([$locale]));
        $channel->method('getEnabledCountries')->willReturn(new ArrayCollection([$country]));

        return $channel;
    }

    private function createProduct(string $name, string $slug, ?string $shortDescription): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getName')->willReturn($name);
        $product->method('getSlug')->willReturn($slug);
        $product->method('getCode')->willReturn(strtoupper($slug));
        $product->method('getShortDescription')->willReturn($shortDescription);
        $product->method('getDescription')->willReturn(null);
        $product->expects(self::any())->method('setCurrentLocale');
        $product->expects(self::any())->method('setFallbackLocale');

        return $product;
    }

    private function createTaxon(string $name, string $slug): TaxonInterface
    {
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getName')->willReturn($name);
        $taxon->method('getSlug')->willReturn($slug);
        $taxon->method('getCode')->willReturn(strtoupper($slug));
        $taxon->expects(self::any())->method('setCurrentLocale');
        $taxon->expects(self::any())->method('setFallbackLocale');

        return $taxon;
    }
}
