<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

use ACSEO\SyliusGeoPlugin\Exception\ProductNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGeneratorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ProductMarkdownResponder implements MarkdownResponderInterface
{
    /** @param ProductRepositoryInterface<ProductInterface> $productRepository */
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductMarkdownGeneratorInterface $generator,
        private GeoResponseFactory $responseFactory,
    ) {
    }

    #[\Override]
    public function respond(ChannelInterface $channel, string $localeCode, string $slug): Response
    {
        $product = $this->productRepository->findOneByChannelAndSlug($channel, $localeCode, $slug) ?? throw new NotFoundHttpException();

        try {
            $body = $this->generator->generate($product, $channel, $localeCode);
        } catch (ProductNotAvailableForGeoException) {
            throw new NotFoundHttpException();
        }

        return $this->responseFactory->create($body, 'text/markdown; charset=utf-8');
    }
}
