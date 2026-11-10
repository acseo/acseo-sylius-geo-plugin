<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

interface TaxonGeoVisibilityCheckerInterface
{
    public function isVisible(TaxonInterface $taxon, ChannelInterface $channel): bool;
}
