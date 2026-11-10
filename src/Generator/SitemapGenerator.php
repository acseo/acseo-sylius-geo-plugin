<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class SitemapGenerator implements SitemapGeneratorInterface
{
    private const string NAMESPACE_URI = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public function __construct(
        private ChannelProductCatalogInterface $productCatalog,
        private ChannelTaxonCatalogInterface $taxonCatalog,
        private GeoUrlBuilderInterface $urlBuilder,
        private ResourceLocalizer $localizer,
    ) {
    }

    #[\Override]
    public function generate(ChannelInterface $channel, string $localeCode): string
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', self::NAMESPACE_URI);

        foreach ([...$this->productUrls($channel, $localeCode), ...$this->taxonUrls($channel, $localeCode)] as $url) {
            $writer->startElement('url');
            $writer->writeElement('loc', $url);
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /** @return list<string> */
    private function productUrls(ChannelInterface $channel, string $localeCode): array
    {
        $urls = [];
        foreach ($this->productCatalog->findEnabledProducts($channel, $localeCode) as $product) {
            $slug = $this->localizer->productSlug($product, $localeCode);
            if (null !== $slug) {
                $urls[] = $this->urlBuilder->absolute($channel, $this->urlBuilder->productMarkdownPath($localeCode, $slug));
            }
        }

        return array_values(array_unique($urls));
    }

    /** @return list<string> */
    private function taxonUrls(ChannelInterface $channel, string $localeCode): array
    {
        $urls = [];
        foreach ($this->taxonCatalog->findEnabledTaxons($channel, $localeCode) as $taxon) {
            $slug = $this->localizer->taxonSlug($taxon, $localeCode);
            if (null !== $slug) {
                $urls[] = $this->urlBuilder->absolute($channel, $this->urlBuilder->taxonCanonicalPath($localeCode, $slug));
            }
        }

        return array_values(array_unique($urls));
    }
}
