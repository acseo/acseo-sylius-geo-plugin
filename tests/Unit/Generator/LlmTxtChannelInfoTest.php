<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Generator;

use ACSEO\SyliusGeoPlugin\Generator\LlmTxtChannelInfo;
use ACSEO\SyliusGeoPlugin\Model\LlmTxtOptions;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Addressing\Model\Country;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;

final class LlmTxtChannelInfoTest extends TestCase
{
    public function testItFallsBackToTheChannelData(): void
    {
        $info = new LlmTxtChannelInfo(new LlmTxtOptions(), new GeoUrlBuilder());
        $channel = $this->channel();

        self::assertSame('Fashion Web', $info->shopName($channel));
        self::assertSame('https://fashion.example', $info->baseUrl($channel));
        self::assertSame(['EUR', 'USD'], $info->currencies($channel));
        self::assertSame(['en_US', 'fr_FR'], $info->locales($channel));
        self::assertSame(['France'], $info->servedCountries($channel));
    }

    public function testConfigurationOverridesTheChannelData(): void
    {
        $info = new LlmTxtChannelInfo(new LlmTxtOptions(
            shopName: ' Configured ',
            baseUrl: 'https://configured.example/',
            currencies: ['CHF'],
            locales: ['de_CH'],
            servedCountries: ['Switzerland'],
        ), new GeoUrlBuilder());
        $channel = $this->channel();

        self::assertSame('Configured', $info->shopName($channel));
        self::assertSame('https://configured.example', $info->baseUrl($channel));
        self::assertSame(['CHF'], $info->currencies($channel));
        self::assertSame(['de_CH'], $info->locales($channel));
        self::assertSame(['en_US', 'fr_FR'], $info->channelLocales($channel));
        self::assertSame(['Switzerland'], $info->servedCountries($channel));
    }

    public function testShopNameFallsBackToTheChannelCodeAndBaseUrlToNothing(): void
    {
        $info = new LlmTxtChannelInfo(new LlmTxtOptions(), new GeoUrlBuilder());
        $channel = new Channel();
        $channel->setCode('WEB');

        self::assertSame('WEB', $info->shopName($channel));
        self::assertNull($info->baseUrl($channel));
        self::assertSame([], $info->currencies($channel));
    }

    public function testCurrenciesFallBackToTheBaseCurrency(): void
    {
        $info = new LlmTxtChannelInfo(new LlmTxtOptions(), new GeoUrlBuilder());
        $channel = new Channel();
        $channel->setBaseCurrency($this->currency('GBP'));

        self::assertSame(['GBP'], $info->currencies($channel));
    }

    private function channel(): Channel
    {
        $channel = new Channel();
        $channel->setCode('FASHION_WEB');
        $channel->setName('Fashion Web');
        $channel->setHostname('fashion.example');
        $channel->addCurrency($this->currency('EUR'));
        $channel->addCurrency($this->currency('USD'));
        foreach (['en_US', 'fr_FR'] as $code) {
            $locale = new Locale();
            $locale->setCode($code);
            $channel->addLocale($locale);
        }
        $country = new Country();
        $country->setCode('FR');
        $country->enable();
        $channel->addCountry($country);

        return $channel;
    }

    private function currency(string $code): Currency
    {
        $currency = new Currency();
        $currency->setCode($code);

        return $currency;
    }
}
