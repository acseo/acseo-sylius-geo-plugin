<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Event;

use ACSEO\SyliusGeoPlugin\Event\GeoResponseCreationEvent;
use PHPUnit\Framework\TestCase;

final class GeoResponseCreationEventTest extends TestCase
{
    public function testItExposesAndMutatesResponseData(): void
    {
        $event = new GeoResponseCreationEvent('body', 'text/plain', ['X-Initial' => '1']);

        self::assertSame('body', $event->getBody());
        self::assertSame('text/plain', $event->getContentType());
        self::assertSame(['X-Initial' => '1'], $event->getHeaders());

        $event->setBody('updated');
        $event->setContentType('text/markdown');
        $event->setHeader('X-Geo', 'yes');

        self::assertSame('updated', $event->getBody());
        self::assertSame('text/markdown', $event->getContentType());
        self::assertSame(['X-Initial' => '1', 'X-Geo' => 'yes'], $event->getHeaders());
    }
}
