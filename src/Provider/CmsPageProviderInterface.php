<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;

interface CmsPageProviderInterface
{
    /** @return list<CmsPageLink> */
    public function findEnabledPages(ChannelInterface $channel, string $localeCode): array;
}
