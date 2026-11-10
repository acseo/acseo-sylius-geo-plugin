<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\GeoResponseCreationEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class GeoResponseFactory
{
    public const string REQUEST_ATTRIBUTE = '_acseo_sylius_geo_plugin_response';

    public function __construct(
        private int $httpCacheMaxAge,
        private EventDispatcherInterface $eventDispatcher,
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function create(string $body, string $contentType): Response
    {
        $event = new GeoResponseCreationEvent($body, $contentType);
        $this->eventDispatcher->dispatch($event, GeoEvents::BEFORE_RESPONSE_CREATION);

        $response = new Response($event->getBody(), Response::HTTP_OK, [
            ...$event->getHeaders(),
            'Content-Type' => $event->getContentType(),
            'ETag' => \sprintf('"%s"', hash('sha256', $event->getBody())),
        ]);

        $this->applyCacheHeaders($response);

        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, !$this->isAuthenticated());
            $response->isNotModified($request);
        }

        return $response;
    }

    private function applyCacheHeaders(Response $response): void
    {
        $response->headers->remove('Cache-Control');
        $response->setMaxAge($this->httpCacheMaxAge);
        $response->headers->addCacheControlDirective('must-revalidate');

        if ($this->isAuthenticated()) {
            $response->setPrivate();

            return;
        }

        $response->setPublic();
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
    }

    private function isAuthenticated(): bool
    {
        return $this->tokenStorage->getToken()?->getUser() instanceof UserInterface;
    }
}
