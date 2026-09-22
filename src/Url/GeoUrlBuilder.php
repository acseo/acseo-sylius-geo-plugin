<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Url;

use Sylius\Component\Core\Model\ChannelInterface;

final class GeoUrlBuilder implements GeoUrlBuilderInterface
{
    private readonly string $llmTxtPath;

    private readonly string $geoProductsPrefix;

    public function __construct(
        string $llmTxtPath = 'llm.txt',
        string $geoPrefix = 'geo',
        string $geoProductsPrefix = 'products',
    ) {
        $this->llmTxtPath = trim($llmTxtPath, '/');
        $this->geoProductsPrefix = trim($geoPrefix, '/') . '/' . trim($geoProductsPrefix, '/');
    }

    public function absolute(ChannelInterface $channel, string $path): string
    {
        $baseUrl = $this->baseUrl($channel);
        if (null === $baseUrl) {
            return $path;
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    public function baseUrl(ChannelInterface $channel): ?string
    {
        $hostname = trim((string) $channel->getHostname());
        if ('' === $hostname || !$this->isSafeHostname($hostname)) {
            return null;
        }

        return 'https://' . $hostname;
    }

    public function llmTxtPath(string $localeCode): string
    {
        return '/' . rawurlencode($localeCode) . '/' . $this->llmTxtPath;
    }

    public function rootLlmTxtPath(): string
    {
        return '/' . $this->llmTxtPath;
    }

    public function productMarkdownPath(string $localeCode, string $slug): string
    {
        return '/' . rawurlencode($localeCode) . '/' . $this->geoProductsPrefix . '/' . rawurlencode($slug) . '.md';
    }

    public function productMarkdownPathPattern(string $localeCode): string
    {
        return '/' . rawurlencode($localeCode) . '/' . $this->geoProductsPrefix . '/{slug}.md';
    }

    public function productCanonicalPath(string $localeCode, string $slug): string
    {
        return '/' . rawurlencode($localeCode) . '/products/' . rawurlencode($slug);
    }

    public function taxonCanonicalPath(string $localeCode, string $slug): string
    {
        return '/' . rawurlencode($localeCode) . '/taxons/' . $this->encodeSlugPath($slug);
    }

    private function encodeSlugPath(string $slug): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $slug)));
    }

    private function isSafeHostname(string $hostname): bool
    {
        return 1 === preg_match('/\A[a-z0-9.-]+(?::[0-9]{1,5})?\z/i', $hostname);
    }
}
