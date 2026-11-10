<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalog;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalog;
use ACSEO\SyliusGeoPlugin\Catalog\DoctrineCandidateProductReader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->load('ACSEO\\SyliusGeoPlugin\\Catalog\\', '../../src/Catalog');

    $services->set(DoctrineCandidateProductReader::class)
        ->arg('$productRepository', service('sylius.repository.product'));

    $services->set(ChannelProductCatalog::class)->args([
        '$productLimit' => param('acseo_sylius_geo_plugin.catalog.product_limit'),
        '$productScanLimit' => param('acseo_sylius_geo_plugin.catalog.product_scan_limit'),
    ]);

    $services->set(ChannelTaxonCatalog::class)->args([
        '$taxonRepository' => service('sylius.repository.taxon'),
        '$taxonLimit' => param('acseo_sylius_geo_plugin.catalog.taxon_limit'),
    ]);
};
