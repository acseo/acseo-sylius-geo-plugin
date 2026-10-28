<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\DependencyInjection;

use ACSEO\SyliusGeoPlugin\DependencyInjection\ACSEOSyliusGeoExtension;
use ACSEO\SyliusGeoPlugin\Provider\CmsPageProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\LlmTxtSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\ProductMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\TaxonMarkdownSectionProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ACSEOSyliusGeoExtensionTest extends TestCase
{
    public function testItUsesThePluginAlias(): void
    {
        self::assertSame('acseo_sylius_geo_plugin', (new ACSEOSyliusGeoExtension())->getAlias());
    }

    public function testItTagsAutoconfiguredProvidersOfTheHostApplication(): void
    {
        $instanceof = $this->load([])->getAutoconfiguredInstanceof();

        foreach ([
            ProductMarkdownSectionProviderInterface::class => 'acseo_sylius_geo_plugin.product_markdown_section_provider',
            TaxonMarkdownSectionProviderInterface::class => 'acseo_sylius_geo_plugin.taxon_markdown_section_provider',
            LlmTxtSectionProviderInterface::class => 'acseo_sylius_geo_plugin.llm_txt_section_provider',
            CmsPageProviderInterface::class => 'acseo_sylius_geo_plugin.cms_page_provider',
        ] as $interface => $tag) {
            self::assertArrayHasKey($interface, $instanceof);
            self::assertTrue($instanceof[$interface]->hasTag($tag));
        }
    }

    public function testItExposesTheDefaultsAsParameters(): void
    {
        $container = $this->load([]);

        self::assertSame(3600, $container->getParameter('acseo_sylius_geo_plugin.http_cache.max_age'));
        self::assertSame(50, $container->getParameter('acseo_sylius_geo_plugin.catalog.product_limit'));
        self::assertTrue($container->getParameter('acseo_sylius_geo_plugin.endpoints.llm_txt'));
        self::assertArrayHasKey('default', $container->getParameter('acseo_sylius_geo_plugin.crawler_policy.hints'));
    }

    public function testConfiguredValuesOverrideTheDefaults(): void
    {
        $container = $this->load([
            'http_cache' => ['max_age' => 60],
            'catalog' => ['product_limit' => 5],
            'endpoints' => ['sitemap' => false],
            'product_exclusion' => ['taxon_codes' => ['PRIVATE']],
        ]);

        self::assertSame(60, $container->getParameter('acseo_sylius_geo_plugin.http_cache.max_age'));
        self::assertSame(5, $container->getParameter('acseo_sylius_geo_plugin.catalog.product_limit'));
        self::assertSame(20, $container->getParameter('acseo_sylius_geo_plugin.catalog.taxon_limit'));
        self::assertFalse($container->getParameter('acseo_sylius_geo_plugin.endpoints.sitemap'));
        self::assertSame(['PRIVATE'], $container->getParameter('acseo_sylius_geo_plugin.product_exclusion.taxon_codes'));
    }

    /** @param array<string, mixed> $config */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new ACSEOSyliusGeoExtension())->load([$config], $container);

        return $container;
    }
}
