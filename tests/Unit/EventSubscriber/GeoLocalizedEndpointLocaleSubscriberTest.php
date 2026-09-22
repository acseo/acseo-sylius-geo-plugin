<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Tests\Unit\EventSubscriber;

use ACSEO\SyliusGeoPlugin\EventSubscriber\GeoLocalizedEndpointLocaleSubscriber;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class GeoLocalizedEndpointLocaleSubscriberTest extends TestCase
{
    private ChannelContextInterface $channelContext;

    private GeoLocalizedEndpointLocaleSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->subscriber = new GeoLocalizedEndpointLocaleSubscriber(
            $this->channelContext,
            new ChannelLocaleResolver(),
            'llm.txt',
            'geo',
            'products',
        );
    }

    public function testItDoesNothingForSupportedGeoLocale(): void
    {
        $this->channelContext->method('getChannel')->willReturn($this->createChannel('en_US'));

        $event = $this->createRequestEvent('/en_US/llm.txt');

        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItReturns404ForUnsupportedLocalizedGeoEndpointBeforeSyliusRedirects(): void
    {
        $this->channelContext->method('getChannel')->willReturn($this->createChannel('en_US'));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Locale "xx_XX" is not available on this channel.');

        $this->subscriber->onKernelRequest($this->createRequestEvent('/xx_XX/geo/products/cap.md'));
    }

    public function testItIgnoresNonGeoLocalizedPaths(): void
    {
        $this->channelContext->expects(self::never())->method('getChannel');

        $event = $this->createRequestEvent('/xx_XX/products/cap');

        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    private function createChannel(string ...$localeCodes): ChannelInterface
    {
        $locales = array_map(function (string $localeCode): LocaleInterface {
            $locale = $this->createMock(LocaleInterface::class);
            $locale->method('getCode')->willReturn($localeCode);

            return $locale;
        }, $localeCodes);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getLocales')->willReturn(new ArrayCollection($locales));

        return $channel;
    }

    private function createRequestEvent(string $path): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
