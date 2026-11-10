<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGenerator;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGeneratorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->load('ACSEO\\SyliusGeoPlugin\\Generator\\Taxon\\', '../../../src/Generator/Taxon');

    $services->set(TaxonMarkdownGenerator::class)
        ->arg('$sectionProviders', tagged_iterator('acseo_sylius_geo_plugin.taxon_markdown_section_provider'));
    $services->alias(TaxonMarkdownGeneratorInterface::class, TaxonMarkdownGenerator::class);
};
