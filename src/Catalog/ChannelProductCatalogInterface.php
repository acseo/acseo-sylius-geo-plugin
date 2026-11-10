<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Catalog;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

interface ChannelProductCatalogInterface
{
    /** @return list<ProductInterface> */
    public function findEnabledProducts(ChannelInterface $channel, string $localeCode): array;

    /** @return list<ProductInterface> */
    public function findCandidateProducts(ChannelInterface $channel, string $localeCode): array;
}
