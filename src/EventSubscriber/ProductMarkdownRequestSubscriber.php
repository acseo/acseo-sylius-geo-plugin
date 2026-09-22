<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\EventSubscriber;

use ACSEO\SyliusGeoPlugin\Http\MarkdownAcceptNegotiator;
use ACSEO\SyliusGeoPlugin\Http\ProductMarkdownResponder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ProductMarkdownRequestSubscriber implements EventSubscriberInterface
{
    private const PRODUCT_PATH_PATTERN = '#^/(?P<locale>[A-Za-z]{2,4}(?:_(?:[A-Za-z]{4}|[0-9]{3}))?(?:_(?:[A-Za-z]{2}|[0-9]{3}))?)/products/(?P<slug>[^/]+)$#';

    public function __construct(
        private readonly ProductMarkdownResponder $productMarkdownResponder,
        private readonly bool $enabled = true,
        private readonly MarkdownAcceptNegotiator $negotiator = new MarkdownAcceptNegotiator(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!\in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_HEAD], true)) {
            return;
        }

        $accept = trim($request->headers->get('Accept', ''));
        if ('' === $accept) {
            return;
        }

        if (preg_match(self::PRODUCT_PATH_PATTERN, $request->getPathInfo(), $matches) !== 1) {
            return;
        }

        $negotiated = $this->negotiator->negotiate($accept);
        if (MarkdownAcceptNegotiator::NOT_ACCEPTABLE === $negotiated) {
            $event->setResponse($this->notAcceptableResponse());

            return;
        }

        if (MarkdownAcceptNegotiator::MARKDOWN !== $negotiated) {
            return;
        }

        $event->setResponse($this->createMarkdownResponse($matches['locale'], $matches['slug']));
    }

    private function createMarkdownResponse(string $localeCode, string $slug): Response
    {
        return $this->productMarkdownResponder->createForRouteLocale(
            $localeCode,
            $slug,
            true,
            'Product "%s" not found.',
            'Product "%s" is not available for GEO export.',
        );
    }

    private function notAcceptableResponse(): Response
    {
        $response = new Response('', Response::HTTP_NOT_ACCEPTABLE);
        $response->setVary('Accept');

        return $response;
    }
}
