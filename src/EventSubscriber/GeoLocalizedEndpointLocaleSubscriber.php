<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\EventSubscriber;

use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Webmozart\Assert\Assert;

final class GeoLocalizedEndpointLocaleSubscriber implements EventSubscriberInterface
{
    private const LOCALE_PATTERN = '[A-Za-z]{2,4}(?:_(?:[A-Za-z]{4}|[0-9]{3}))?(?:_(?:[A-Za-z]{2}|[0-9]{3}))?';

    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly ChannelLocaleResolverInterface $localeResolver,
        private readonly string $llmTxtPath,
        private readonly string $geoPrefix,
        private readonly string $geoProductsPrefix,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 12],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $localeCode = $this->extractLocaleCode($event->getRequest()->getPathInfo());
        if (null === $localeCode) {
            return;
        }

        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        if (!$this->localeResolver->supports($channel, $localeCode)) {
            throw new NotFoundHttpException(\sprintf('Locale "%s" is not available on this channel.', $localeCode));
        }
    }

    private function extractLocaleCode(string $pathInfo): ?string
    {
        $localizedEndpointPattern = \sprintf(
            '#^/(?P<locale>%s)/(?:%s|%s/sitemap\.xml|%s/%s/[^/]+\.md)$#',
            self::LOCALE_PATTERN,
            preg_quote($this->llmTxtPath, '#'),
            preg_quote($this->geoPrefix, '#'),
            preg_quote($this->geoPrefix, '#'),
            preg_quote($this->geoProductsPrefix, '#'),
        );

        if (preg_match($localizedEndpointPattern, $pathInfo, $matches) !== 1) {
            return null;
        }

        return $matches['locale'];
    }
}
