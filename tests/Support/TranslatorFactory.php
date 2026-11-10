<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Support;

use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

final class TranslatorFactory
{
    public static function create(): Translator
    {
        $translations = \dirname(__DIR__, 2) . '/translations';
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', $translations . '/messages.en.yaml', 'en');
        $translator->addResource('yaml', $translations . '/messages.fr.yaml', 'fr');
        $translator->addResource('yaml', $translations . '/acseo_sylius_geo_plugin_output.en.yaml', 'en', CrawlerTextTranslator::DOMAIN);

        return $translator;
    }

    public static function crawlerTexts(): CrawlerTextTranslator
    {
        return new CrawlerTextTranslator(self::create());
    }
}
