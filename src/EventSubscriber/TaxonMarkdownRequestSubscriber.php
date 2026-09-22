<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\EventSubscriber;

use ACSEO\SyliusGeoPlugin\Http\MarkdownAcceptNegotiator;
use ACSEO\SyliusGeoPlugin\Http\TaxonMarkdownResponder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class TaxonMarkdownRequestSubscriber implements EventSubscriberInterface
{
    private const TAXON_PATH_PATTERN = '#^/(?P<locale>[A-Za-z]{2,4}(?:_(?:[A-Za-z]{4}|[0-9]{3}))?(?:_(?:[A-Za-z]{2}|[0-9]{3}))?)/taxons/(?P<slug>[^/]+(?:/[^/]+)*)$#';

    public function __construct(
        private readonly TaxonMarkdownResponder $taxonMarkdownResponder,
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

        if (preg_match(self::TAXON_PATH_PATTERN, $request->getPathInfo(), $matches) !== 1) {
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
        return $this->taxonMarkdownResponder->createForRouteLocale($localeCode, $slug, true);
    }

    private function notAcceptableResponse(): Response
    {
        $response = new Response('', Response::HTTP_NOT_ACCEPTABLE);
        $response->setVary('Accept');

        return $response;
    }
}
