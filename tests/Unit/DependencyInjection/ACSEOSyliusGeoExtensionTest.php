<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\DependencyInjection;

use ACSEO\SyliusGeoPlugin\DependencyInjection\ACSEOSyliusGeoExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ACSEOSyliusGeoExtensionTest extends TestCase
{
    public function testItUsesThePluginAlias(): void
    {
        self::assertSame('acseo_sylius_geo_plugin', (new ACSEOSyliusGeoExtension())->getAlias());
    }

    public function testItExposesTheDefaultsAsParameters(): void
    {
        $container = $this->load([]);

        self::assertSame(3600, $container->getParameter('acseo_sylius_geo_plugin.http_cache.max_age'));
        self::assertSame(50, $container->getParameter('acseo_sylius_geo_plugin.llm_txt.product_limit'));
        self::assertTrue($container->getParameter('acseo_sylius_geo_plugin.endpoints.llm_txt'));
        self::assertArrayHasKey('default', $container->getParameter('acseo_sylius_geo_plugin.crawler_policy.hints'));
    }

    public function testConfiguredValuesOverrideTheDefaults(): void
    {
        $container = $this->load([
            'http_cache' => ['max_age' => 60],
            'llm_txt' => ['product_limit' => 5],
            'endpoints' => ['sitemap' => false],
            'product_exclusion' => ['taxon_codes' => ['PRIVATE']],
        ]);

        self::assertSame(60, $container->getParameter('acseo_sylius_geo_plugin.http_cache.max_age'));
        self::assertSame(5, $container->getParameter('acseo_sylius_geo_plugin.llm_txt.product_limit'));
        self::assertSame(20, $container->getParameter('acseo_sylius_geo_plugin.llm_txt.taxon_limit'));
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
