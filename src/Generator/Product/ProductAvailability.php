<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;

final readonly class ProductAvailability
{
    public function __construct(private AvailabilityCheckerInterface $availabilityChecker)
    {
    }

    public function ofProduct(ProductInterface $product): ?bool
    {
        $hasStockInformation = false;
        foreach ($product->getEnabledVariants() as $variant) {
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            $hasStockInformation = true;
            if ($this->availabilityChecker->isStockAvailable($variant)) {
                return true;
            }
        }

        return $hasStockInformation ? false : null;
    }

    public function ofVariant(ProductVariantInterface $variant): bool
    {
        return $this->availabilityChecker->isStockAvailable($variant);
    }
}
