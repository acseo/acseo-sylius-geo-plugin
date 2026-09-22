<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class ACSEOSyliusGeoExtension extends AbstractResourceExtension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->setParameters($container, 'http_cache', $config['http_cache']);
        $this->setParameters($container, 'llm_txt', $config['llm_txt']);
        $this->setParameters($container, 'product_markdown', $config['product_markdown']);
        $this->setParameters($container, 'taxon_markdown', $config['taxon_markdown']);
        $this->setParameters($container, 'product_exclusion', $config['product_exclusion']);
        $this->setParameters($container, 'channel_info', $config['channel_info']);
        $this->setParameters($container, 'endpoints', $config['endpoints']);
        $this->setParameters($container, 'crawler_policy', $config['crawler_policy']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }

    public function getAlias(): string
    {
        return 'acseo_sylius_geo_plugin';
    }

    private function setParameters(ContainerBuilder $container, string $section, mixed $values): void
    {
        if (!\is_array($values)) {
            return;
        }

        foreach ($values as $name => $value) {
            if (!\is_string($name) || !$this->isContainerParameterValue($value)) {
                continue;
            }

            $container->setParameter('acseo_sylius_geo_plugin.' . $section . '.' . $name, $value);
        }
    }

    /** @phpstan-assert-if-true array|bool|float|int|string|\UnitEnum|null $value */
    private function isContainerParameterValue(mixed $value): bool
    {
        return null === $value || \is_array($value) || \is_bool($value) || \is_float($value) || \is_int($value) || \is_string($value) || $value instanceof \UnitEnum;
    }
}
