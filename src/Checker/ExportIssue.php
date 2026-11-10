<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

final readonly class ExportIssue
{
    /** @param array<string, string> $parameters */
    public function __construct(
        public ExportIssueType $type,
        public array $parameters = [],
    ) {
    }
}
