<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Model;

final readonly class ProductMarkdownOptions
{
    public const STOCK_STRATEGY_HIDDEN = 'hidden';

    public const STOCK_STRATEGY_AVAILABILITY_ONLY = 'availability_only';

    public const STOCK_STRATEGY_DETAILED = 'detailed';

    public const PRICE_STRATEGY_CALCULATED = 'calculated';

    public const PRICE_STRATEGY_BASE = 'base';

    public const PRICE_STRATEGY_HIDDEN = 'hidden';

    public function __construct(
        public bool $includeBrand = true,
        public string $stockStrategy = self::STOCK_STRATEGY_DETAILED,
        public string $priceStrategy = self::PRICE_STRATEGY_CALCULATED,
        public bool $includeImages = true,
        public bool $includeAttributes = true,
        public bool $includeVariants = true,
        public bool $includeTaxons = true,
        public bool $includeJsonLd = true,
    ) {
    }

    public function exposesPrices(): bool
    {
        return self::PRICE_STRATEGY_HIDDEN !== $this->priceStrategy;
    }

    public function exposesStock(): bool
    {
        return self::STOCK_STRATEGY_HIDDEN !== $this->stockStrategy;
    }

    public function exposesDetailedStock(): bool
    {
        return self::STOCK_STRATEGY_DETAILED === $this->stockStrategy;
    }
}
