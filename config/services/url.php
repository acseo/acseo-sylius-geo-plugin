<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->set(GeoUrlBuilder::class)->args([
        '$llmTxtPath' => param('acseo_sylius_geo_plugin.routing.llm_txt_path'),
        '$geoPrefix' => param('acseo_sylius_geo_plugin.routing.geo_prefix'),
        '$geoProductsPrefix' => param('acseo_sylius_geo_plugin.routing.geo_products_prefix'),
    ]);
    $services->alias(GeoUrlBuilderInterface::class, GeoUrlBuilder::class);
};
