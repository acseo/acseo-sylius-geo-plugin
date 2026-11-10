<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\EventSubscriber;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\Exception\LocaleNotAvailableException;
use ACSEO\SyliusGeoPlugin\Http\Endpoint;
use ACSEO\SyliusGeoPlugin\Http\EndpointSettings;
use ACSEO\SyliusGeoPlugin\Http\MarkdownAcceptNegotiator;
use ACSEO\SyliusGeoPlugin\Http\MarkdownResponderInterface;
use ACSEO\SyliusGeoPlugin\Http\Negotiation;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class MarkdownNegotiationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private string $route,
        private Endpoint $endpoint,
        private EndpointSettings $settings,
        private MarkdownAcceptNegotiator $negotiator,
        private MarkdownResponderInterface $responder,
        private CurrentChannelProvider $channels,
        private ChannelLocaleResolverInterface $localeResolver,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $accept = trim((string) $request->headers->get('Accept'));
        $localeCode = $request->attributes->get('_locale');
        $slug = $request->attributes->get('slug');
        if (!$this->isNegotiable($event) || '' === $accept || !\is_string($localeCode) || !\is_string($slug)) {
            return;
        }

        $response = match ($this->negotiator->negotiate($accept)) {
            Negotiation::Markdown => $this->markdownResponse($localeCode, $slug),
            Negotiation::NotAcceptable => new Response('', Response::HTTP_NOT_ACCEPTABLE),
            Negotiation::Html => null,
        };

        if ($response instanceof Response) {
            $event->setResponse($response->setVary('Accept'));
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if ($this->isNegotiable($event) && !\in_array('accept', array_map(strtolower(...), array_filter($response->getVary(), is_string(...))), true)) {
            $response->setVary('Accept', false);
        }
    }

    private function isNegotiable(KernelEvent $event): bool
    {
        $request = $event->getRequest();

        return $this->settings->isEnabled($this->endpoint) &&
            $event->isMainRequest() &&
            $this->route === $request->attributes->get('_route') &&
            \in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_HEAD], true);
    }

    private function markdownResponse(string $localeCode, string $slug): Response
    {
        $channel = $this->channels->current();
        if (!$this->localeResolver->supports($channel, $localeCode)) {
            throw new LocaleNotAvailableException();
        }

        return $this->responder->respond($channel, $localeCode, $slug);
    }
}
