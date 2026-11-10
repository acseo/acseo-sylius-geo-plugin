<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\Http\MarkdownResponderInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class ProductMarkdownController
{
    public function __construct(
        private MarkdownResponderInterface $productResponder,
        private CurrentChannelProvider $channels,
    ) {
    }

    public function __invoke(string $_locale, string $slug): Response
    {
        return $this->productResponder->respond($this->channels->current(), $_locale, $slug);
    }
}
