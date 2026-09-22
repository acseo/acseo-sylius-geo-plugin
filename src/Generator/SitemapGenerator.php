<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class SitemapGenerator implements SitemapGeneratorInterface
{
    public function __construct(
        private readonly ChannelProductCatalogInterface $productCatalog,
        private readonly ChannelTaxonCatalogInterface $taxonCatalog,
        private readonly GeoUrlBuilderInterface $urlBuilder,
    ) {
    }

    public function generate(ChannelInterface $channel, string $localeCode): string
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($this->productUrls($channel, $localeCode) as $url) {
            $this->writeUrl($writer, $url);
        }

        foreach ($this->taxonUrls($channel, $localeCode) as $url) {
            $this->writeUrl($writer, $url);
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
            $product->setCurrentLocale($localeCode);
            $product->setFallbackLocale($localeCode);

            $slug = $product->getSlug();
            if (null === $slug || '' === trim($slug)) {
                continue;
            }

            $urls[] = $this->urlBuilder->absolute(
                $channel,
                $this->urlBuilder->productMarkdownPath($localeCode, $slug),
            );
        }

        return array_values(array_unique($urls));
    }

    /** @return list<string> */
    private function taxonUrls(ChannelInterface $channel, string $localeCode): array
    {
        $urls = [];
        foreach ($this->taxonCatalog->findEnabledTaxons($channel, $localeCode) as $taxon) {
            $taxon->setCurrentLocale($localeCode);
            $taxon->setFallbackLocale($localeCode);

            $slug = $taxon->getSlug();
            if (null === $slug || '' === trim($slug)) {
                continue;
            }

            $urls[] = $this->urlBuilder->absolute(
                $channel,
                $this->urlBuilder->taxonCanonicalPath($localeCode, $slug),
            );
        }

        return array_values(array_unique($urls));
    }

    private function writeUrl(\XMLWriter $writer, string $url): void
    {
        $writer->startElement('url');
        $writer->writeElement('loc', $url);
        $writer->endElement();
    }
}
