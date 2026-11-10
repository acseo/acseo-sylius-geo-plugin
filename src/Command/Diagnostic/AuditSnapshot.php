<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command\Diagnostic;

use ACSEO\SyliusGeoPlugin\Checker\ExportIssue;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final readonly class AuditSnapshot
{
    /**
     * @param list<ProductInterface> $exportableProducts
     * @param list<array{ProductInterface, list<ExportIssue>}> $excludedProducts
     * @param list<TaxonInterface> $exportableTaxons
     */
    public function __construct(
        public array $exportableProducts,
        public array $excludedProducts,
        public array $exportableTaxons,
    ) {
    }
}
