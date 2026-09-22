<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Url;

use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;

final class GeoUrlBuilderTest extends TestCase
{
    public function testItBuildsAbsoluteHttpsUrls(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getHostname')->willReturn('shop.example');

        $builder = new GeoUrlBuilder();

        self::assertSame(
            'https://shop.example/en_US/llm.txt',
            $builder->absolute($channel, $builder->llmTxtPath('en_US')),
        );
        self::assertSame(
            'https://shop.example/en_US/geo/products/mug.md',
            $builder->absolute($channel, $builder->productMarkdownPath('en_US', 'mug')),
        );
        self::assertSame(
            'https://shop.example/en_US/products/mug',
            $builder->absolute($channel, $builder->productCanonicalPath('en_US', 'mug')),
        );
        self::assertSame(
            'https://shop.example/en_US/taxons/caps',
            $builder->absolute($channel, $builder->taxonCanonicalPath('en_US', 'caps')),
        );
    }

    public function testItFallsBackToRelativePathWithoutHostname(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getHostname')->willReturn(null);

        $builder = new GeoUrlBuilder();

        self::assertSame(
            '/fr_FR/geo/products/mug.md',
            $builder->absolute($channel, $builder->productMarkdownPath('fr_FR', 'mug')),
        );
    }

    public function testItFallsBackToRelativePathWithUnsafeHostname(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getHostname')->willReturn("shop.example/\nmalicious");

        $builder = new GeoUrlBuilder();

        self::assertSame(
            '/fr_FR/geo/products/mug.md',
            $builder->absolute($channel, $builder->productMarkdownPath('fr_FR', 'mug')),
        );
    }
}
