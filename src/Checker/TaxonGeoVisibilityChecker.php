<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final class TaxonGeoVisibilityChecker implements TaxonGeoVisibilityCheckerInterface
{
    /** @param list<string> $excludedTaxonCodes */
    public function __construct(
        private readonly array $excludedTaxonCodes = [],
    ) {
    }

    public function isVisible(TaxonInterface $taxon, ChannelInterface $channel): bool
    {
        $excludedTaxonCodes = array_map('strval', $this->excludedTaxonCodes);
        $menuTaxon = $channel->getMenuTaxon();
        $belongsToChannel = false;

        foreach ($this->lineage($taxon) as $node) {
            if (!$node->isEnabled() || \in_array((string) $node->getCode(), $excludedTaxonCodes, true)) {
                return false;
            }

            if (null !== $menuTaxon && $this->isSame($node, $menuTaxon)) {
                $belongsToChannel = true;
            }
        }

        return $belongsToChannel;
    }

    /** @return \Generator<TaxonInterface> */
    private function lineage(TaxonInterface $taxon): \Generator
    {
        $seen = [];
        for ($node = $taxon; null !== $node && !isset($seen[spl_object_id($node)]); $node = $node->getParent()) {
            $seen[spl_object_id($node)] = true;

            yield $node;
        }
    }

    private function isSame(TaxonInterface $taxon, TaxonInterface $other): bool
    {
        return $taxon === $other || (null !== $taxon->getCode() && $taxon->getCode() === $other->getCode());
    }
}
