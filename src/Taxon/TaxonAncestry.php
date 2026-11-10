<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Taxon;

use Sylius\Component\Taxonomy\Model\TaxonInterface;

final class TaxonAncestry
{
    /** @return \Generator<TaxonInterface> */
    public function lineage(TaxonInterface $taxon): \Generator
    {
        $seen = [];
        for ($node = $taxon; $node instanceof TaxonInterface && !isset($seen[spl_object_id($node)]); $node = $node->getParent()) {
            $seen[spl_object_id($node)] = true;

            yield $node;
        }
    }
}
