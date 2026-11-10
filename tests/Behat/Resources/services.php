<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Tests\ACSEO\SyliusGeoPlugin\Behat\Context\Ui\Shop\GeoCatalogSetupContext;
use Tests\ACSEO\SyliusGeoPlugin\Behat\Context\Ui\Shop\GeoRequestContext;
use Tests\ACSEO\SyliusGeoPlugin\Behat\Context\Ui\Shop\GeoResponseContext;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()->public();

    $services->set(GeoRequestContext::class)
        ->args([
            service('behat.mink.default_session'),
            service('sylius.behat.shared_storage'),
            service('sylius.repository.channel'),
        ])
        ->tag('fob.context_service');

    $services->set(GeoResponseContext::class)
        ->args([service('behat.mink.default_session')])
        ->tag('fob.context_service');

    $services->set(GeoCatalogSetupContext::class)
        ->args([
            service('sylius.behat.shared_storage'),
            service('sylius.factory.product_attribute'),
            service('sylius.factory.product_attribute_value'),
            service('doctrine.orm.entity_manager'),
        ])
        ->tag('fob.context_service');
};
