<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

interface ProductMarkdownGeneratorInterface
{
    public function generate(ProductInterface $product, ChannelInterface $channel, string $localeCode): string;
}
