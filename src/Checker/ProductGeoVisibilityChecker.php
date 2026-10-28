<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use ACSEO\SyliusGeoPlugin\Attribute\SelectAttributeLabels;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface as BaseTaxonInterface;

final class ProductGeoVisibilityChecker implements ProductGeoVisibilityCheckerInterface
{
    /**
     * @param list<string> $excludedTaxonCodes
     * @param list<string> $excludedAttributeCodes
     * @param array<string, list<scalar>> $excludedAttributeValues
     */
    public function __construct(
        private readonly array $excludedTaxonCodes = [],
        private readonly array $excludedAttributeCodes = [],
        private readonly array $excludedAttributeValues = [],
    ) {
    }

    public function isVisible(ProductInterface $product, ChannelInterface $channel): bool
    {
        if (!$product->isEnabled()) {
            return false;
        }

        if (!$product->hasChannel($channel)) {
            return false;
        }

        if (!$this->isWithinAvailabilityWindow($product)) {
            return false;
        }

        if ($this->hasExcludedTaxon($product)) {
            return false;
        }

        return !$this->hasExcludedAttribute($product);
    }

    private function isWithinAvailabilityWindow(ProductInterface $product): bool
    {
        $now = new \DateTimeImmutable();

        if (\method_exists($product, 'getAvailableOn')) {
            $availableOn = $product->getAvailableOn();
            if ($availableOn instanceof \DateTimeInterface && $availableOn > $now) {
                return false;
            }
        }

        if (\method_exists($product, 'getAvailableUntil')) {
            $availableUntil = $product->getAvailableUntil();
            if ($availableUntil instanceof \DateTimeInterface && $availableUntil < $now) {
                return false;
            }
        }

        return true;
    }

    private function hasExcludedTaxon(ProductInterface $product): bool
    {
        if ([] === $this->excludedTaxonCodes) {
            return false;
        }

        $excludedTaxonCodes = array_map('strval', $this->excludedTaxonCodes);
        foreach ($product->getProductTaxons() as $productTaxon) {
            $taxon = $productTaxon->getTaxon();
            if ($taxon instanceof TaxonInterface && $this->isInExcludedSubtree($taxon, $excludedTaxonCodes)) {
                return true;
            }
        }

        $mainTaxon = $product->getMainTaxon();

        return $mainTaxon instanceof TaxonInterface && $this->isInExcludedSubtree($mainTaxon, $excludedTaxonCodes);
    }

    /** @param list<string> $excludedTaxonCodes */
    private function isInExcludedSubtree(BaseTaxonInterface $taxon, array $excludedTaxonCodes): bool
    {
        $seen = [];
        for ($node = $taxon; null !== $node && !isset($seen[spl_object_id($node)]); $node = $node->getParent()) {
            $seen[spl_object_id($node)] = true;

            if (\in_array((string) $node->getCode(), $excludedTaxonCodes, true)) {
                return true;
            }
        }

        return false;
    }

    private function hasExcludedAttribute(ProductInterface $product): bool
    {
        $excludedAttributeCodes = array_map('strval', $this->excludedAttributeCodes);
        foreach ($product->getAttributes() as $attribute) {
            $code = (string) $attribute->getCode();
            if (\in_array($code, $excludedAttributeCodes, true)) {
                return true;
            }

            if (!$this->hasExcludedAttributeValue($code, $attribute->getValue()) && !$this->hasExcludedAttributeValue($code, SelectAttributeLabels::inAnyLocale($attribute))) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function hasExcludedAttributeValue(string $code, mixed $value): bool
    {
        if (!\array_key_exists($code, $this->excludedAttributeValues)) {
            return false;
        }

        $values = array_map('strval', $this->excludedAttributeValues[$code]);
        if (\is_array($value) || $value instanceof \Traversable) {
            foreach ($value as $item) {
                if (self::isStringable($item) && \in_array((string) $item, $values, true)) {
                    return true;
                }
            }

            return false;
        }

        return self::isStringable($value) && \in_array((string) $value, $values, true);
    }

    /** @phpstan-assert-if-true scalar|\Stringable|null $value */
    private static function isStringable(mixed $value): bool
    {
        return null === $value || \is_scalar($value) || $value instanceof \Stringable;
    }
}
