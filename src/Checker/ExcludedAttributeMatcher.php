<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use ACSEO\SyliusGeoPlugin\Attribute\SelectAttributeLabelResolver;
use ACSEO\SyliusGeoPlugin\Model\ExclusionRules;
use Sylius\Component\Core\Model\ProductInterface;

final readonly class ExcludedAttributeMatcher
{
    public function __construct(
        private ExclusionRules $rules,
        private SelectAttributeLabelResolver $labels,
    ) {
    }

    public function matches(ProductInterface $product): bool
    {
        foreach ($product->getAttributes() as $attribute) {
            $code = (string) $attribute->getCode();

            if ($this->rules->excludesAttributeCode($code) ||
                $this->hasExcludedValue($code, $attribute->getValue()) ||
                $this->hasExcludedValue($code, $this->labels->inAnyLocale($attribute))) {
                return true;
            }
        }

        return false;
    }

    private function hasExcludedValue(string $code, mixed $value): bool
    {
        foreach (is_iterable($value) ? $value : [$value] as $item) {
            if ($this->isStringable($item) && $this->rules->excludesAttributeValue($code, (string) $item)) {
                return true;
            }
        }

        return false;
    }

    /** @phpstan-assert-if-true scalar|\Stringable|null $value */
    private function isStringable(mixed $value): bool
    {
        return null === $value || \is_scalar($value) || $value instanceof \Stringable;
    }
}
