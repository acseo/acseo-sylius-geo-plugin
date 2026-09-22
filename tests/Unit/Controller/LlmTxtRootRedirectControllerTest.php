<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Controller;

use ACSEO\SyliusGeoPlugin\Controller\LlmTxtRootRedirectController;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LlmTxtRootRedirectControllerTest extends TestCase
{
    public function testItRedirectsToDefaultLocaleLlmTxt(): void
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn('en_US');

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getDefaultLocale')->willReturn($locale);

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $controller = new LlmTxtRootRedirectController($channelContext, new GeoUrlBuilder());
        $response = $controller();

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/en_US/llm.txt', $response->headers->get('Location'));
    }

    public function testItReturns404WithoutDefaultLocale(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getDefaultLocale')->willReturn(null);

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $controller = new LlmTxtRootRedirectController($channelContext, new GeoUrlBuilder());

        $this->expectException(NotFoundHttpException::class);

        $controller();
    }
}
