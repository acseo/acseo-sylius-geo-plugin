<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('acseo_sylius_geo_plugin.cms_page_provider')]
interface CmsPageProviderInterface
{
    /** @return list<CmsPageLink> */
    public function findEnabledPages(ChannelInterface $channel, string $localeCode): array;
}
