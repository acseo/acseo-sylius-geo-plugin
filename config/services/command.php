<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Command\DebugProductCommand;
use ACSEO\SyliusGeoPlugin\Command\DebugTaxonCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->load('ACSEO\\SyliusGeoPlugin\\Command\\', '../../src/Command')
        ->exclude(['../../src/Command/Diagnostic/AuditSnapshot.php', '../../src/Command/Diagnostic/DebugTarget.php']);

    $services->set(DebugProductCommand::class)->arg('$productRepository', service('sylius.repository.product'));
    $services->set(DebugTaxonCommand::class)->arg('$taxonRepository', service('sylius.repository.taxon'));
};
