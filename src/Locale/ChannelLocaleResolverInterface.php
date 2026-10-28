<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Locale;

use Sylius\Component\Core\Model\ChannelInterface;

interface ChannelLocaleResolverInterface
{
    public function resolve(ChannelInterface $channel, ?string $requestedLocaleCode): string;

    public function supports(ChannelInterface $channel, string $localeCode): bool;
}
