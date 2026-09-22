<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

use ACSEO\SyliusGeoPlugin\Exception\ProductNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

final class ProductMarkdownResponder
{
    /** @param ProductRepositoryInterface<ProductInterface> $productRepository */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductMarkdownGeneratorInterface $productMarkdownGenerator,
        private readonly ChannelContextInterface $channelContext,
        private readonly ChannelLocaleResolverInterface $localeResolver,
        private readonly GeoResponseFactory $geoResponseFactory,
    ) {
    }

    public function createForRouteLocale(
        string $localeCode,
        string $slug,
        bool $varyAccept = false,
        string $missingMessage = 'Product "%s" is not available.',
        string $unavailableMessage = 'Product "%s" is not available.',
    ): Response {
        $channel = $this->channel();
        if (!$this->localeResolver->supports($channel, $localeCode)) {
            throw new NotFoundHttpException(\sprintf('Locale "%s" is not available on this channel.', $localeCode));
        }

        return $this->create($channel, $localeCode, $slug, $varyAccept, $missingMessage, $unavailableMessage);
    }

    public function createForRequestedLocale(
        ?string $requestedLocaleCode,
        string $slug,
        string $missingMessage = 'Product "%s" is not available.',
        string $unavailableMessage = 'Product "%s" is not available.',
    ): Response {
        $channel = $this->channel();
        $localeCode = $this->localeResolver->resolve($channel, $requestedLocaleCode);

        return $this->create($channel, $localeCode, $slug, false, $missingMessage, $unavailableMessage);
    }

    private function channel(): ChannelInterface
    {
        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        return $channel;
    }

    private function create(
        ChannelInterface $channel,
        string $localeCode,
        string $slug,
        bool $varyAccept,
        string $missingMessage,
        string $unavailableMessage,
    ): Response {
        $product = $this->productRepository->findOneByChannelAndSlug($channel, $localeCode, $slug);
        if (!$product instanceof ProductInterface) {
            throw new NotFoundHttpException(\sprintf($missingMessage, $slug));
        }

        try {
            $body = $this->productMarkdownGenerator->generate($product, $channel, $localeCode);
        } catch (ProductNotAvailableForGeoException) {
            throw new NotFoundHttpException(\sprintf($unavailableMessage, $slug));
        }

        $response = $this->geoResponseFactory->create($body, 'text/markdown; charset=utf-8');
        if ($varyAccept) {
            $response->setVary('Accept');
        }

        return $response;
    }
}
