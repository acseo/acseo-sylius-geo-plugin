<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\LlmTxt;

use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\CrawlerPolicyOptions;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;

final readonly class CrawlerPolicySection
{
    public function __construct(
        private CrawlerPolicyOptions $options,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    public function build(string $localeCode): ?MarkdownSection
    {
        if (!$this->options->enabled) {
            return null;
        }

        $access = $this->options->allowAiCrawlers
            ? $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.crawler_policy.ai_allowed')
            : $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.crawler_policy.ai_denied');

        $lines = [\sprintf('- %s: %s', $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.crawler_policy.ai_access'), $access)];
        foreach ($this->options->hintsFor($localeCode) as $hint) {
            if ('' !== trim($hint)) {
                $lines[] = '- ' . $this->sanitizer->text(trim($hint));
            }
        }

        return new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.crawler_policy.title'), $lines);
    }
}
