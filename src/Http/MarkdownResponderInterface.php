<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Response;

interface MarkdownResponderInterface
{
    public function respond(ChannelInterface $channel, string $localeCode, string $slug): Response;
}
