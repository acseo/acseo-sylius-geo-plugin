<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Url;

use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;

final class GeoUrlBuilderTest extends TestCase
{
    #[TestDox('Paths are built from the default route fragments and encoded')]
    public function test_it_builds_default_paths(): void
    {
        $builder = new GeoUrlBuilder();

        self::assertSame('/fr_FR/llms.txt', $builder->llmTxtPath('fr_FR'));
        self::assertSame('/llms.txt', $builder->rootLlmTxtPath());
        self::assertSame('/fr_FR/geo/products/a%20b.md', $builder->productMarkdownPath('fr_FR', 'a b'));
        self::assertSame('/fr_FR/geo/products/{slug}.md', $builder->productMarkdownPathPattern('fr_FR'));
        self::assertSame('/fr_FR/products/mug', $builder->productCanonicalPath('fr_FR', 'mug'));
        self::assertSame('/fr_FR/taxons/caps/simple%20cap', $builder->taxonCanonicalPath('fr_FR', 'caps/simple cap'));
    }

    #[TestDox('Route fragments follow the configured prefixes')]
    public function test_it_uses_configured_fragments(): void
    {
        $builder = new GeoUrlBuilder('/ai.txt/', '/ai/', '/items/');

        self::assertSame('/en_US/ai.txt', $builder->llmTxtPath('en_US'));
        self::assertSame('/en_US/ai/items/mug.md', $builder->productMarkdownPath('en_US', 'mug'));
    }

    #[TestDox('Absolute URLs use the channel hostname over https')]
    public function test_it_builds_absolute_urls(): void
    {
        $builder = new GeoUrlBuilder();
        $channel = CatalogFactory::channel();

        self::assertSame('https://shop.example.com', $builder->baseUrl($channel));
        self::assertSame('https://shop.example.com/en_US/llms.txt', $builder->absolute($channel, '/en_US/llms.txt'));
    }

    /** @return iterable<string, array{?string}> */
    public static function unsafeHostnames(): iterable
    {
        yield 'empty' => [''];
        yield 'null' => [null];
        yield 'path injection' => ['shop.example.com/evil'];
        yield 'credentials' => ['user:pass@shop.example.com'];
        yield 'markup' => ['shop.example.com"><script>'];
    }

    #[DataProvider('unsafeHostnames')]
    #[TestDox('An unsafe hostname falls back to relative paths')]
    public function test_it_falls_back_to_relative_paths(?string $hostname): void
    {
        $builder = new GeoUrlBuilder();
        $channel = CatalogFactory::channel('WEB', $hostname);

        self::assertNull($builder->baseUrl($channel));
        self::assertSame('/en_US/llms.txt', $builder->absolute($channel, '/en_US/llms.txt'));
    }
}
