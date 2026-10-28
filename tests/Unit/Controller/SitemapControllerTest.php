<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Controller;

use ACSEO\SyliusGeoPlugin\Controller\SitemapController;
use ACSEO\SyliusGeoPlugin\Generator\SitemapGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SitemapControllerTest extends TestCase
{
    private SitemapGeneratorInterface&MockObject $generator;

    private ChannelContextInterface&MockObject $channelContext;

    protected function setUp(): void
    {
        $this->generator = $this->createMock(SitemapGeneratorInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
    }

    public function testItReturnsXmlSitemapForRequestedLocale(): void
    {
        $channel = $this->createChannel('en_US', 'fr_FR');
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->generator->method('generate')->with($channel, 'fr_FR')->willReturn("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset/>");

        $response = ($this->createController())('fr_FR');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/xml; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('<urlset/>', (string) $response->getContent());
        self::assertTrue($response->headers->getCacheControlDirective('public'));
    }

    public function testItFallsBackToChannelDefaultLocale(): void
    {
        $channel = $this->createChannel('en_US', 'fr_FR');
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->generator->method('generate')->with($channel, 'en_US')->willReturn("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset/>");

        $response = ($this->createController())();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testItReturns404ForUnsupportedLocale(): void
    {
        $this->channelContext->method('getChannel')->willReturn($this->createChannel('en_US'));

        $this->expectException(NotFoundHttpException::class);

        ($this->createController())('fr_FR');
    }

    public function testItReturns404WhenRouteIsDisabledByConfiguration(): void
    {
        $controller = new SitemapController(
            $this->generator,
            $this->channelContext,
            new ChannelLocaleResolver(),
            new GeoResponseFactory(3600),
            false,
        );

        $this->expectException(NotFoundHttpException::class);

        $controller('fr_FR');
    }

    private function createController(): SitemapController
    {
        return new SitemapController(
            $this->generator,
            $this->channelContext,
            new ChannelLocaleResolver(),
            new GeoResponseFactory(3600),
        );
    }

    private function createChannel(string ...$localeCodes): ChannelInterface
    {
        $locales = [];
        foreach ($localeCodes as $localeCode) {
            $locale = $this->createMock(LocaleInterface::class);
            $locale->method('getCode')->willReturn($localeCode);
            $locales[] = $locale;
        }

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getLocales')->willReturn(new ArrayCollection($locales));
        $channel->method('getDefaultLocale')->willReturn($locales[0]);

        return $channel;
    }
}
