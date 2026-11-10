<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\EventSubscriber;

use ACSEO\SyliusGeoPlugin\EventSubscriber\GeoResponseCookieSubscriber;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class GeoResponseCookieSubscriberTest extends TestCase
{
    #[TestDox('Cookies are removed from an anonymous GEO response')]
    public function test_cookies_are_removed_from_anonymous_geo_responses(): void
    {
        $event = $this->event(true);

        (new GeoResponseCookieSubscriber())->onKernelResponse($event);

        self::assertSame([], $event->getResponse()->headers->getCookies());
    }

    #[TestDox('Cookies of other responses are kept')]
    public function test_cookies_of_other_responses_are_kept(): void
    {
        $event = $this->event(null);

        (new GeoResponseCookieSubscriber())->onKernelResponse($event);

        self::assertCount(1, $event->getResponse()->headers->getCookies());
    }

    private function event(?bool $flag): ResponseEvent
    {
        $request = new Request();
        if (null !== $flag) {
            $request->attributes->set(GeoResponseFactory::REQUEST_ATTRIBUTE, $flag);
        }

        $response = new Response();
        $response->headers->setCookie(Cookie::create('PHPSESSID', 'abc'));

        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
