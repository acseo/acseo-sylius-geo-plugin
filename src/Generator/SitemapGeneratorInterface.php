<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use Sylius\Component\Core\Model\ChannelInterface;

interface SitemapGeneratorInterface
{
    public function generate(ChannelInterface $channel, string $localeCode): string;
}
