<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Catalog;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

interface ChannelTaxonCatalogInterface
{
    /** @return list<TaxonInterface> */
    public function findEnabledTaxons(ChannelInterface $channel, string $localeCode): array;

    /** @return list<TaxonInterface> */
    public function findCandidateTaxons(ChannelInterface $channel, string $localeCode): array;
}
