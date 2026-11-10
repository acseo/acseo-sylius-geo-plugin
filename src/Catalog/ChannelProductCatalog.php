<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Catalog;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

final readonly class ChannelProductCatalog implements ChannelProductCatalogInterface
{
    private const int BATCH_SIZE = 100;

    public function __construct(
        private CandidateProductReaderInterface $reader,
        private ProductGeoVisibilityCheckerInterface $visibilityChecker,
        private int $productLimit,
        private int $productScanLimit,
    ) {
    }

    #[\Override]
    public function findEnabledProducts(ChannelInterface $channel, string $localeCode): array
    {
        $maxScanned = max($this->productLimit, $this->productScanLimit);
        $visible = [];
        $scanned = 0;

        while (\count($visible) < $this->productLimit && $scanned < $maxScanned) {
            $size = min(self::BATCH_SIZE, $maxScanned - $scanned);
            $page = $this->reader->page($channel, $localeCode, $scanned, $size);

            $visible = [...$visible, ...array_filter($page, fn (ProductInterface $product): bool => $this->visibilityChecker->isVisible($product, $channel))];
            $scanned += $size;

            if (\count($page) < $size) {
                break;
            }
        }

        return \array_slice($visible, 0, $this->productLimit);
    }

    #[\Override]
    public function findCandidateProducts(ChannelInterface $channel, string $localeCode): array
    {
        return $this->productLimit < 1 ? [] : $this->reader->page($channel, $localeCode, 0, $this->productLimit, true);
    }
}
