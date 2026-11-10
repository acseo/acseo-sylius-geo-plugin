<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Catalog;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

interface CandidateProductReaderInterface
{
    /** @return list<ProductInterface> */
    public function page(ChannelInterface $channel, string $localeCode, int $offset, int $size, bool $withVariants = false): array;
}
