<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\EventSubscriber;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\Exception\EndpointDisabledException;
use ACSEO\SyliusGeoPlugin\Exception\LocaleNotAvailableException;
use ACSEO\SyliusGeoPlugin\Http\Endpoint;
use ACSEO\SyliusGeoPlugin\Http\EndpointSettings;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class GeoEndpointGuardSubscriber implements EventSubscriberInterface
{
    public const string ENDPOINT_ATTRIBUTE = '_acseo_sylius_geo_plugin_endpoint';

    public function __construct(
        private EndpointSettings $settings,
        private CurrentChannelProvider $channels,
        private ChannelLocaleResolverInterface $localeResolver,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 12]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $attributes = $event->getRequest()->attributes;
        $endpoint = \is_string($attributes->get(self::ENDPOINT_ATTRIBUTE)) ? Endpoint::tryFrom($attributes->getString(self::ENDPOINT_ATTRIBUTE)) : null;
        if (!$event->isMainRequest() || !$endpoint instanceof Endpoint) {
            return;
        }

        if (!$this->settings->isEnabled($endpoint)) {
            throw new EndpointDisabledException();
        }

        $localeCode = $attributes->get('_locale');
        if (\is_string($localeCode) && !$this->localeResolver->supports($this->channels->current(), $localeCode)) {
            throw new LocaleNotAvailableException();
        }
    }
}
