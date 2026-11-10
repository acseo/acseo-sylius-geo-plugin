<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Provider\LlmTxtCmsPagesSectionProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->set(LlmTxtCmsPagesSectionProvider::class)
        ->arg('$cmsPageProviders', tagged_iterator('acseo_sylius_geo_plugin.cms_page_provider'));
};
