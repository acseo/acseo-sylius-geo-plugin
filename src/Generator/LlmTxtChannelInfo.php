<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use ACSEO\SyliusGeoPlugin\Model\LlmTxtOptions;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;

final class LlmTxtChannelInfo
{
    public function __construct(
        private readonly LlmTxtOptions $options,
        private readonly GeoUrlBuilderInterface $urlBuilder,
    ) {
    }

    public function shopName(ChannelInterface $channel): string
    {
        $configured = trim((string) $this->options->shopName);
        if ('' !== $configured) {
            return $configured;
        }

        $name = trim((string) $channel->getName());

        return '' === $name ? (string) $channel->getCode() : $name;
    }

    public function baseUrl(ChannelInterface $channel): ?string
    {
        $configured = trim((string) $this->options->baseUrl);
        if ('' !== $configured) {
            return rtrim($configured, '/');
        }

        return $this->urlBuilder->baseUrl($channel);
    }

    /** @return list<string> */
    public function currencies(ChannelInterface $channel): array
    {
        if ([] !== $this->options->currencies) {
            return $this->options->currencies;
        }

        $codes = [];
        foreach ($channel->getCurrencies() as $currency) {
            if (null !== $currency->getCode()) {
                $codes[] = $currency->getCode();
            }
        }

        if ([] === $codes) {
            $baseCurrency = $channel->getBaseCurrency();
            if ($baseCurrency instanceof CurrencyInterface && null !== $baseCurrency->getCode()) {
                $codes[] = $baseCurrency->getCode();
            }
        }

        return array_values(array_unique($codes));
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
            if (null !== $locale->getCode()) {
                $codes[] = $locale->getCode();
            }
        }

        return array_values(array_unique($codes));
    }

    /** @return list<string> */
    public function servedCountries(ChannelInterface $channel): array
    {
        if ([] !== $this->options->servedCountries) {
            return $this->options->servedCountries;
        }

        $countries = [];
        foreach ($channel->getEnabledCountries() as $country) {
            $label = trim((string) ($country->getName() ?? $country->getCode()));
            if ('' !== $label) {
                $countries[] = $label;
            }
        }

        return array_values(array_unique($countries));
    }
}
