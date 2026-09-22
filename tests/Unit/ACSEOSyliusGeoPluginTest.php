<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit;

use ACSEO\SyliusGeoPlugin\ACSEOSyliusGeoPlugin;
use ACSEO\SyliusGeoPlugin\DependencyInjection\ACSEOSyliusGeoExtension;
use PHPUnit\Framework\TestCase;

final class ACSEOSyliusGeoPluginTest extends TestCase
{
    public function testItReturnsThePluginPath(): void
    {
        self::assertSame(\dirname(__DIR__, 2), (new ACSEOSyliusGeoPlugin())->getPath());
    }

    public function testItCreatesItsContainerExtension(): void
    {
        self::assertInstanceOf(ACSEOSyliusGeoExtension::class, (new ACSEOSyliusGeoPlugin())->getContainerExtension());
    }
}
