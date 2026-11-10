<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Model;

final readonly class ProductMarkdownOptions
{
    public function __construct(
        public bool $includeBrand = true,
        public StockStrategy $stockStrategy = StockStrategy::Detailed,
        public PriceStrategy $priceStrategy = PriceStrategy::Calculated,
        public bool $includeImages = true,
        public bool $includeAttributes = true,
        public bool $includeVariants = true,
        public bool $includeTaxons = true,
        public bool $includeJsonLd = true,
    ) {
    }

    public function exposesPrices(): bool
    {
        return PriceStrategy::Hidden !== $this->priceStrategy;
    }

    public function exposesStock(): bool
    {
        return StockStrategy::Hidden !== $this->stockStrategy;
    }

    public function exposesDetailedStock(): bool
    {
        return StockStrategy::Detailed === $this->stockStrategy;
    }
}
