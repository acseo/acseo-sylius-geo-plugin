<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Locale;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ChannelLocaleResolver implements ChannelLocaleResolverInterface
{
    public function resolve(ChannelInterface $channel, ?string $requestedLocaleCode): string
    {
        if (null !== $requestedLocaleCode && '' !== trim($requestedLocaleCode)) {
            if (!$this->supports($channel, $requestedLocaleCode)) {
                throw new NotFoundHttpException(\sprintf('Locale "%s" is not available on this channel.', $requestedLocaleCode));
            }

            return $requestedLocaleCode;
        }

        $defaultLocale = $channel->getDefaultLocale();
        if (!$defaultLocale instanceof LocaleInterface || null === $defaultLocale->getCode()) {
            throw new NotFoundHttpException('Channel has no default locale.');
        }

        return $defaultLocale->getCode();
    }

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
