<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class ACSEOSyliusGeoExtension extends AbstractResourceExtension
{
    /** @param array<array<mixed>> $configs */
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array<string, array<string, array<mixed>|bool|float|int|string|\UnitEnum|null>> $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach ($config as $section => $values) {
            foreach ($values as $name => $value) {
                $container->setParameter(\sprintf('%s.%s.%s', Configuration::ROOT, $section, $name), $value);
            }
        }

        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config')))->load('services.php');
    }

    #[\Override]
    public function getAlias(): string
    {
        return Configuration::ROOT;
    }
}
