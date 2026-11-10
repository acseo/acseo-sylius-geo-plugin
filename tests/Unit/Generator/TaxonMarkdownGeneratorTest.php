<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityChecker;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\TaxonMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Exception\TaxonNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\Taxon\SubcategoriesSection;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGenerator;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSectionRenderer;
use ACSEO\SyliusGeoPlugin\Model\ExclusionRules;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Model\TaxonMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Provider\TaxonMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Taxon\TaxonAncestry;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;
use Tests\ACSEO\SyliusGeoPlugin\Support\TranslatorFactory;

final class TaxonMarkdownGeneratorTest extends TestCase
{
    #[TestDox('A taxon outside the channel menu is not exported')]
    public function test_hidden_taxon_is_not_exported(): void
    {
        $this->expectException(TaxonNotAvailableForGeoException::class);

        $this->generate(CatalogFactory::taxon('outlet'), CatalogFactory::channel());
    }

    #[TestDox('A taxon without a slug in the locale is not exported')]
    public function test_taxon_without_slug_is_not_exported(): void
    {
        [$channel, $menu] = $this->channelWithMenu();
        $taxon = CatalogFactory::taxon('mugs', $menu);
        $taxon->setSlug(null);

        $this->expectException(TaxonNotAvailableForGeoException::class);

        $this->generate($taxon, $channel);
    }

    #[TestDox('The page lists the description, the visible subcategories, the extension sections and the canonical URL')]
    public function test_document_structure(): void
    {
        [$channel, $menu] = $this->channelWithMenu();
        $mugs = CatalogFactory::taxon('mugs', $menu);
        $mugs->setDescription('All <i>mugs</i>');
        $mugs->addChild(CatalogFactory::taxon('travel'));
        $mugs->addChild(CatalogFactory::taxon('hidden', null, false));

        $content = $this->generate($mugs, $channel);

        self::assertSame(['# Mugs', '## Subcategories', '## From event', '## From provider', '## Canonical URL'], $this->headings($content));
        self::assertStringContainsString("All mugs\n", $content);
        self::assertStringContainsString("- [Travel](https://shop.example.com/en_US/taxons/travel)\n", $content);
        self::assertStringNotContainsString('Hidden', $content);
        self::assertStringContainsString("https://shop.example.com/en_US/taxons/mugs\n", $content);
    }

    #[TestDox('The description and the subcategories can be switched off')]
    public function test_sections_can_be_switched_off(): void
    {
        [$channel, $menu] = $this->channelWithMenu();
        $mugs = CatalogFactory::taxon('mugs', $menu);
        $mugs->setDescription('All mugs');
        $mugs->addChild(CatalogFactory::taxon('travel'));

        $content = $this->generate($mugs, $channel, new TaxonMarkdownOptions(false, false));

        self::assertNotContains('## Subcategories', $this->headings($content));
        self::assertStringNotContainsString('All mugs', $content);
    }

    /** @return array{ChannelInterface, TaxonInterface} */
    private function channelWithMenu(): array
    {
        $channel = CatalogFactory::channel();
        $menu = CatalogFactory::taxon('category');
        $channel->setMenuTaxon($menu);

        return [$channel, $menu];
    }

    /** @return list<string> */
    private function headings(string $content): array
    {
        return array_values(array_filter(explode("\n", $content), static fn (string $line): bool => str_starts_with($line, '#')));
    }

    private function generate(TaxonInterface $taxon, ChannelInterface $channel, ?TaxonMarkdownOptions $options = null): string
    {
        $options ??= new TaxonMarkdownOptions();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GeoEvents::BEFORE_TAXON_MARKDOWN_GENERATION, static function (TaxonMarkdownGenerationEvent $event): void {
            $event->addSection(new MarkdownSection('From event', ['event line']));
        });
        $provider = new class() implements TaxonMarkdownSectionProviderInterface {
            public function supports(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): bool
            {
                return true;
            }

            public function provide(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): array
            {
                return [new MarkdownSection('From provider', ['provided'])];
            }
        };

        $sanitizer = new MarkdownSanitizer();
        $texts = TranslatorFactory::crawlerTexts();
        $localizer = new ResourceLocalizer();
        $urls = new GeoUrlBuilder();
        $checker = new TaxonGeoVisibilityChecker(new ExclusionRules(), new TaxonAncestry());

        return (new TaxonMarkdownGenerator(
            $options,
            $checker,
            $localizer,
            $urls,
            new SubcategoriesSection($options, $checker, $urls, $localizer, $sanitizer, $texts),
            new MarkdownSectionRenderer($sanitizer),
            $sanitizer,
            $texts,
            $dispatcher,
            [$provider],
        ))->generate($taxon, $channel, CatalogFactory::LOCALE);
    }
}
