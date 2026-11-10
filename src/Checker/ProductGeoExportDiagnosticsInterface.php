<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

interface ProductGeoExportDiagnosticsInterface
{
    /** @return list<ExportIssue> */
    public function exclusionReasons(ProductInterface $product, ChannelInterface $channel, string $localeCode): array;

    /** @return list<ExportIssue> */
    public function warnings(ProductInterface $product, ChannelInterface $channel): array;
}
