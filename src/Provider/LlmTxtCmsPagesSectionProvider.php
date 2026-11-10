<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Provider;

use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class LlmTxtCmsPagesSectionProvider implements LlmTxtSectionProviderInterface
{
    /** @param iterable<CmsPageProviderInterface> $cmsPageProviders */
    public function __construct(
        private iterable $cmsPageProviders,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    #[\Override]
    public function supports(ChannelInterface $channel, string $localeCode): bool
    {
        return true;
    }

    #[\Override]
    public function provide(ChannelInterface $channel, string $localeCode): array
    {
        $lines = $this->lines($channel, $localeCode);

        return [] === $lines ? [] : [new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.cms_pages.title'), $lines)];
    }

    /** @return list<string> */
    private function lines(ChannelInterface $channel, string $localeCode): array
    {
        $lines = [];
        foreach ($this->cmsPageProviders as $provider) {
            foreach ($provider->findEnabledPages($channel, $localeCode) as $page) {
                if ((null === $page->localeCode || $page->localeCode === $localeCode) && $this->isSafeHttpUrl($page->url)) {
                    $lines[] = $this->line($page);
                }
            }
        }

        return array_values(array_unique($lines));
    }

    private function line(CmsPageLink $page): string
    {
        $line = \sprintf('- [%s](%s)', $this->sanitizer->linkText($page->title), $page->url);
        $description = trim((string) $page->description);

        return '' === $description ? $line : $line . ': ' . $this->sanitizer->text($description);
    }

    private function isSafeHttpUrl(string $url): bool
    {
        return false !== filter_var($url, \FILTER_VALIDATE_URL) && \in_array(parse_url($url, \PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
