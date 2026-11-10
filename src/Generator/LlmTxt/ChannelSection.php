<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\LlmTxt;

use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\ChannelInfoOptions;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class ChannelSection
{
    public function __construct(
        private ChannelInfoOptions $options,
        private LlmTxtChannelInfo $channelInfo,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    public function build(ChannelInterface $channel): ?MarkdownSection
    {
        if (!$this->options->enabled) {
            return null;
        }

        $facts = [
            'acseo_sylius_geo_plugin.output.llm_txt.channel.shop_name' => $this->sanitizer->text($this->channelInfo->shopName($channel)),
            'acseo_sylius_geo_plugin.output.llm_txt.channel.channel_code' => $this->options->includeChannelCode ? (string) $channel->getCode() : null,
            'acseo_sylius_geo_plugin.output.llm_txt.channel.base_url' => $this->channelInfo->baseUrl($channel),
            'acseo_sylius_geo_plugin.output.llm_txt.channel.currencies' => $this->joined($this->channelInfo->currencies($channel)),
            'acseo_sylius_geo_plugin.output.llm_txt.channel.locales' => $this->joined($this->channelInfo->locales($channel)),
            'acseo_sylius_geo_plugin.output.llm_txt.channel.served_countries' => $this->joined($this->channelInfo->servedCountries($channel)),
        ];

        $lines = [];
        foreach (array_filter($facts, static fn (?string $value): bool => null !== $value && '' !== $value) as $labelKey => $value) {
            $lines[] = \sprintf('- %s: %s', $this->texts->translate($labelKey), $value);
        }

        return new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.channel.title'), $lines);
    }

    /** @param list<string> $values */
    private function joined(array $values): ?string
    {
        return [] === $values ? null : implode(', ', $values);
    }
}
