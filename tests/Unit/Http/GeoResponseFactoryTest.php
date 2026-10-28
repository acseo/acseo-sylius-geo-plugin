<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Http;

use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\GeoResponseCreationEvent;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class GeoResponseFactoryTest extends TestCase
{
    public function testItBuildsAPubliclyCacheableResponse(): void
    {
        $response = (new GeoResponseFactory(600))->create('# Body', 'text/markdown; charset=UTF-8');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('# Body', $response->getContent());
        self::assertSame('text/markdown; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame(\sprintf('"%s"', hash('sha256', '# Body')), $response->headers->get('ETag'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
        self::assertNull($response->getLastModified());
    }

    public function testItLetsListenersAlterBodyContentTypeAndHeaders(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GeoEvents::BEFORE_RESPONSE_CREATION, static function (GeoResponseCreationEvent $event): void {
            $event->setBody('altered');
            $event->setContentType('text/plain');
            $event->setHeader('X-Geo', 'yes');
        });

        $response = (new GeoResponseFactory(60, $dispatcher))->create('original', 'text/markdown');

        self::assertSame('altered', $response->getContent());
        self::assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        self::assertSame('yes', $response->headers->get('X-Geo'));
        self::assertSame(\sprintf('"%s"', hash('sha256', 'altered')), $response->headers->get('ETag'));
    }

    public function testItReplacesTheCacheControlSetByAListener(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GeoEvents::BEFORE_RESPONSE_CREATION, static function (GeoResponseCreationEvent $event): void {
            $event->setHeader('Cache-Control', 'no-store, private, max-age=0');
        });

        $response = (new GeoResponseFactory(600, $dispatcher))->create('# Body', 'text/plain');

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
    }

    public function testItAnswersNotModifiedWhenTheEtagMatches(): void
    {
        $etag = \sprintf('"%s"', hash('sha256', '# Body'));

        $response = $this->factoryFor(Request::create('/llms.txt', 'GET', server: ['HTTP_IF_NONE_MATCH' => $etag]))->create('# Body', 'text/plain');

        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame($etag, $response->headers->get('ETag'));
    }

    public function testItAnswersTheFullBodyWhenTheEtagDiffers(): void
    {
        $response = $this->factoryFor(Request::create('/llms.txt', 'GET', server: ['HTTP_IF_NONE_MATCH' => '"stale"']))->create('# Body', 'text/plain');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('# Body', $response->getContent());
    }

    private function factoryFor(Request $request): GeoResponseFactory
    {
        $stack = new RequestStack();
        $stack->push($request);

        return new GeoResponseFactory(60, null, $stack);
    }

    public function testItFlagsTheCurrentRequestAsAGeoResponse(): void
    {
        $request = Request::create('/llms.txt');

        $this->factoryFor($request)->create('# Body', 'text/plain');

        self::assertTrue($request->attributes->get(GeoResponseFactory::REQUEST_ATTRIBUTE));
    }

    public function testItBuildsAPrivateResponseForAnAuthenticatedUser(): void
    {
        $request = Request::create('/llms.txt');
        $stack = new RequestStack();
        $stack->push($request);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('shop@example.com', null), 'shop'));

        $response = (new GeoResponseFactory(600, null, $stack, $tokenStorage))->create('# Body', 'text/plain');

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
        self::assertNull($request->attributes->get(GeoResponseFactory::REQUEST_ATTRIBUTE));
    }

    public function testItBuildsAPublicResponseWithoutAToken(): void
    {
        $response = (new GeoResponseFactory(600, null, null, new TokenStorage()))->create('# Body', 'text/plain');

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('true', $response->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }
}
