<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

interface TaxonMarkdownGeneratorInterface
{
    public function generate(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): string;
}
