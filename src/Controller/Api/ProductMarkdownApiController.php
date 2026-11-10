<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller\Api;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\Http\MarkdownResponderInterface;
use ACSEO\SyliusGeoPlugin\Http\RequestedLocaleReader;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ProductMarkdownApiController
{
    public function __construct(
        private MarkdownResponderInterface $productResponder,
        private CurrentChannelProvider $channels,
        private ChannelLocaleResolverInterface $localeResolver,
        private RequestedLocaleReader $requestedLocale,
    ) {
    }

    public function __invoke(Request $request, string $slug): Response
    {
        $channel = $this->channels->current();

        return $this->productResponder->respond(
            $channel,
            $this->localeResolver->resolve($channel, $this->requestedLocale->read($request)),
            $slug,
        );
    }
}
