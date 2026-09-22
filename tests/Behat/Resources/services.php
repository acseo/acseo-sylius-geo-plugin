<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Tests\ACSEO\SyliusGeoPlugin\Behat\Context\Ui\Shop\GeoHttpContext;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()->public();

    $services->set(GeoHttpContext::class)
        ->public()
        ->args([
            service('behat.mink.default_session'),
            service('sylius.behat.shared_storage'),
            service('sylius.repository.channel'),
            service('sylius.factory.product_attribute'),
            service('sylius.factory.product_attribute_value'),
            service('doctrine.orm.entity_manager'),
        ])
        ->tag('fob.context_service')
    ;
};
