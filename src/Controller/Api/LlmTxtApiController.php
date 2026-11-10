<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller\Api;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Http\RequestedLocaleReader;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class LlmTxtApiController
{
    public function __construct(
        private LlmTxtGeneratorInterface $generator,
        private CurrentChannelProvider $channels,
        private ChannelLocaleResolverInterface $localeResolver,
        private RequestedLocaleReader $requestedLocale,
        private GeoResponseFactory $responseFactory,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $channel = $this->channels->current();
        $localeCode = $this->localeResolver->resolve($channel, $this->requestedLocale->read($request));

        return $this->responseFactory->create($this->generator->generate($channel, $localeCode), 'text/plain; charset=UTF-8');
    }
}
