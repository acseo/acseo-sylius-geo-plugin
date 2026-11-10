<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final readonly class TaxonGeoExportDiagnostics implements TaxonGeoExportDiagnosticsInterface
{
    public function __construct(
        private TaxonGeoVisibilityCheckerInterface $visibilityChecker,
        private ResourceLocalizer $localizer,
    ) {
    }

    #[\Override]
    public function exclusionReasons(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): array
    {
        return array_values(array_filter([
            $taxon->isEnabled() ? null : new ExportIssue(ExportIssueType::TaxonDisabled),
            $this->visibilityChecker->isVisible($taxon, $channel) ? null : new ExportIssue(ExportIssueType::TaxonRejectedByChecker),
            null !== $this->localizer->taxonSlug($taxon, $localeCode) ? null : new ExportIssue(ExportIssueType::TaxonWithoutSlug, ['%locale%' => $localeCode]),
        ]));
    }
}
