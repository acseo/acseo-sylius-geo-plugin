<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Generator\SitemapGenerator;
use ACSEO\SyliusGeoPlugin\Generator\SitemapGeneratorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->set(SitemapGenerator::class);
    $services->alias(SitemapGeneratorInterface::class, SitemapGenerator::class);
};
