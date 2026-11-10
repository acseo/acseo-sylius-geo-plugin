<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Translation;

use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CrawlerTextTranslator
{
    public const string DOMAIN = 'acseo_sylius_geo_plugin_output';

    public const string LOCALE = 'en';

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /** @param array<string, string> $parameters */
    public function translate(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters, self::DOMAIN, self::LOCALE);
    }
}
