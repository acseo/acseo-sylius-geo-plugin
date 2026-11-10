<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\TaxonMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Exception\TaxonNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\Taxon\SubcategoriesSection;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownDocument;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSectionRenderer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Model\TaxonMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Provider\TaxonMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class TaxonMarkdownGenerator implements TaxonMarkdownGeneratorInterface
{
    /**
     * @param iterable<TaxonMarkdownSectionProviderInterface> $sectionProviders
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        private TaxonMarkdownOptions $options,
        private TaxonGeoVisibilityCheckerInterface $visibilityChecker,
        private ResourceLocalizer $localizer,
        private GeoUrlBuilderInterface $urlBuilder,
        private SubcategoriesSection $subcategories,
        private MarkdownSectionRenderer $renderer,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
        private EventDispatcherInterface $eventDispatcher,
        private iterable $sectionProviders,
    ) {
    }

    #[\Override]
    public function generate(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): string
    {
        if (!$this->visibilityChecker->isVisible($taxon, $channel)) {
            throw new TaxonNotAvailableForGeoException();
        }

        $slug = $this->localizer->taxonSlug($taxon, $localeCode) ?? throw new TaxonNotAvailableForGeoException();

        $event = new TaxonMarkdownGenerationEvent($taxon, $channel, $localeCode);
        $this->eventDispatcher->dispatch($event, GeoEvents::BEFORE_TAXON_MARKDOWN_GENERATION);

        return $this->document($taxon, $channel, $localeCode, $slug, $event)->toString();
    }

    private function document(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode, string $slug, TaxonMarkdownGenerationEvent $event): MarkdownDocument
    {
        $document = (new MarkdownDocument($this->sanitizer))->title($this->localizer->taxonName($taxon, $localeCode));
        $description = $this->options->includeDescription ? $this->sanitizer->plainText($taxon->getDescription()) : null;
        if (null !== $description) {
            $document->paragraph($description);
        }

        $canonicalUrl = $this->urlBuilder->absolute($channel, $this->urlBuilder->taxonCanonicalPath($localeCode, $slug));

        return $document
            ->append($this->renderer->renderAll(array_filter([$this->subcategories->build($taxon, $channel, $localeCode)])))
            ->append($this->renderer->renderAll($event->getSections()))
            ->append($this->renderer->renderProvided(
                $this->sectionProviders,
                static fn (TaxonMarkdownSectionProviderInterface $provider): bool => $provider->supports($taxon, $channel, $localeCode),
                static fn (TaxonMarkdownSectionProviderInterface $provider): array => $provider->provide($taxon, $channel, $localeCode),
            ))
            ->append($this->renderer->render(new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.taxon.canonical_url'), [$canonicalUrl])));
    }
}
