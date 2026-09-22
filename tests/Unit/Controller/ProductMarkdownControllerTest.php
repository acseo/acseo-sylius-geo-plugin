<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Controller;

use ACSEO\SyliusGeoPlugin\Controller\ProductMarkdownController;
use ACSEO\SyliusGeoPlugin\Exception\ProductNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Http\ProductMarkdownResponder;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductMarkdownControllerTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;

    private ProductMarkdownGeneratorInterface&MockObject $generator;

    private ChannelContextInterface&MockObject $channelContext;

    private ProductMarkdownController $controller;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->generator = $this->createMock(ProductMarkdownGeneratorInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->controller = new ProductMarkdownController(
            $this->responder(),
        );
    }

    public function testItReturnsMarkdownWithCacheHeaders(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $product = $this->createMock(ProductInterface::class);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository
            ->method('findOneByChannelAndSlug')
            ->with($channel, 'en_US', 'mug')
            ->willReturn($product)
        ;
        $this->generator->method('generate')->with($product, $channel, 'en_US')->willReturn("# Mug\n");

        $response = ($this->controller)('en_US', 'mug');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame("# Mug\n", $response->getContent());
        self::assertSame('text/markdown; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testItReturns404WhenProductMissing(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Product "missing" is not available.');

        ($this->controller)('en_US', 'missing');
    }

    public function testItReturns404WhenProductNotAvailableForGeo(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $product = $this->createMock(ProductInterface::class);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->willReturn($product);
        $this->generator->method('generate')->willThrowException(new ProductNotAvailableForGeoException('disabled'));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Product "mug" is not available.');

        ($this->controller)('en_US', 'mug');
    }

    public function testItReturns404WhenRouteIsDisabledByConfiguration(): void
    {
        $controller = new ProductMarkdownController(
            $this->responder(),
            false,
        );

        $this->expectException(NotFoundHttpException::class);

        $controller('en_US', 'mug');
    }

    private function createChannelWithLocale(string $localeCode): ChannelInterface
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn($localeCode);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getLocales')->willReturn(new ArrayCollection([$locale]));

        return $channel;
    }

    private function responder(): ProductMarkdownResponder
    {
        return new ProductMarkdownResponder(
            $this->productRepository,
            $this->generator,
            $this->channelContext,
            new ChannelLocaleResolver(),
            new GeoResponseFactory(3600),
        );
    }
}
