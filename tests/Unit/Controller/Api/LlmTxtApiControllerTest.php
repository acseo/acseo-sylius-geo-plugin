<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Controller\Api;

use ACSEO\SyliusGeoPlugin\Controller\Api\LlmTxtApiController;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LlmTxtApiControllerTest extends TestCase
{
    private LlmTxtGeneratorInterface&MockObject $generator;

    private ChannelContextInterface&MockObject $channelContext;

    private LlmTxtApiController $controller;

    protected function setUp(): void
    {
        $this->generator = $this->createMock(LlmTxtGeneratorInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->controller = new LlmTxtApiController(
            $this->generator,
            $this->channelContext,
            new ChannelLocaleResolver(),
            new GeoResponseFactory(3600),
        );
    }

    public function testItUsesDefaultLocaleWhenQueryMissing(): void
    {
        $channel = $this->createChannel('en_US');
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->generator->method('generate')->with($channel, 'en_US')->willReturn("# Shop\n");

        $response = ($this->controller)(Request::create('/api/v2/shop/geo/llm-txt'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame("# Shop\n", $response->getContent());
    }

    public function testItUsesLocaleQueryParameter(): void
    {
        $channel = $this->createChannel('en_US', 'fr_FR');
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->generator->expects(self::once())->method('generate')->with($channel, 'fr_FR')->willReturn("# Boutique\n");

        $response = ($this->controller)(Request::create('/api/v2/shop/geo/llm-txt', 'GET', ['locale' => 'fr_FR']));

        self::assertSame("# Boutique\n", $response->getContent());
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
