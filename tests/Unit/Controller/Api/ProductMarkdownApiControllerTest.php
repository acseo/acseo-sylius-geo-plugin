<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Controller\Api;

use ACSEO\SyliusGeoPlugin\Controller\Api\ProductMarkdownApiController;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductMarkdownApiControllerTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;

    private ProductMarkdownGeneratorInterface&MockObject $generator;

    private ChannelContextInterface&MockObject $channelContext;

    private ProductMarkdownApiController $controller;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->generator = $this->createMock(ProductMarkdownGeneratorInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->controller = new ProductMarkdownApiController(
            $this->responder(),
        );
    }

    public function testItReturnsMarkdown(): void
    {
        $channel = $this->createChannel('en_US');
        $product = $this->createMock(ProductInterface::class);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository
            ->method('findOneByChannelAndSlug')
            ->with($channel, 'en_US', 'mug')
            ->willReturn($product)
        ;
        $this->generator->method('generate')->with($product, $channel, 'en_US')->willReturn("# Mug\n");

        $response = ($this->controller)(Request::create('/api/v2/shop/geo/products/mug'), 'mug');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/markdown; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame("# Mug\n", $response->getContent());
    }

    public function testItReturns404WhenUnavailable(): void
    {
        $channel = $this->createChannel('en_US');
        $product = $this->createMock(ProductInterface::class);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->willReturn($product);
        $this->generator->method('generate')->willThrowException(new ProductNotAvailableForGeoException('disabled'));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Product "mug" is not available.');

        ($this->controller)(Request::create('/api/v2/shop/geo/products/mug'), 'mug');
    }

    public function testItReturnsTheSame404MessageWhenProductIsMissing(): void
    {
        $channel = $this->createChannel('en_US');

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Product "missing" is not available.');

        ($this->controller)(Request::create('/api/v2/shop/geo/products/missing'), 'missing');
    }

    private function createChannel(string $localeCode): ChannelInterface
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn($localeCode);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getLocales')->willReturn(new ArrayCollection([$locale]));
        $channel->method('getDefaultLocale')->willReturn($locale);

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
