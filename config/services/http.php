<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Http\ProductMarkdownResponder;
use ACSEO\SyliusGeoPlugin\Http\TaxonMarkdownResponder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->load('ACSEO\\SyliusGeoPlugin\\Http\\', '../../src/Http')
        ->exclude(['../../src/Http/Endpoint.php', '../../src/Http/EndpointSettings.php', '../../src/Http/Negotiation.php']);

    $services->set(GeoResponseFactory::class)->arg('$httpCacheMaxAge', param('acseo_sylius_geo_plugin.http_cache.max_age'));
    $services->set(ProductMarkdownResponder::class)->arg('$productRepository', service('sylius.repository.product'));
    $services->set(TaxonMarkdownResponder::class)->arg('$taxonRepository', service('sylius.repository.taxon'));
};
