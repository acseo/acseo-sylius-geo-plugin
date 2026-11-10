<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

interface TaxonGeoExportDiagnosticsInterface
{
    /** @return list<ExportIssue> */
    public function exclusionReasons(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): array;
}
