<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\LlmTxt;

use ACSEO\SyliusGeoPlugin\Markdown\MarkdownDocument;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\ChannelInfoOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class LlmTxtHeader
{
    public function __construct(
        private ChannelInfoOptions $options,
        private LlmTxtChannelInfo $channelInfo,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    public function start(ChannelInterface $channel, string $localeCode): MarkdownDocument
    {
        return (new MarkdownDocument($this->sanitizer))
            ->title($this->channelInfo->shopName($channel))
            ->quote($this->summary($channel, $localeCode))
            ->paragraph($this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.intro'));
    }

    private function summary(ChannelInterface $channel, string $localeCode): string
    {
        $parts = [];
        if ($this->options->enabled && $this->options->includeChannelCode) {
            $parts[] = $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.summary.channel', ['%code%' => (string) $channel->getCode()]);
        }

        $parts[] = $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.summary.locale', ['%locale%' => $localeCode]);

        $currency = $channel->getBaseCurrency()?->getCode();
        if (null !== $currency) {
            $parts[] = $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.summary.currency', ['%currency%' => $currency]);
        }

        $localeCodes = $this->options->enabled ? $this->channelInfo->channelLocales($channel) : [];
        if ([] !== $localeCodes) {
            $parts[] = $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.summary.locales', ['%locales%' => implode(', ', $localeCodes)]);
        }

        return implode(' ', $parts);
    }
}
