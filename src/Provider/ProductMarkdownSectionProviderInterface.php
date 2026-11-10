<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Provider;

use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('acseo_sylius_geo_plugin.product_markdown_section_provider')]
interface ProductMarkdownSectionProviderInterface
{
    public function supports(ProductInterface $product, ChannelInterface $channel, string $localeCode): bool;

    /** @return list<MarkdownSection> */
    public function provide(ProductInterface $product, ChannelInterface $channel, string $localeCode): array;
}
