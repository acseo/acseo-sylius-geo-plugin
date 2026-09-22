<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller\Api;

use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

final class LlmTxtApiController
{
    public function __construct(
        private readonly LlmTxtGeneratorInterface $llmTxtGenerator,
        private readonly ChannelContextInterface $channelContext,
        private readonly ChannelLocaleResolverInterface $localeResolver,
        private readonly GeoResponseFactory $geoResponseFactory,
        private readonly bool $enabled = true,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException('GEO API endpoints are disabled.');
        }

        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $localeCode = $this->localeResolver->resolve(
            $channel,
            $request->query->getString('locale') !== '' ? $request->query->getString('locale') : null,
        );

        $body = $this->llmTxtGenerator->generate($channel, $localeCode);

        return $this->geoResponseFactory->create($body, 'text/plain; charset=UTF-8');
    }
}
