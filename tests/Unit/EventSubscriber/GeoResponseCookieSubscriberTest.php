<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\EventSubscriber;

use ACSEO\SyliusGeoPlugin\EventSubscriber\GeoResponseCookieSubscriber;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class GeoResponseCookieSubscriberTest extends TestCase
{
    public function testItRemovesCookiesFromGeoResponses(): void
    {
        $request = new Request();
        $request->attributes->set(GeoResponseFactory::REQUEST_ATTRIBUTE, true);
        $response = $this->responseWithCookies();

        $this->dispatch($request, $response, HttpKernelInterface::MAIN_REQUEST);

        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($response->headers->has('Set-Cookie'));
    }

    public function testItKeepsCookiesOnOtherResponses(): void
    {
        $response = $this->responseWithCookies();

        $this->dispatch(new Request(), $response, HttpKernelInterface::MAIN_REQUEST);

        self::assertCount(2, $response->headers->getCookies());
    }

    public function testItIgnoresSubRequests(): void
    {
        $request = new Request();
        $request->attributes->set(GeoResponseFactory::REQUEST_ATTRIBUTE, true);
        $response = $this->responseWithCookies();

        $this->dispatch($request, $response, HttpKernelInterface::SUB_REQUEST);

        self::assertCount(2, $response->headers->getCookies());
    }

    public function testItRunsAfterTheListenersThatAddCookies(): void
    {
        $listeners = GeoResponseCookieSubscriber::getSubscribedEvents();

        self::assertLessThan(-1000, $listeners['kernel.response'][1]);
    }

    private function responseWithCookies(): Response
    {
        $response = new Response('body');
        $response->headers->setCookie(Cookie::create('shop_deauth_profile_token', 'abc'));
        $response->headers->setCookie(Cookie::create('shop_auth_profile_token', 'deleted', 1));

        return $response;
    }

    private function dispatch(Request $request, Response $response, int $type): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        (new GeoResponseCookieSubscriber())->onKernelResponse(new ResponseEvent($kernel, $request, $type, $response));
    }
}
