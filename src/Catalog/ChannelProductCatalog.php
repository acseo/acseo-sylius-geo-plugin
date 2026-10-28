<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Catalog;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

final class ChannelProductCatalog implements ChannelProductCatalogInterface, ResetInterface
{
    /** @var array<string, list<ProductInterface>> */
    private array $enabledCache = [];

    /** @param ProductRepositoryInterface<ProductInterface> $productRepository */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly int $productLimit,
        private readonly ProductGeoVisibilityCheckerInterface $visibilityChecker,
        private readonly int $maxScannedProducts = 2000,
    ) {
    }

    public function reset(): void
    {
        $this->enabledCache = [];
    }

    public function findEnabledProducts(ChannelInterface $channel, string $localeCode): array
    {
        if ($this->productLimit < 1) {
            return [];
        }

        return $this->enabledCache[(string) $channel->getCode() . '|' . $localeCode] ??= $this->scanEnabledProducts($channel, $localeCode);
    }

    /** @return list<ProductInterface> */
    private function scanEnabledProducts(ChannelInterface $channel, string $localeCode): array
    {
        $maxScanned = max($this->productLimit, $this->maxScannedProducts);
        $requested = $this->productLimit;
        while (true) {
            $products = array_values($this->productRepository->findLatestByChannel($channel, $localeCode, $requested));
            $visible = array_values(array_filter(
                $products,
                fn (ProductInterface $product): bool => $this->visibilityChecker->isVisible($product, $channel),
            ));

            $exhausted = \count($products) < $requested;
            if (\count($visible) >= $this->productLimit || $exhausted || $requested >= $maxScanned) {
                return \array_slice($visible, 0, $this->productLimit);
            }

            $requested = min($requested * 2, $maxScanned);
        }
    }

    public function findCandidateProducts(ChannelInterface $channel, string $localeCode): array
    {
        return array_values($this->productRepository->findLatestByChannel($channel, $localeCode, $this->productLimit));
    }
}
