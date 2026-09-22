<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Url;

use Sylius\Component\Core\Model\ChannelInterface;

interface GeoUrlBuilderInterface
{
    public function absolute(ChannelInterface $channel, string $path): string;

    public function baseUrl(ChannelInterface $channel): ?string;

    public function llmTxtPath(string $localeCode): string;

    public function rootLlmTxtPath(): string;

    public function productMarkdownPath(string $localeCode, string $slug): string;

    public function productMarkdownPathPattern(string $localeCode): string;

    public function productCanonicalPath(string $localeCode, string $slug): string;

    public function taxonCanonicalPath(string $localeCode, string $slug): string;
}
