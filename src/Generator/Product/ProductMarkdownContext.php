<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

final readonly class ProductMarkdownContext
{
    public function __construct(
        public ProductInterface $product,
        public ChannelInterface $channel,
        public string $localeCode,
    ) {
    }
}
