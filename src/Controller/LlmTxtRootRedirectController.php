<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller;

use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

final class LlmTxtRootRedirectController
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly GeoUrlBuilderInterface $urlBuilder,
        private readonly bool $enabled = true,
    ) {
    }

    public function __invoke(): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException('GEO llm.txt endpoint is disabled.');
        }

        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $defaultLocale = $channel->getDefaultLocale();
        if (!$defaultLocale instanceof LocaleInterface || null === $defaultLocale->getCode()) {
            throw new NotFoundHttpException('Channel has no default locale.');
        }

        return new RedirectResponse(
            $this->urlBuilder->llmTxtPath($defaultLocale->getCode()),
            Response::HTTP_FOUND,
        );
    }
}
