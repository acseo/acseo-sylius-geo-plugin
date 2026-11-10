<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->load('ACSEO\\SyliusGeoPlugin\\Checker\\', '../../src/Checker')
        ->exclude(['../../src/Checker/ExportIssue.php', '../../src/Checker/ExportIssueType.php']);
    $services->load('ACSEO\\SyliusGeoPlugin\\Attribute\\', '../../src/Attribute');
};
