<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Provider;

use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Service\ResetInterface;

final class LlmTxtCmsPagesSectionProvider implements LlmTxtSectionProviderInterface, ResetInterface
{
    /** @var array<string, list<string>> */
    private array $lines = [];

    /** @param iterable<CmsPageProviderInterface> $cmsPageProviders */
    public function __construct(
        private readonly iterable $cmsPageProviders,
        private readonly MarkdownSanitizer $markdownSanitizer = new MarkdownSanitizer(),
    ) {
    }

    public function reset(): void
    {
        $this->lines = [];
    }

    public function supports(ChannelInterface $channel, string $localeCode): bool
    {
        return [] !== $this->lines($channel, $localeCode);
    }

    public function provide(ChannelInterface $channel, string $localeCode): array
    {
        $lines = $this->lines($channel, $localeCode);

        return [] === $lines ? [] : [new MarkdownSection('CMS pages', $lines)];
    }

    /** @return list<string> */
    private function lines(ChannelInterface $channel, string $localeCode): array
    {
        $key = (string) $channel->getCode() . '|' . $localeCode;
        if (isset($this->lines[$key])) {
            return $this->lines[$key];
        }

        $lines = [];
        foreach ($this->cmsPageProviders as $provider) {
            foreach ($provider->findEnabledPages($channel, $localeCode) as $page) {
                if (null !== $page->localeCode && $page->localeCode !== $localeCode) {
                    continue;
                }

                if (!$this->isSafeHttpUrl($page->url)) {
                    continue;
                }

                $line = \sprintf('- [%s](%s)', $this->markdownSanitizer->linkText($page->title), $page->url);
                if (null !== $page->description && '' !== trim($page->description)) {
                    $line .= ': ' . $this->markdownSanitizer->text(trim($page->description));
                }

                $lines[] = $line;
            }
        }

        return $this->lines[$key] = array_values(array_unique($lines));
    }

    private function isSafeHttpUrl(string $url): bool
    {
        if (false === filter_var($url, \FILTER_VALIDATE_URL)) {
            return false;
        }

        return \in_array(parse_url($url, \PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
