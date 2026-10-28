<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\EventSubscriber;

use ACSEO\SyliusGeoPlugin\Http\MarkdownAcceptNegotiator;
use ACSEO\SyliusGeoPlugin\Http\ProductMarkdownResponder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ProductMarkdownRequestSubscriber implements EventSubscriberInterface
{
    private const ROUTE = 'sylius_shop_product_show';

    public function __construct(
        private readonly ProductMarkdownResponder $productMarkdownResponder,
        private readonly bool $enabled = true,
        private readonly MarkdownAcceptNegotiator $negotiator = new MarkdownAcceptNegotiator(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4],
            KernelEvents::RESPONSE => 'onKernelResponse',
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

        if (self::ROUTE !== $request->attributes->get('_route')) {
            return;
        }

        $localeCode = $request->attributes->get('_locale');
        $slug = $request->attributes->get('slug');
        if (!\is_string($localeCode) || !\is_string($slug)) {
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

        $event->setResponse($this->createMarkdownResponse($localeCode, $slug));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (self::ROUTE !== $request->attributes->get('_route')) {
            return;
        }

        if (!\in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_HEAD], true)) {
            return;
        }

        $response = $event->getResponse();
        foreach ($response->getVary() as $vary) {
            if (\is_string($vary) && 0 === strcasecmp($vary, 'Accept')) {
                return;
            }
        }

        $response->setVary('Accept', false);
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
