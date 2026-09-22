<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Event;

use ACSEO\SyliusGeoPlugin\Event\TaxonMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final class TaxonMarkdownGenerationEventTest extends TestCase
{
    public function testItExposesContextAndAddedSections(): void
    {
        $taxon = $this->createMock(TaxonInterface::class);
        $channel = $this->createMock(ChannelInterface::class);
        $section = new MarkdownSection('Category', ['Line']);
        $event = new TaxonMarkdownGenerationEvent($taxon, $channel, 'de_DE');

        $event->addSection($section);

        self::assertSame($taxon, $event->getTaxon());
        self::assertSame($channel, $event->getChannel());
        self::assertSame('de_DE', $event->getLocaleCode());
        self::assertSame([$section], $event->getSections());
    }
}
