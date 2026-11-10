<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Http;

use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\GeoResponseCreationEvent;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class GeoResponseFactoryTest extends TestCase
{
    #[TestDox('An anonymous response is public, revalidated and carries a SHA-256 ETag')]
    public function test_anonymous_response_is_public(): void
    {
        $response = $this->factory()->create('body', 'text/plain; charset=UTF-8');

        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame(\sprintf('"%s"', hash('sha256', 'body')), $response->headers->get('ETag'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
    }

    #[TestDox('An authenticated response is private')]
    public function test_authenticated_response_is_private(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new InMemoryUser('customer', null), 'shop'));

        $response = $this->factory($storage)->create('body', 'text/plain');

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    #[TestDox('A matching If-None-Match header is answered with 304')]
    public function test_conditional_request_is_not_modified(): void
    {
        $request = Request::create('/en_US/llms.txt');
        $request->headers->set('If-None-Match', \sprintf('"%s"', hash('sha256', 'body')));

        $response = $this->factory(null, $request)->create('body', 'text/plain');

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    #[TestDox('Cookies are flagged for removal on anonymous requests only')]
    public function test_anonymous_requests_are_flagged_for_cookie_removal(): void
    {
        $request = Request::create('/en_US/llms.txt');
        $this->factory(null, $request)->create('body', 'text/plain');

        self::assertTrue($request->attributes->get(GeoResponseFactory::REQUEST_ATTRIBUTE));

        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new InMemoryUser('customer', null), 'shop'));
        $authenticatedRequest = Request::create('/en_US/llms.txt');
        $this->factory($storage, $authenticatedRequest)->create('body', 'text/plain');

        self::assertFalse($authenticatedRequest->attributes->get(GeoResponseFactory::REQUEST_ATTRIBUTE));
    }

    #[TestDox('A listener can change the body, content type and headers but not the cache policy')]
    public function test_listeners_can_adjust_the_response(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GeoEvents::BEFORE_RESPONSE_CREATION, static function (GeoResponseCreationEvent $event): void {
            $event->setBody('changed');
            $event->setContentType('text/x-custom');
            $event->setHeader('X-Geo', 'yes');
            $event->setHeader('Cache-Control', 'no-store');
        });

        $response = $this->factory(null, null, $dispatcher)->create('body', 'text/plain');

        self::assertSame('changed', $response->getContent());
        self::assertSame('text/x-custom', $response->headers->get('Content-Type'));
        self::assertSame('yes', $response->headers->get('X-Geo'));
        self::assertSame(\sprintf('"%s"', hash('sha256', 'changed')), $response->headers->get('ETag'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
    }

    private function factory(?TokenStorage $storage = null, ?Request $request = null, ?EventDispatcher $dispatcher = null): GeoResponseFactory
    {
        $requestStack = new RequestStack();
        if ($request instanceof Request) {
            $requestStack->push($request);
        }

        return new GeoResponseFactory(600, $dispatcher ?? new EventDispatcher(), $requestStack, $storage ?? new TokenStorage());
    }
}
