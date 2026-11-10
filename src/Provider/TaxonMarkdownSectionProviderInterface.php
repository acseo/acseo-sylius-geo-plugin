<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Provider;

use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('acseo_sylius_geo_plugin.taxon_markdown_section_provider')]
interface TaxonMarkdownSectionProviderInterface
{
    public function supports(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): bool;

    /** @return list<MarkdownSection> */
    public function provide(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): array;
}
