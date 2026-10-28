<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller;

use ACSEO\SyliusGeoPlugin\Generator\SitemapGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

final class SitemapController
{
    public function __construct(
        private readonly SitemapGeneratorInterface $sitemapGenerator,
        private readonly ChannelContextInterface $channelContext,
        private readonly ChannelLocaleResolverInterface $localeResolver,
        private readonly GeoResponseFactory $geoResponseFactory,
        private readonly bool $enabled = true,
    ) {
    }

    public function __invoke(?string $_locale = null): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException('GEO sitemap endpoint is disabled.');
        }

        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $localeCode = $this->localeResolver->resolve($channel, $_locale);
        $body = $this->sitemapGenerator->generate($channel, $localeCode);

        return $this->geoResponseFactory->create($body, 'application/xml; charset=UTF-8');
    }
}
