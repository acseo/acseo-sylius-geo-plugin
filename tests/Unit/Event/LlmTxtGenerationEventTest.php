<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Event;

use ACSEO\SyliusGeoPlugin\Event\LlmTxtGenerationEvent;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;

final class LlmTxtGenerationEventTest extends TestCase
{
    public function testItExposesContextAndAddedSections(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $section = new MarkdownSection('Editorial', ['Line']);
        $event = new LlmTxtGenerationEvent($channel, 'en_US');

        $event->addSection($section);

        self::assertSame($channel, $event->getChannel());
        self::assertSame('en_US', $event->getLocaleCode());
        self::assertSame([$section], $event->getSections());
    }
}
