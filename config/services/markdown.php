<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->load('ACSEO\\SyliusGeoPlugin\\Markdown\\', '../../src/Markdown')->exclude('../../src/Markdown/MarkdownDocument.php');
    $services->load('ACSEO\\SyliusGeoPlugin\\Translation\\', '../../src/Translation');
};
