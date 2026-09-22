<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin;

use ACSEO\SyliusGeoPlugin\DependencyInjection\ACSEOSyliusGeoExtension;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ACSEOSyliusGeoPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ExtensionInterface
    {
        return new ACSEOSyliusGeoExtension();
    }
}
