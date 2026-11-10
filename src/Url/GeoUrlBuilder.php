<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Url;

use Sylius\Component\Core\Model\ChannelInterface;

final readonly class GeoUrlBuilder implements GeoUrlBuilderInterface
{
    private const string HOSTNAME_PATTERN = '/\A[a-z0-9.-]+(?::[0-9]{1,5})?\z/i';

    private string $llmTxtPath;

    private string $productsPrefix;

    public function __construct(
        string $llmTxtPath = 'llms.txt',
        string $geoPrefix = 'geo',
        string $geoProductsPrefix = 'products',
    ) {
        $this->llmTxtPath = trim($llmTxtPath, '/');
        $this->productsPrefix = trim($geoPrefix, '/') . '/' . trim($geoProductsPrefix, '/');
    }

    #[\Override]
    public function absolute(ChannelInterface $channel, string $path): string
    {
        $baseUrl = $this->baseUrl($channel);

        return null === $baseUrl ? $path : $baseUrl . '/' . ltrim($path, '/');
    }

    #[\Override]
    public function baseUrl(ChannelInterface $channel): ?string
    {
        $hostname = trim((string) $channel->getHostname());

        return 1 === preg_match(self::HOSTNAME_PATTERN, $hostname) ? 'https://' . $hostname : null;
    }

    #[\Override]
    public function llmTxtPath(string $localeCode): string
    {
        return '/' . rawurlencode($localeCode) . '/' . $this->llmTxtPath;
    }

    #[\Override]
    public function rootLlmTxtPath(): string
    {
        return '/' . $this->llmTxtPath;
    }

    #[\Override]
    public function productMarkdownPath(string $localeCode, string $slug): string
    {
        return '/' . rawurlencode($localeCode) . '/' . $this->productsPrefix . '/' . rawurlencode($slug) . '.md';
    }

    #[\Override]
    public function productMarkdownPathPattern(string $localeCode): string
    {
        return '/' . rawurlencode($localeCode) . '/' . $this->productsPrefix . '/{slug}.md';
    }

    #[\Override]
    public function productCanonicalPath(string $localeCode, string $slug): string
    {
        return '/' . rawurlencode($localeCode) . '/products/' . rawurlencode($slug);
    }

    #[\Override]
    public function taxonCanonicalPath(string $localeCode, string $slug): string
    {
        return '/' . rawurlencode($localeCode) . '/taxons/' . implode('/', array_map(rawurlencode(...), explode('/', $slug)));
    }
}
