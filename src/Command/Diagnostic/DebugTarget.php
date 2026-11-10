<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command\Diagnostic;

use Sylius\Component\Core\Model\ChannelInterface;

final readonly class DebugTarget
{
    public function __construct(
        public ChannelInterface $channel,
        public string $localeCode,
    ) {
    }
}
