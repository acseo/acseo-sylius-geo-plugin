<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use ACSEO\SyliusGeoPlugin\Model\ExclusionRules;
use ACSEO\SyliusGeoPlugin\Taxon\TaxonAncestry;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final readonly class ExcludedTaxonMatcher
{
    public function __construct(
        private ExclusionRules $rules,
        private TaxonAncestry $ancestry,
    ) {
    }

    public function matches(ProductInterface $product): bool
    {
        if (!$this->rules->hasTaxonCodes()) {
            return false;
        }

        foreach ($product->getProductTaxons() as $productTaxon) {
            if (null !== $productTaxon->getTaxon() && $this->isInExcludedSubtree($productTaxon->getTaxon())) {
                return true;
            }
        }

        $mainTaxon = $product->getMainTaxon();

        return $mainTaxon instanceof \Sylius\Component\Core\Model\TaxonInterface && $this->isInExcludedSubtree($mainTaxon);
    }

    private function isInExcludedSubtree(TaxonInterface $taxon): bool
    {
        foreach ($this->ancestry->lineage($taxon) as $node) {
            if ($this->rules->excludesTaxonCode($node->getCode())) {
                return true;
            }
        }

        return false;
    }
}
