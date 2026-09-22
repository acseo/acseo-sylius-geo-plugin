<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Generator\SitemapGenerator;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final class SitemapGeneratorTest extends TestCase
{
    private ChannelProductCatalogInterface&MockObject $productCatalog;

    private ChannelTaxonCatalogInterface&MockObject $taxonCatalog;

    private SitemapGenerator $generator;

    protected function setUp(): void
    {
        $this->productCatalog = $this->createMock(ChannelProductCatalogInterface::class);
        $this->taxonCatalog = $this->createMock(ChannelTaxonCatalogInterface::class);

        $this->generator = new SitemapGenerator(
            $this->productCatalog,
            $this->taxonCatalog,
            new GeoUrlBuilder(),
        );
    }

    public function testItGeneratesSitemapWithExportableProductAndTaxonUrls(): void
    {
        $channel = $this->createChannel('shop.example.test');
        $exportableProduct = $this->createProduct('blue-dress');
        $productWithoutSlug = $this->createProduct('');
        $taxon = $this->createTaxon('robes');

        $this->productCatalog->method('findEnabledProducts')->with($channel, 'fr_FR')->willReturn([
            $exportableProduct,
            $productWithoutSlug,
        ]);
        $this->taxonCatalog->method('findEnabledTaxons')->with($channel, 'fr_FR')->willReturn([$taxon]);

        $xml = $this->generator->generate($channel, 'fr_FR');

        self::assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        self::assertStringContainsString('<loc>https://shop.example.test/fr_FR/geo/products/blue-dress.md</loc>', $xml);
        self::assertStringContainsString('<loc>https://shop.example.test/fr_FR/taxons/robes</loc>', $xml);
        self::assertStringNotContainsString('<loc>https://shop.example.test/fr_FR/geo/products/.md</loc>', $xml);
    }

    private function createChannel(string $hostname): ChannelInterface
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getHostname')->willReturn($hostname);

        return $channel;
    }

    private function createProduct(string $slug): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSlug')->willReturn($slug);
        $product->expects(self::any())->method('setCurrentLocale');
        $product->expects(self::any())->method('setFallbackLocale');

        return $product;
    }

    private function createTaxon(string $slug): TaxonInterface
    {
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getSlug')->willReturn($slug);
        $taxon->expects(self::any())->method('setCurrentLocale');
        $taxon->expects(self::any())->method('setFallbackLocale');

        return $taxon;
    }
}
