<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class LlmTxtRootRedirectController
{
    public function __construct(
        private CurrentChannelProvider $channels,
        private ChannelLocaleResolverInterface $localeResolver,
        private GeoUrlBuilderInterface $urlBuilder,
    ) {
    }

    public function __invoke(): Response
    {
        $localeCode = $this->localeResolver->resolve($this->channels->current(), null);

        return new RedirectResponse($this->urlBuilder->llmTxtPath($localeCode), Response::HTTP_FOUND);
    }
}
