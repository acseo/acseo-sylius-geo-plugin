<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Channel;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Webmozart\Assert\Assert;

final readonly class CurrentChannelProvider
{
    public function __construct(private ChannelContextInterface $channelContext)
    {
    }

    public function current(): ChannelInterface
    {
        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        return $channel;
    }
}
