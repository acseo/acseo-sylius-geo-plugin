<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\LlmTxt;

use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class CrawlerHintsSection
{
    public function __construct(
        private GeoUrlBuilderInterface $urlBuilder,
        private CrawlerTextTranslator $texts,
    ) {
    }

    public function build(ChannelInterface $channel, string $localeCode): MarkdownSection
    {
        return new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.crawler_hints.title'), [
            \sprintf('- %s: %s', $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.crawler_hints.locale_index'), $this->urlBuilder->absolute($channel, $this->urlBuilder->llmTxtPath($localeCode))),
            \sprintf('- %s: %s', $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.crawler_hints.root_redirect'), $this->urlBuilder->absolute($channel, $this->urlBuilder->rootLlmTxtPath())),
            \sprintf('- %s: %s', $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.crawler_hints.product_pattern'), $this->urlBuilder->absolute($channel, $this->urlBuilder->productMarkdownPathPattern($localeCode))),
            '- ' . $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.crawler_hints.reminder'),
        ]);
    }
}
