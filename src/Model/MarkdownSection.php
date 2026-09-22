<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Model;

final readonly class MarkdownSection
{
    /** @param list<string> $lines */
    public function __construct(
        public string $title,
        public array $lines,
    ) {
    }
}
