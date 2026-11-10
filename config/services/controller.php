<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Controller\ProductMarkdownController;
use ACSEO\SyliusGeoPlugin\Controller\Api\ProductMarkdownApiController;
use ACSEO\SyliusGeoPlugin\Http\ProductMarkdownResponder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure()->tag('controller.service_arguments');

    $services->load('ACSEO\\SyliusGeoPlugin\\Controller\\', '../../src/Controller');

    $services->set(ProductMarkdownController::class)->arg('$productResponder', service(ProductMarkdownResponder::class));
    $services->set(ProductMarkdownApiController::class)->arg('$productResponder', service(ProductMarkdownResponder::class));
};
