<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Controller;

use ACSEO\SyliusGeoPlugin\Controller\LlmTxtController;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\GeoResponseCreationEvent;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LlmTxtControllerTest extends TestCase
{
    private LlmTxtGeneratorInterface&MockObject $generator;

    private ChannelContextInterface&MockObject $channelContext;

    private LlmTxtController $controller;

    protected function setUp(): void
    {
        $this->generator = $this->createMock(LlmTxtGeneratorInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->controller = new LlmTxtController(
            $this->generator,
            $this->channelContext,
            new ChannelLocaleResolver(),
            new GeoResponseFactory(3600),
        );
    }

    public function testItReturnsPlainTextWithCacheHeaders(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->generator->method('generate')->with($channel, 'en_US')->willReturn("# Shop\n");

        $response = ($this->controller)('en_US');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame("# Shop\n", $response->getContent());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertTrue($response->headers->getCacheControlDirective('public'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
        self::assertSame('"' . hash('sha256', "# Shop\n") . '"', $response->headers->get('ETag'));
        self::assertNull($response->getLastModified());
    }

    public function testItReturns404ForUnsupportedLocale(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $this->channelContext->method('getChannel')->willReturn($channel);

        $this->expectException(NotFoundHttpException::class);

        ($this->controller)('fr_FR');
    }

    public function testItDispatchesBeforeResponseCreationEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GeoEvents::BEFORE_RESPONSE_CREATION, static function (GeoResponseCreationEvent $event): void {
            $event->setBody($event->getBody() . "\n# Extra");
            $event->setHeader('X-GEO-Custom', 'yes');
        });
        $controller = new LlmTxtController(
            $this->generator,
            $this->channelContext,
            new ChannelLocaleResolver(),
            new GeoResponseFactory(3600, $dispatcher),
        );
        $channel = $this->createChannelWithLocale('en_US');
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->generator->method('generate')->with($channel, 'en_US')->willReturn("# Shop\n");

        $response = ($controller)('en_US');

        self::assertStringContainsString('# Extra', (string) $response->getContent());
        self::assertSame('yes', $response->headers->get('X-GEO-Custom'));
    }

    public function testItReturns404WhenRouteIsDisabledByConfiguration(): void
    {
        $controller = new LlmTxtController(
            $this->generator,
            $this->channelContext,
            new ChannelLocaleResolver(),
            new GeoResponseFactory(3600),
            false,
        );

        $this->expectException(NotFoundHttpException::class);

        $controller('en_US');
    }

    private function createChannelWithLocale(string $localeCode): ChannelInterface
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn($localeCode);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getLocales')->willReturn(new ArrayCollection([$locale]));

        return $channel;
    }
}
