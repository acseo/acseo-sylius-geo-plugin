<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Locale;

use ACSEO\SyliusGeoPlugin\Exception\DefaultLocaleMissingException;
use ACSEO\SyliusGeoPlugin\Exception\LocaleNotAvailableException;
use Sylius\Component\Core\Model\ChannelInterface;

final class ChannelLocaleResolver implements ChannelLocaleResolverInterface
{
    #[\Override]
    public function resolve(ChannelInterface $channel, ?string $requestedLocaleCode): string
    {
        if (null !== $requestedLocaleCode && '' !== trim($requestedLocaleCode)) {
            return $this->supports($channel, $requestedLocaleCode) ? $requestedLocaleCode : throw new LocaleNotAvailableException();
        }

        return $channel->getDefaultLocale()?->getCode() ?? throw new DefaultLocaleMissingException();
    }

    #[\Override]
    public function supports(ChannelInterface $channel, string $localeCode): bool
    {
        foreach ($channel->getLocales() as $locale) {
            if ($locale->getCode() === $localeCode) {
                return true;
            }
        }

        return false;
    }
}
