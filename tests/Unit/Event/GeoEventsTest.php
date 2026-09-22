<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Event;

use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use PHPUnit\Framework\TestCase;

final class GeoEventsTest extends TestCase
{
    public function testItDefinesEventNames(): void
    {
        self::assertSame('acseo_sylius_geo_plugin.before_llm_txt_generation', GeoEvents::BEFORE_LLM_TXT_GENERATION);
        self::assertSame('acseo_sylius_geo_plugin.before_product_markdown_generation', GeoEvents::BEFORE_PRODUCT_MARKDOWN_GENERATION);
        self::assertSame('acseo_sylius_geo_plugin.before_taxon_markdown_generation', GeoEvents::BEFORE_TAXON_MARKDOWN_GENERATION);
        self::assertSame('acseo_sylius_geo_plugin.before_response_creation', GeoEvents::BEFORE_RESPONSE_CREATION);
    }

    public function testItCannotBeInstantiated(): void
    {
        $reflectionClass = new \ReflectionClass(GeoEvents::class);
        $constructor = $reflectionClass->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());

        $constructor->setAccessible(true);
        $constructor->invoke($reflectionClass->newInstanceWithoutConstructor());
    }
}
