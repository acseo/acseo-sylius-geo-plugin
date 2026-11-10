<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Model;

final readonly class ChannelInfoOptions
{
    /**
     * @param list<string> $currencies
     * @param list<string> $locales
     * @param list<string> $servedCountries
     */
    public function __construct(
        public bool $enabled = true,
        public bool $includeChannelCode = false,
        public ?string $shopName = null,
        public ?string $baseUrl = null,
        public array $currencies = [],
        public array $locales = [],
        public array $servedCountries = [],
    ) {
    }
}
