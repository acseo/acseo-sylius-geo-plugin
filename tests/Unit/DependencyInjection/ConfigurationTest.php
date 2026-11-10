<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\DependencyInjection;

use ACSEO\SyliusGeoPlugin\DependencyInjection\Configuration;
use ACSEO\SyliusGeoPlugin\Model\PriceStrategy;
use ACSEO\SyliusGeoPlugin\Model\StockStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    #[TestDox('An empty configuration gives the documented defaults')]
    public function test_defaults(): void
    {
        $config = $this->process([]);

        self::assertSame(3600, $config['http_cache']['max_age']);
        self::assertSame(['product_limit' => 50, 'taxon_limit' => 20, 'product_scan_limit' => 2000], $config['catalog']);
        self::assertSame(StockStrategy::Detailed, $config['product_markdown']['stock_strategy']);
        self::assertSame(PriceStrategy::Calculated, $config['product_markdown']['price_strategy']);
        self::assertSame([], $config['product_exclusion']['taxon_codes']);
        self::assertFalse($config['channel_info']['include_channel_code']);
        self::assertNull($config['channel_info']['shop_name']);
        self::assertSame(['llm_txt' => true, 'product_markdown' => true, 'taxon_markdown' => true, 'sitemap' => true, 'api' => true], $config['endpoints']);
        self::assertTrue($config['crawler_policy']['allow_ai_crawlers']);
        self::assertCount(3, $config['crawler_policy']['hints']['default']);
    }

    #[TestDox('Strategies are read as enums')]
    public function test_strategies_are_enums(): void
    {
        $config = $this->process(['product_markdown' => ['stock_strategy' => 'availability_only', 'price_strategy' => 'hidden']]);

        self::assertSame(StockStrategy::AvailabilityOnly, $config['product_markdown']['stock_strategy']);
        self::assertSame(PriceStrategy::Hidden, $config['product_markdown']['price_strategy']);
    }

    #[TestDox('A flat list of crawler hints applies to every locale')]
    public function test_flat_hints_become_the_default_hints(): void
    {
        $config = $this->process(['crawler_policy' => ['hints' => ['One', 'Two']]]);

        self::assertSame(['default' => ['One', 'Two']], $config['crawler_policy']['hints']);
    }

    #[TestDox('Crawler hints can be set per locale')]
    public function test_hints_can_be_localized(): void
    {
        $hints = ['default' => ['One'], 'fr_FR' => ['Un']];

        self::assertSame($hints, $this->process(['crawler_policy' => ['hints' => $hints]])['crawler_policy']['hints']);
    }

    #[TestDox('Attribute values are listed by attribute code')]
    public function test_attribute_values_are_keyed_by_code(): void
    {
        $config = $this->process(['product_exclusion' => ['attribute_values' => ['geo_visibility' => ['private', 'Privé']]]]);

        self::assertSame(['geo_visibility' => ['private', 'Privé']], $config['product_exclusion']['attribute_values']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'unknown stock strategy' => [['product_markdown' => ['stock_strategy' => 'everything']]];
        yield 'unknown price strategy' => [['product_markdown' => ['price_strategy' => 'free']]];
        yield 'negative cache lifetime' => [['http_cache' => ['max_age' => -1]]];
        yield 'negative product limit' => [['catalog' => ['product_limit' => -1]]];
        yield 'zero scan limit' => [['catalog' => ['product_scan_limit' => 0]]];
        yield 'unknown option' => [['unknown' => true]];
    }

    /** @param array<string, mixed> $configuration */
    #[DataProvider('invalidConfigurations')]
    #[TestDox('An invalid configuration is rejected')]
    public function test_invalid_configuration_is_rejected(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process($configuration);
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<string, array<string, mixed>>
     */
    private function process(array $configuration): array
    {
        /** @var array<string, array<string, mixed>> $processed */
        $processed = (new Processor())->processConfiguration(new Configuration(), [$configuration]);

        return $processed;
    }
}
