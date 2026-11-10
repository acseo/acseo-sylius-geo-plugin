<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\LlmTxt;

use ACSEO\SyliusGeoPlugin\Model\ChannelInfoOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class LlmTxtChannelInfo
{
    public function __construct(
        private ChannelInfoOptions $options,
        private GeoUrlBuilderInterface $urlBuilder,
        private CrawlerTextTranslator $texts,
    ) {
    }

    public function shopName(ChannelInterface $channel): string
    {
        $hostname = trim((string) $channel->getHostname());

        return match (true) {
            '' !== trim((string) $this->options->shopName) => trim((string) $this->options->shopName),
            '' !== trim((string) $channel->getName()) => trim((string) $channel->getName()),
            $this->options->includeChannelCode => (string) $channel->getCode(),
            '' !== $hostname => $hostname,
            default => $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.fallback_shop_name'),
        };
    }

    public function baseUrl(ChannelInterface $channel): ?string
    {
        $configured = trim((string) $this->options->baseUrl);

        return '' === $configured ? $this->urlBuilder->baseUrl($channel) : rtrim($configured, '/');
    }

    /** @return list<string> */
    public function currencies(ChannelInterface $channel): array
    {
        if ([] !== $this->options->currencies) {
            return $this->options->currencies;
        }

        $codes = [];
        foreach ($channel->getCurrencies() as $currency) {
            $codes[] = $currency->getCode();
        }

        if ([] === array_filter($codes, static fn (?string $code): bool => null !== $code && '' !== $code)) {
            $codes = [$channel->getBaseCurrency()?->getCode()];
        }

        return $this->uniqueStrings($codes);
    }

    /** @return list<string> */
    public function locales(ChannelInterface $channel): array
    {
        return [] !== $this->options->locales ? $this->options->locales : $this->channelLocales($channel);
    }

    /** @return list<string> */
    public function channelLocales(ChannelInterface $channel): array
    {
        $codes = [];
        foreach ($channel->getLocales() as $locale) {
            $codes[] = $locale->getCode();
        }

        return $this->uniqueStrings($codes);
    }

    /** @return list<string> */
    public function servedCountries(ChannelInterface $channel): array
    {
        if ([] !== $this->options->servedCountries) {
            return $this->options->servedCountries;
        }

        $labels = [];
        foreach ($channel->getCountries() as $country) {
            if (true === $country->isEnabled()) {
                $labels[] = trim((string) ($country->getName() ?? $country->getCode()));
            }
        }

        return $this->uniqueStrings($labels);
    }

    /**
     * @param array<string|null> $values
     *
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        return array_values(array_unique(array_filter($values, static fn (?string $value): bool => null !== $value && '' !== $value)));
    }
}
