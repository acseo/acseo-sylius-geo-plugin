<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\DependencyInjection;

use ACSEO\SyliusGeoPlugin\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testItProvidesDefaultsWhenNothingIsConfigured(): void
    {
        $config = $this->process([]);

        self::assertSame(3600, $config['http_cache']['max_age']);
        self::assertSame(50, $config['llm_txt']['product_limit']);
        self::assertSame(20, $config['llm_txt']['taxon_limit']);
        self::assertSame(2000, $config['llm_txt']['product_scan_limit']);
        self::assertSame('detailed', $config['product_markdown']['stock_strategy']);
        self::assertSame('calculated', $config['product_markdown']['price_strategy']);
        self::assertTrue($config['endpoints']['llm_txt']);
        self::assertTrue($config['endpoints']['api']);
        self::assertTrue($config['crawler_policy']['allow_ai_crawlers']);
        self::assertSame([], $config['product_exclusion']['taxon_codes']);
    }

    public function testItProvidesDefaultCrawlerHints(): void
    {
        $hints = $this->process([])['crawler_policy']['hints'];

        self::assertArrayHasKey('default', $hints);
        self::assertCount(3, $hints['default']);
    }

    public function testItReplacesDefaultCrawlerHintsWithConfiguredOnes(): void
    {
        $hints = $this->process(['crawler_policy' => ['hints' => ['fr_FR' => ['Un conseil.']]]])['crawler_policy']['hints'];

        self::assertSame(['fr_FR' => ['Un conseil.']], $hints);
    }

    public function testItWrapsAListOfHintsUnderTheDefaultLocale(): void
    {
        $hints = $this->process(['crawler_policy' => ['hints' => ['Only hint.']]])['crawler_policy']['hints'];

        self::assertSame(['default' => ['Only hint.']], $hints);
    }

    public function testItAllowsEndpointsToBeDisabled(): void
    {
        $endpoints = $this->process(['endpoints' => ['sitemap' => false, 'api' => false]])['endpoints'];

        self::assertFalse($endpoints['sitemap']);
        self::assertFalse($endpoints['api']);
        self::assertTrue($endpoints['llm_txt']);
    }

    public function testItRejectsAnUnknownStockStrategy(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['product_markdown' => ['stock_strategy' => 'unknown']]);
    }

    public function testItRejectsAnUnknownPriceStrategy(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['product_markdown' => ['price_strategy' => 'unknown']]);
    }

    public function testItRejectsANegativeLimit(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['llm_txt' => ['product_limit' => -1]]);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
