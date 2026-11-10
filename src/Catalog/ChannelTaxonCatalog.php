<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Catalog;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;

final readonly class ChannelTaxonCatalog implements ChannelTaxonCatalogInterface
{
    /** @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository */
    public function __construct(
        private TaxonRepositoryInterface $taxonRepository,
        private TaxonGeoVisibilityCheckerInterface $visibilityChecker,
        private int $taxonLimit,
    ) {
    }

    #[\Override]
    public function findEnabledTaxons(ChannelInterface $channel, string $localeCode): array
    {
        $visible = array_filter(
            $this->loadTaxons($channel, $localeCode),
            fn (TaxonInterface $taxon): bool => $this->visibilityChecker->isVisible($taxon, $channel),
        );

        return \array_slice(array_values($visible), 0, $this->taxonLimit);
    }

    #[\Override]
    public function findCandidateTaxons(ChannelInterface $channel, string $localeCode): array
    {
        return \array_slice($this->loadTaxons($channel, $localeCode), 0, $this->taxonLimit);
    }

    /** @return list<TaxonInterface> */
    private function loadTaxons(ChannelInterface $channel, string $localeCode): array
    {
        $menuTaxon = $channel->getMenuTaxon();
        if (!$menuTaxon instanceof TaxonInterface || $this->taxonLimit < 1) {
            return [];
        }

        /** @var list<TaxonInterface> $taxons */
        $taxons = $this->taxonRepository->findChildrenByChannelMenuTaxon($menuTaxon, $localeCode);

        return $taxons;
    }
}
