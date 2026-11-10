<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command\Diagnostic;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnosticsInterface;
use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final readonly class AuditSnapshotCollector
{
    public function __construct(
        private ChannelProductCatalogInterface $productCatalog,
        private ChannelTaxonCatalogInterface $taxonCatalog,
        private ProductGeoExportDiagnosticsInterface $productDiagnostics,
        private TaxonGeoVisibilityCheckerInterface $taxonVisibilityChecker,
        private ResourceLocalizer $localizer,
    ) {
    }

    public function collect(ChannelInterface $channel, string $localeCode): AuditSnapshot
    {
        $exportable = [];
        $excluded = [];
        foreach ($this->productCatalog->findCandidateProducts($channel, $localeCode) as $product) {
            $issues = $this->productDiagnostics->exclusionReasons($product, $channel, $localeCode);
            if ([] === $issues) {
                $exportable[] = $product;
            } else {
                $excluded[] = [$product, $issues];
            }
        }

        $taxons = array_values(array_filter(
            $this->taxonCatalog->findCandidateTaxons($channel, $localeCode),
            fn (TaxonInterface $taxon): bool => $this->taxonVisibilityChecker->isVisible($taxon, $channel) && null !== $this->localizer->taxonSlug($taxon, $localeCode),
        ));

        return new AuditSnapshot($exportable, $excluded, $taxons);
    }
}
