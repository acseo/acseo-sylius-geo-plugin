<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\LlmTxt;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class TaxonsSection
{
    public function __construct(
        private ChannelTaxonCatalogInterface $catalog,
        private GeoUrlBuilderInterface $urlBuilder,
        private ResourceLocalizer $localizer,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    public function build(ChannelInterface $channel, string $localeCode): MarkdownSection
    {
        $lines = [];
        foreach ($this->catalog->findEnabledTaxons($channel, $localeCode) as $taxon) {
            $slug = $this->localizer->taxonSlug($taxon, $localeCode);
            if (null !== $slug) {
                $lines[] = \sprintf(
                    '- [%s](%s)',
                    $this->sanitizer->linkText($this->localizer->taxonName($taxon, $localeCode)),
                    $this->urlBuilder->absolute($channel, $this->urlBuilder->taxonCanonicalPath($localeCode, $slug)),
                );
            }
        }

        return new MarkdownSection(
            $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.taxons.title'),
            [] === $lines ? ['- ' . $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.taxons.empty')] : $lines,
        );
    }
}
