<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\EventSubscriber;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\EventSubscriber\MarkdownNegotiationSubscriber;
use ACSEO\SyliusGeoPlugin\Exception\LocaleNotAvailableException;
use ACSEO\SyliusGeoPlugin\Http\Endpoint;
use ACSEO\SyliusGeoPlugin\Http\EndpointSettings;
use ACSEO\SyliusGeoPlugin\Http\MarkdownAcceptNegotiator;
use ACSEO\SyliusGeoPlugin\Http\MarkdownResponderInterface;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;

final class MarkdownNegotiationSubscriberTest extends TestCase
{
    private const string ROUTE = 'sylius_shop_product_show';

    #[TestDox('An explicit Markdown preference is answered with the Markdown representation and Vary: Accept')]
    public function test_markdown_is_served_when_preferred(): void
    {
        $event = $this->requestEvent('text/markdown');

        $this->subscriber()->onKernelRequest($event);

        self::assertSame('# mug', $event->getResponse()?->getContent());
        self::assertSame(['Accept'], $event->getResponse()->getVary());
    }

    #[TestDox('Only unsupported media types are answered with 406 and Vary: Accept')]
    public function test_unsupported_media_types_are_not_acceptable(): void
    {
        $event = $this->requestEvent('application/json');

        $this->subscriber()->onKernelRequest($event);

        self::assertSame(406, $event->getResponse()?->getStatusCode());
        self::assertSame(['Accept'], $event->getResponse()->getVary());
    }

    #[TestDox('HTML, wildcards and a missing Accept header leave the Sylius page untouched')]
    public function test_html_requests_are_not_intercepted(): void
    {
        foreach (['text/html', '*/*', 'text/markdown;q=0.2, text/html', null] as $accept) {
            $event = $this->requestEvent($accept);

            $this->subscriber()->onKernelRequest($event);

            self::assertNull($event->getResponse(), (string) $accept);
        }
    }

    #[TestDox('Other routes, other methods and a disabled endpoint are ignored')]
    public function test_other_requests_are_ignored(): void
    {
        $this->subscriber()->onKernelRequest($event = $this->requestEvent('text/markdown', 'sylius_shop_homepage'));
        self::assertNull($event->getResponse());

        $this->subscriber()->onKernelRequest($event = $this->requestEvent('text/markdown', self::ROUTE, 'POST'));
        self::assertNull($event->getResponse());

        $this->subscriber(new EndpointSettings(productMarkdown: false))->onKernelRequest($event = $this->requestEvent('text/markdown'));
        self::assertNull($event->getResponse());
    }

    #[TestDox('A locale that is not enabled on the channel answers 404')]
    public function test_unsupported_locale_is_not_found(): void
    {
        $this->expectException(LocaleNotAvailableException::class);

        $this->subscriber()->onKernelRequest($this->requestEvent('text/markdown', self::ROUTE, 'GET', 'de_DE'));
    }

    #[TestDox('The Sylius page of the negotiated route varies on Accept')]
    public function test_html_response_varies_on_accept(): void
    {
        $request = new Request(attributes: ['_route' => self::ROUTE]);
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response('html'));

        $this->subscriber()->onKernelResponse($event);

        self::assertSame(['Accept'], $event->getResponse()->getVary());
    }

    #[TestDox('A response that already varies on Accept does not repeat the header')]
    public function test_vary_accept_is_not_duplicated(): void
    {
        $request = new Request(attributes: ['_route' => self::ROUTE]);
        $response = (new Response('markdown'))->setVary('Accept');
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber()->onKernelResponse($event);

        self::assertSame(['Accept'], $event->getResponse()->getVary());
        self::assertSame(['Accept'], $event->getResponse()->headers->all('Vary'));
    }

    private function requestEvent(?string $accept, string $route = self::ROUTE, string $method = 'GET', string $locale = 'en_US'): RequestEvent
    {
        $request = new Request(attributes: ['_route' => $route, '_locale' => $locale, 'slug' => 'mug']);
        $request->setMethod($method);
        if (null !== $accept) {
            $request->headers->set('Accept', $accept);
        }

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function subscriber(?EndpointSettings $settings = null): MarkdownNegotiationSubscriber
    {
        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn(CatalogFactory::channel());

        $responder = new class() implements MarkdownResponderInterface {
            public function respond(ChannelInterface $channel, string $localeCode, string $slug): Response
            {
                return new Response('# ' . $slug);
            }
        };

        return new MarkdownNegotiationSubscriber(
            self::ROUTE,
            Endpoint::ProductMarkdown,
            $settings ?? new EndpointSettings(),
            new MarkdownAcceptNegotiator(),
            $responder,
            new CurrentChannelProvider($channelContext),
            new ChannelLocaleResolver(),
        );
    }
}
