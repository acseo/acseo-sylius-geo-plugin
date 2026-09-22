<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Model;

use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductMarkdownOptionsTest extends TestCase
{
    public function testDefaultsExposeEverything(): void
    {
        $options = new ProductMarkdownOptions();

        self::assertTrue($options->exposesPrices());
        self::assertTrue($options->exposesStock());
        self::assertTrue($options->exposesDetailedStock());
        self::assertTrue($options->includeBrand);
        self::assertTrue($options->includeJsonLd);
    }

    /** @return iterable<string, array{string, bool, bool}> */
    public static function stockStrategies(): iterable
    {
        yield 'hidden' => [ProductMarkdownOptions::STOCK_STRATEGY_HIDDEN, false, false];
        yield 'availability only' => [ProductMarkdownOptions::STOCK_STRATEGY_AVAILABILITY_ONLY, true, false];
        yield 'detailed' => [ProductMarkdownOptions::STOCK_STRATEGY_DETAILED, true, true];
    }

    #[DataProvider('stockStrategies')]
    public function testStockStrategyControlsStockExposure(string $strategy, bool $stock, bool $detailed): void
    {
        $options = new ProductMarkdownOptions(stockStrategy: $strategy);

        self::assertSame($stock, $options->exposesStock());
        self::assertSame($detailed, $options->exposesDetailedStock());
    }

    public function testHiddenPriceStrategyHidesPrices(): void
    {
        self::assertFalse((new ProductMarkdownOptions(priceStrategy: ProductMarkdownOptions::PRICE_STRATEGY_HIDDEN))->exposesPrices());
        self::assertTrue((new ProductMarkdownOptions(priceStrategy: ProductMarkdownOptions::PRICE_STRATEGY_BASE))->exposesPrices());
    }
}
