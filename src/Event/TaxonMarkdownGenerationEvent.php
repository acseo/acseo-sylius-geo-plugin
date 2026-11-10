<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Event;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final class TaxonMarkdownGenerationEvent extends AbstractMarkdownGenerationEvent
{
    public function __construct(
        private readonly TaxonInterface $taxon,
        ChannelInterface $channel,
        string $localeCode,
    ) {
        parent::__construct($channel, $localeCode);
    }

    public function getTaxon(): TaxonInterface
    {
        return $this->taxon;
    }
}
