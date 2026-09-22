<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller;

use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

final class LlmTxtController
{
    public function __construct(
        private readonly LlmTxtGeneratorInterface $llmTxtGenerator,
        private readonly ChannelContextInterface $channelContext,
        private readonly ChannelLocaleResolverInterface $localeResolver,
        private readonly GeoResponseFactory $geoResponseFactory,
        private readonly bool $enabled = true,
    ) {
    }

    public function __invoke(string $_locale): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException('GEO llm.txt endpoint is disabled.');
        }

        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        if (!$this->localeResolver->supports($channel, $_locale)) {
            throw new NotFoundHttpException(\sprintf('Locale "%s" is not available on this channel.', $_locale));
        }

        $body = $this->llmTxtGenerator->generate($channel, $_locale);

        return $this->geoResponseFactory->create($body, 'text/plain; charset=UTF-8');
    }
}
