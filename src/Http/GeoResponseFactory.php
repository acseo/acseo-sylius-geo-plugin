<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\GeoResponseCreationEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class GeoResponseFactory
{
    public const REQUEST_ATTRIBUTE = '_acseo_geo_response';

    public function __construct(
        private readonly int $httpCacheMaxAge,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function create(string $body, string $contentType): Response
    {
        $event = new GeoResponseCreationEvent($body, $contentType);
        $this->eventDispatcher?->dispatch($event, GeoEvents::BEFORE_RESPONSE_CREATION);

        $body = $event->getBody();
        $headers = array_merge($event->getHeaders(), [
            'Content-Type' => $event->getContentType(),
            'ETag' => \sprintf('"%s"', hash('sha256', $body)),
        ]);

        $response = new Response($body, Response::HTTP_OK, $headers);
        $response->setPublic();
        $response->setMaxAge($this->httpCacheMaxAge);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        $request = $this->requestStack?->getCurrentRequest();
        if (null !== $request) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, true);
            $response->isNotModified($request);
        }

        return $response;
    }
}
