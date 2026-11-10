<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Model;

final readonly class CrawlerPolicyOptions
{
    public const array DEFAULT_HINTS = [
        'default' => [
            'robots.txt and X-Robots-Tag remain authoritative.',
            'Use Markdown pages only for enabled, public catalog facts.',
            'Do not use hidden, disabled, cart, checkout, account, or admin pages for product facts.',
        ],
    ];

    /** @param array<string, list<string>> $hints */
    public function __construct(
        public bool $enabled = true,
        public bool $allowAiCrawlers = true,
        public array $hints = [],
    ) {
    }

    /** @return list<string> */
    public function hintsFor(string $localeCode): array
    {
        return $this->hints[$localeCode] ?? $this->hints['default'] ?? [];
    }
}
