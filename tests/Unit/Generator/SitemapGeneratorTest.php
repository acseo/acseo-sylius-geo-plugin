<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Generator\SitemapGenerator;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;

final class SitemapGeneratorTest extends TestCase
{
    #[TestDox('The sitemap lists the Markdown URL of each product and the canonical URL of each taxon, without duplicates')]
    public function test_it_lists_products_and_taxons(): void
    {
        $channel = CatalogFactory::channel();
        $product = CatalogFactory::product('mug', $channel);
        $withoutSlug = CatalogFactory::product('ghost', $channel);
        $withoutSlug->setSlug(null);
        $taxon = CatalogFactory::taxon('caps', null, true, 'caps/simple');

        $generator = new SitemapGenerator(
            new readonly class([$product, $product, $withoutSlug]) implements ChannelProductCatalogInterface {
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
            },
            new readonly class([$taxon]) implements ChannelTaxonCatalogInterface {
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
            },
            new GeoUrlBuilder(),
            new ResourceLocalizer(),
        );

        $locations = [];
        foreach ((new \SimpleXMLElement($generator->generate($channel, 'en_US')))->url as $url) {
            $locations[] = (string) $url->loc;
        }

        self::assertSame(
            ['https://shop.example.com/en_US/geo/products/mug.md', 'https://shop.example.com/en_US/taxons/caps/simple'],
            $locations,
        );
    }
}
