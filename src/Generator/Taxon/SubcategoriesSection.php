<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Taxon;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Model\TaxonMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final readonly class SubcategoriesSection
{
    public function __construct(
        private TaxonMarkdownOptions $options,
        private TaxonGeoVisibilityCheckerInterface $visibilityChecker,
        private GeoUrlBuilderInterface $urlBuilder,
        private ResourceLocalizer $localizer,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    public function build(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): ?MarkdownSection
    {
        if (!$this->options->includeChildren) {
            return null;
        }

        $lines = [];
        foreach ($taxon->getChildren() as $child) {
            $slug = $this->visibilityChecker->isVisible($child, $channel) ? $this->localizer->taxonSlug($child, $localeCode) : null;
            if (null !== $slug) {
                $lines[] = \sprintf(
                    '- [%s](%s)',
                    $this->sanitizer->linkText($this->localizer->taxonName($child, $localeCode)),
                    $this->urlBuilder->absolute($channel, $this->urlBuilder->taxonCanonicalPath($localeCode, $slug)),
                );
            }
        }

        return new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.taxon.subcategories'), $lines);
    }
}
