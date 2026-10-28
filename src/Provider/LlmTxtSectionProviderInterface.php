<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Provider;

use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use Sylius\Component\Core\Model\ChannelInterface;

interface LlmTxtSectionProviderInterface
{
    public function supports(ChannelInterface $channel, string $localeCode): bool;

    /** @return list<MarkdownSection> */
    public function provide(ChannelInterface $channel, string $localeCode): array;
}
