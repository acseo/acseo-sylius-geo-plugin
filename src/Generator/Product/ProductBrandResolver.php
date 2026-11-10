<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product;

use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Core\Model\ProductInterface;

final readonly class ProductBrandResolver
{
    private const array BRAND_NAMES = ['brand', 'manufacturer', 'marque'];

    public function __construct(private AttributeValueFormatter $formatter)
    {
    }

    public function resolve(ProductInterface $product, string $localeCode): ?string
    {
        foreach ($product->getAttributesByLocale($localeCode, $localeCode) as $attribute) {
            if ($this->isBrandAttribute($attribute)) {
                return $this->formatter->forLocale($attribute, $localeCode);
            }
        }

        return null;
    }

    public function isBrandAttribute(AttributeValueInterface $attribute): bool
    {
        return \in_array(mb_strtolower((string) $attribute->getCode()), self::BRAND_NAMES, true) ||
            \in_array(mb_strtolower((string) $attribute->getName()), self::BRAND_NAMES, true);
    }
}
