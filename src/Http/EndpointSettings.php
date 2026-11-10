<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

final readonly class EndpointSettings
{
    public function __construct(
        private bool $llmTxt = true,
        private bool $productMarkdown = true,
        private bool $taxonMarkdown = true,
        private bool $sitemap = true,
        private bool $api = true,
    ) {
    }

    public function isEnabled(Endpoint $endpoint): bool
    {
        return match ($endpoint) {
            Endpoint::LlmTxt => $this->llmTxt,
            Endpoint::ProductMarkdown => $this->productMarkdown,
            Endpoint::TaxonMarkdown => $this->taxonMarkdown,
            Endpoint::Sitemap => $this->sitemap,
            Endpoint::Api => $this->api,
        };
    }
}
