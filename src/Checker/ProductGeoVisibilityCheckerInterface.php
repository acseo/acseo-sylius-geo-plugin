<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

interface ProductGeoVisibilityCheckerInterface
{
    public function isVisible(ProductInterface $product, ChannelInterface $channel): bool;
}
