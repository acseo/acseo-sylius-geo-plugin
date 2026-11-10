<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGenerator;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGeneratorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->load('ACSEO\\SyliusGeoPlugin\\Generator\\Product\\', '../../../src/Generator/Product')
        ->exclude('../../../src/Generator/Product/ProductMarkdownContext.php');

    $services->set(ProductMarkdownGenerator::class)->args([
        '$sectionBuilders' => tagged_iterator('acseo_sylius_geo_plugin.product_markdown_builtin_section'),
        '$sectionProviders' => tagged_iterator('acseo_sylius_geo_plugin.product_markdown_section_provider'),
    ]);
    $services->alias(ProductMarkdownGeneratorInterface::class, ProductMarkdownGenerator::class);
};
