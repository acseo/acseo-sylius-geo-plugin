<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Model;

final readonly class TaxonMarkdownOptions
{
    public function __construct(
        public bool $includeDescription = true,
        public bool $includeChildren = true,
    ) {
    }
}
