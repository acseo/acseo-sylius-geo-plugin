<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Event;

use ACSEO\SyliusGeoPlugin\Event\ProductMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

final class ProductMarkdownGenerationEventTest extends TestCase
{
    public function testItExposesContextAndAddedSections(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $channel = $this->createMock(ChannelInterface::class);
        $section = new MarkdownSection('Details', ['Line']);
        $event = new ProductMarkdownGenerationEvent($product, $channel, 'fr_FR');

        $event->addSection($section);

        self::assertSame($product, $event->getProduct());
        self::assertSame($channel, $event->getChannel());
        self::assertSame('fr_FR', $event->getLocaleCode());
        self::assertSame([$section], $event->getSections());
    }
}
