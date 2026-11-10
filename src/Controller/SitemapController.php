<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\Generator\SitemapGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class SitemapController
{
    public function __construct(
        private SitemapGeneratorInterface $generator,
        private CurrentChannelProvider $channels,
        private ChannelLocaleResolverInterface $localeResolver,
        private GeoResponseFactory $responseFactory,
    ) {
    }

    public function __invoke(?string $_locale = null): Response
    {
        $channel = $this->channels->current();

        return $this->responseFactory->create(
            $this->generator->generate($channel, $this->localeResolver->resolve($channel, $_locale)),
            'application/xml; charset=UTF-8',
        );
    }
}
