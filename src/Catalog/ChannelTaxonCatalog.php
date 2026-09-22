<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Catalog;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

final class ChannelTaxonCatalog implements ChannelTaxonCatalogInterface, ResetInterface
{
    /** @var array<string, list<TaxonInterface>> */
    private array $loaded = [];

    /** @var array<string, list<TaxonInterface>> */
    private array $enabledCache = [];

    /** @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository */
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly int $taxonLimit,
        private readonly TaxonGeoVisibilityCheckerInterface $visibilityChecker,
    ) {
    }

    public function reset(): void
    {
        $this->loaded = [];
        $this->enabledCache = [];
    }

    public function findEnabledTaxons(ChannelInterface $channel, string $localeCode): array
    {
        if ($this->taxonLimit < 1) {
            return [];
        }

        $key = (string) $channel->getCode() . '|' . $localeCode;
        if (isset($this->enabledCache[$key])) {
            return $this->enabledCache[$key];
        }

        $visible = [];
        foreach ($this->loadTaxons($channel, $localeCode) as $taxon) {
            if (!$this->visibilityChecker->isVisible($taxon, $channel)) {
                continue;
            }

            $visible[] = $taxon;
            if (\count($visible) >= $this->taxonLimit) {
                break;
            }
        }

        return $this->enabledCache[$key] = $visible;
    }

    public function findCandidateTaxons(ChannelInterface $channel, string $localeCode): array
    {
        if ($this->taxonLimit < 1) {
            return [];
        }

        return \array_slice($this->loadTaxons($channel, $localeCode), 0, $this->taxonLimit);
    }

    /** @return list<TaxonInterface> */
    private function loadTaxons(ChannelInterface $channel, string $localeCode): array
    {
        $menuTaxon = $channel->getMenuTaxon();
        if (null === $menuTaxon) {
            return [];
        }

        /** @var list<TaxonInterface> $taxons */
        $taxons = $this->taxonRepository->findChildrenByChannelMenuTaxon($menuTaxon, $localeCode);

        return $this->loaded[(string) $channel->getCode() . '|' . $localeCode] ??= $taxons;
    }
}
