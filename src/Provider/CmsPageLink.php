<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Provider;

final readonly class CmsPageLink
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $description = null,
        public ?string $localeCode = null,
    ) {
    }
}
