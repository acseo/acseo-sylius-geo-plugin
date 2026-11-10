<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Integration\DependencyInjection;

use ACSEO\SyliusGeoPlugin\ACSEOSyliusGeoPlugin;
use ACSEO\SyliusGeoPlugin\DependencyInjection\ACSEOSyliusGeoExtension;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Generator\SitemapGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Model\ExclusionRules;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Model\StockStrategy;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ACSEOSyliusGeoExtensionTest extends KernelTestCase
{
    #[TestDox('The plugin exposes the acseo_sylius_geo_plugin configuration alias')]
    public function test_plugin_uses_the_configuration_alias(): void
    {
        $extension = (new ACSEOSyliusGeoPlugin())->getContainerExtension();

        self::assertInstanceOf(ACSEOSyliusGeoExtension::class, $extension);
        self::assertSame('acseo_sylius_geo_plugin', $extension->getAlias());
    }

    #[TestDox('The processed configuration is exposed as container parameters')]
    public function test_configuration_is_exposed_as_parameters(): void
    {
        $container = new ContainerBuilder();
        (new ACSEOSyliusGeoExtension())->load([['catalog' => ['product_limit' => 7], 'product_markdown' => ['stock_strategy' => 'hidden']]], $container);

        self::assertSame(7, $container->getParameter('acseo_sylius_geo_plugin.catalog.product_limit'));
        self::assertSame(StockStrategy::Hidden, $container->getParameter('acseo_sylius_geo_plugin.product_markdown.stock_strategy'));
        self::assertSame(3600, $container->getParameter('acseo_sylius_geo_plugin.http_cache.max_age'));
    }

    #[TestDox('The Test Application container wires the generators, the options and the exclusion rules')]
    public function test_container_wires_the_plugin(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(LlmTxtGeneratorInterface::class, $container->get(LlmTxtGeneratorInterface::class));
        self::assertInstanceOf(SitemapGeneratorInterface::class, $container->get(SitemapGeneratorInterface::class));
        self::assertInstanceOf(ProductMarkdownGeneratorInterface::class, $container->get(ProductMarkdownGeneratorInterface::class));
        self::assertInstanceOf(TaxonMarkdownGeneratorInterface::class, $container->get(TaxonMarkdownGeneratorInterface::class));
        self::assertEquals(new ProductMarkdownOptions(), $container->get(ProductMarkdownOptions::class));
        self::assertEquals(new ExclusionRules(['geo_private'], ['geo_hidden'], ['geo_visibility' => ['private']]), $container->get(ExclusionRules::class));
    }
}
