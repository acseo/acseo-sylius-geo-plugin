<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\TaxonMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Exception\TaxonNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGenerator;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Provider\TaxonMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class TaxonMarkdownGeneratorTest extends TestCase
{
    private TaxonGeoVisibilityCheckerInterface&MockObject $visibilityChecker;

    private TaxonMarkdownGenerator $generator;

    protected function setUp(): void
    {
        $this->visibilityChecker = $this->createMock(TaxonGeoVisibilityCheckerInterface::class);
        $this->generator = new TaxonMarkdownGenerator(
            $this->visibilityChecker,
            new GeoUrlBuilder(),
        );
    }

    public function testItGeneratesMarkdownWithNameDescriptionChildrenAndCanonicalUrl(): void
    {
        $channel = $this->createChannel();
        $child = $this->createTaxon('Caps', 'caps', null, []);
        $taxon = $this->createTaxon('Accessories', 'accessories', '<p>Shop accessories</p>', [$child]);

        $this->visibilityChecker
            ->method('isVisible')
            ->willReturnCallback(static fn (TaxonInterface $checkedTaxon): bool => $checkedTaxon === $taxon || $checkedTaxon === $child)
        ;

        $output = $this->generator->generate($taxon, $channel, 'en_US');

        self::assertStringContainsString('# Accessories', $output);
        self::assertStringContainsString('Shop accessories', $output);
        self::assertStringContainsString('## Subcategories', $output);
        self::assertStringContainsString('[Caps](https://fashion.example/en_US/taxons/caps)', $output);
        self::assertStringContainsString('## Canonical URL', $output);
        self::assertStringContainsString('https://fashion.example/en_US/taxons/accessories', $output);
        self::assertStringNotContainsString('<p>', $output);
    }

    public function testItThrowsWhenTaxonIsNotVisible(): void
    {
        $taxon = $this->createTaxon('Disabled', 'disabled', null, []);

        $this->visibilityChecker->method('isVisible')->willReturn(false);

        $this->expectException(TaxonNotAvailableForGeoException::class);

        $this->generator->generate($taxon, $this->createChannel(), 'en_US');
    }

    public function testItCanHideOptionalMarkdownSections(): void
    {
        $generator = new TaxonMarkdownGenerator(
            $this->visibilityChecker,
            new GeoUrlBuilder(),
            includeDescription: false,
            includeChildren: false,
        );
        $channel = $this->createChannel();
        $child = $this->createTaxon('Caps', 'caps', null, []);
        $taxon = $this->createTaxon('Accessories', 'accessories', '<p>Shop accessories</p>', [$child]);

        $this->visibilityChecker->method('isVisible')->with($taxon)->willReturn(true);

        $output = $generator->generate($taxon, $channel, 'en_US');

        self::assertStringContainsString('# Accessories', $output);
        self::assertStringContainsString('## Canonical URL', $output);
        self::assertStringNotContainsString('Shop accessories', $output);
        self::assertStringNotContainsString('## Subcategories', $output);
    }

    public function testItAppendsCustomProviderSections(): void
    {
        $generator = new TaxonMarkdownGenerator(
            $this->visibilityChecker,
            new GeoUrlBuilder(),
            sectionProviders: [new class() implements TaxonMarkdownSectionProviderInterface {
                public function supports(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): bool
                {
                    return true;
                }

                public function provide(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): array
                {
                    return [new MarkdownSection('Buying guide', ['Choose by size.'])];
                }
            }],
        );
        $taxon = $this->createTaxon('Accessories', 'accessories', null, []);

        $this->visibilityChecker->method('isVisible')->willReturn(true);

        $output = $generator->generate($taxon, $this->createChannel(), 'en_US');

        self::assertStringContainsString("## Buying guide\n\nChoose by size.", $output);
    }

    public function testItDispatchesBeforeTaxonMarkdownGenerationEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GeoEvents::BEFORE_TAXON_MARKDOWN_GENERATION, static function (TaxonMarkdownGenerationEvent $event): void {
            $event->addSection(new MarkdownSection('Collection note', [
                \sprintf('Locale: %s', $event->getLocaleCode()),
            ]));
        });
        $generator = new TaxonMarkdownGenerator(
            $this->visibilityChecker,
            new GeoUrlBuilder(),
            eventDispatcher: $dispatcher,
        );
        $taxon = $this->createTaxon('Accessories', 'accessories', null, []);

        $this->visibilityChecker->method('isVisible')->willReturn(true);

        $output = $generator->generate($taxon, $this->createChannel(), 'en_US');

        self::assertStringContainsString("## Collection note\n\nLocale: en_US", $output);
    }

    private function createChannel(): ChannelInterface
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getHostname')->willReturn('fashion.example');

        return $channel;
    }

    /** @param list<TaxonInterface> $children */
    private function createTaxon(string $name, string $slug, ?string $description, array $children): TaxonInterface
    {
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getCode')->willReturn(strtoupper($slug));
        $taxon->method('getName')->willReturn($name);
        $taxon->method('getSlug')->willReturn($slug);
        $taxon->method('getDescription')->willReturn($description);
        $taxon->method('getChildren')->willReturn(new ArrayCollection($children));
        $taxon->expects(self::any())->method('setCurrentLocale');
        $taxon->expects(self::any())->method('setFallbackLocale');

        return $taxon;
    }
}
