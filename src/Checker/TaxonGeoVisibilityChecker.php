<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use ACSEO\SyliusGeoPlugin\Model\ExclusionRules;
use ACSEO\SyliusGeoPlugin\Taxon\TaxonAncestry;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final readonly class TaxonGeoVisibilityChecker implements TaxonGeoVisibilityCheckerInterface
{
    public function __construct(
        private ExclusionRules $rules,
        private TaxonAncestry $ancestry,
    ) {
    }

    #[\Override]
    public function isVisible(TaxonInterface $taxon, ChannelInterface $channel): bool
    {
        $menuTaxon = $channel->getMenuTaxon();
        $belongsToChannel = false;

        foreach ($this->ancestry->lineage($taxon) as $node) {
            if (!$node->isEnabled() || $this->rules->excludesTaxonCode($node->getCode())) {
                return false;
            }

            $belongsToChannel = $belongsToChannel || $this->isSame($node, $menuTaxon);
        }

        return $belongsToChannel;
    }

    private function isSame(TaxonInterface $taxon, ?TaxonInterface $menuTaxon): bool
    {
        return $menuTaxon instanceof TaxonInterface && ($taxon === $menuTaxon || (null !== $taxon->getCode() && $taxon->getCode() === $menuTaxon->getCode()));
    }
}
