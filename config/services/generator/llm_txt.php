<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGenerator;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGeneratorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->load('ACSEO\\SyliusGeoPlugin\\Generator\\LlmTxt\\', '../../../src/Generator/LlmTxt');

    $services->set(LlmTxtGenerator::class)
        ->arg('$sectionProviders', tagged_iterator('acseo_sylius_geo_plugin.llm_txt_section_provider'));
    $services->alias(LlmTxtGeneratorInterface::class, LlmTxtGenerator::class);
};
