<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Event;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

final class ProductMarkdownGenerationEvent extends AbstractMarkdownGenerationEvent
{
    public function __construct(
        private readonly ProductInterface $product,
        ChannelInterface $channel,
        string $localeCode,
    ) {
        parent::__construct($channel, $localeCode);
    }

    public function getProduct(): ProductInterface
    {
        return $this->product;
    }
}
