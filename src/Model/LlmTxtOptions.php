<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Model;

final readonly class LlmTxtOptions
{
    /**
     * @param list<string> $currencies
     * @param list<string> $locales
     * @param list<string> $servedCountries
     * @param array<string, list<scalar>>|list<scalar> $crawlerPolicyHints
     */
    public function __construct(
        public bool $includeChannelInfo = true,
        public ?string $shopName = null,
        public ?string $baseUrl = null,
        public array $currencies = [],
        public array $locales = [],
        public array $servedCountries = [],
        public bool $includeCrawlerPolicy = true,
        public bool $allowAiCrawlers = true,
        public array $crawlerPolicyHints = [],
    ) {
    }

    /** @return list<string> */
    public function crawlerHintsFor(string $localeCode): array
    {
        if ([] === $this->crawlerPolicyHints) {
            return [];
        }

        $hints = array_is_list($this->crawlerPolicyHints)
            ? $this->crawlerPolicyHints
            : ($this->crawlerPolicyHints[$localeCode] ?? $this->crawlerPolicyHints['default'] ?? []);

        if (!\is_array($hints)) {
            return [];
        }

        return array_values(array_filter($hints, static fn (mixed $hint): bool => \is_string($hint)));
    }
}
