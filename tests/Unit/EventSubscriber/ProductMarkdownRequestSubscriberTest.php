<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\EventSubscriber;

use ACSEO\SyliusGeoPlugin\EventSubscriber\ProductMarkdownRequestSubscriber;
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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ProductMarkdownRequestSubscriberTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;

    private ProductMarkdownGeneratorInterface&MockObject $generator;

    private ChannelContextInterface&MockObject $channelContext;

    private HttpKernelInterface&MockObject $kernel;

    private ProductMarkdownRequestSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->generator = $this->createMock(ProductMarkdownGeneratorInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
        $this->subscriber = new ProductMarkdownRequestSubscriber(
            $this->responder(),
        );
    }

    public function testItRunsAfterTheSecurityFirewall(): void
    {
        $listeners = ProductMarkdownRequestSubscriber::getSubscribedEvents();

        self::assertLessThan(8, $listeners['kernel.request'][1]);
    }

    public function testItServesMarkdownOnCanonicalProductUrlWhenMarkdownIsAccepted(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $product = $this->createMock(ProductInterface::class);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository
            ->expects(self::once())
            ->method('findOneByChannelAndSlug')
            ->with($channel, 'en_US', 'mug')
            ->willReturn($product)
        ;
        $this->generator->method('generate')->with($product, $channel, 'en_US')->willReturn("# Mug\n");

        $event = $this->createRequestEvent('/en_US/products/mug', 'text/markdown');
        $this->subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame("# Mug\n", $response->getContent());
        self::assertSame('text/markdown; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('Accept', $response->headers->get('Vary'));
    }

    public function testItLetsSyliusServeHtmlWhenHtmlHasHigherQuality(): void
    {
        $this->productRepository->expects(self::never())->method('findOneByChannelAndSlug');

        $event = $this->createRequestEvent('/en_US/products/mug', 'text/markdown;q=0.5,text/html;q=0.9');
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItHonorsMarkdownQualityOverWildcardHtml(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $product = $this->createMock(ProductInterface::class);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->willReturn($product);
        $this->generator->method('generate')->willReturn("# Mug\n");

        $event = $this->createRequestEvent('/en_US/products/mug', 'text/markdown;q=0.9,*/*;q=0.8');
        $this->subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertSame('text/markdown; charset=utf-8', $event->getResponse()->headers->get('Content-Type'));
    }

    public function testItDoesNotServeMarkdownForWildcardAccept(): void
    {
        $this->productRepository->expects(self::never())->method('findOneByChannelAndSlug');

        $event = $this->createRequestEvent('/en_US/products/mug', '*/*');
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItReturns406WhenOnlyUnsupportedTypesAreAccepted(): void
    {
        $event = $this->createRequestEvent('/en_US/products/mug', 'application/json, text/markdown;q=0');
        $this->subscriber->onKernelRequest($event);

        self::assertSame(Response::HTTP_NOT_ACCEPTABLE, $event->getResponse()?->getStatusCode());
        self::assertSame('Accept', $event->getResponse()->headers->get('Vary'));
    }

    public function testItReturns406WhenNoSupportedContentTypeIsAccepted(): void
    {
        $this->productRepository->expects(self::never())->method('findOneByChannelAndSlug');

        $event = $this->createRequestEvent('/en_US/products/mug', 'application/json');
        $this->subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(Response::HTTP_NOT_ACCEPTABLE, $event->getResponse()->getStatusCode());
        self::assertSame('Accept', $event->getResponse()->headers->get('Vary'));
    }

    public function testItIgnoresNonProductUrls(): void
    {
        $this->productRepository->expects(self::never())->method('findOneByChannelAndSlug');

        $event = $this->createRequestEvent('/en_US/geo/products/mug.md', 'text/markdown');
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItIgnoresRoutesOutsideTheSyliusProductPage(): void
    {
        $this->productRepository->expects(self::never())->method('findOneByChannelAndSlug');

        $request = Request::create('/api/products/42', Request::METHOD_GET, [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->attributes->add(['_route' => 'api_products_get_item', 'id' => '42']);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItIgnoresRequestsWithoutAcceptHeader(): void
    {
        $this->productRepository->expects(self::never())->method('findOneByChannelAndSlug');

        $event = $this->createRequestEvent('/en_US/products/mug', '');
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItIgnoresUnsupportedHttpMethods(): void
    {
        $this->productRepository->expects(self::never())->method('findOneByChannelAndSlug');

        $event = $this->createRequestEvent('/en_US/products/mug', 'text/markdown', Request::METHOD_POST);
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItDoesNotInterceptCanonicalProductUrlWhenDisabled(): void
    {
        $subscriber = new ProductMarkdownRequestSubscriber(
            $this->responder(),
            enabled: false,
        );

        $this->productRepository->expects(self::never())->method('findOneByChannelAndSlug');

        $event = $this->createRequestEvent('/en_US/products/mug', 'text/markdown');
        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItReturns404WhenProductNotAvailableForGeo(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $product = $this->createMock(ProductInterface::class);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->willReturn($product);
        $this->generator->method('generate')->willThrowException(new ProductNotAvailableForGeoException('disabled'));

        $this->expectException(NotFoundHttpException::class);

        $event = $this->createRequestEvent('/en_US/products/mug', 'text/markdown');
        $this->subscriber->onKernelRequest($event);
    }

    public function testItReturns404WhenLocaleIsUnsupported(): void
    {
        $channel = $this->createChannelWithLocale('en_US');

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->expects(self::never())->method('findOneByChannelAndSlug');

        $this->expectException(NotFoundHttpException::class);

        $event = $this->createRequestEvent('/fr_FR/products/mug', 'text/markdown');
        $this->subscriber->onKernelRequest($event);
    }

    public function testItReturns404WhenProductIsMissing(): void
    {
        $channel = $this->createChannelWithLocale('en_US');

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->with($channel, 'en_US', 'missing')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $event = $this->createRequestEvent('/en_US/products/missing', 'text/markdown');
        $this->subscriber->onKernelRequest($event);
    }

    public function testItAddsVaryAcceptToTheHtmlResponse(): void
    {
        $request = $this->createRequestEvent('/en_US/products/mug', 'text/html')->getRequest();
        $response = new Response('<html></html>');
        $response->setVary('Cookie');

        $this->subscriber->onKernelResponse(new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame(['Cookie', 'Accept'], $response->getVary());
    }

    public function testItDoesNotDuplicateVaryAccept(): void
    {
        $request = $this->createRequestEvent('/en_US/products/mug', 'text/markdown')->getRequest();
        $response = new Response('# Body');
        $response->setVary('Accept');

        $this->subscriber->onKernelResponse(new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame(['Accept'], $response->getVary());
    }

    public function testItDoesNotAddVaryAcceptOutsideTheSyliusPage(): void
    {
        $request = $this->createRequestEvent('/api/v2/shop/products/12', 'text/html')->getRequest();
        $response = new Response('<html></html>');

        $this->subscriber->onKernelResponse(new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame([], $response->getVary());
    }

    public function testItDoesNotAddVaryAcceptWhenDisabled(): void
    {
        $subscriber = new ProductMarkdownRequestSubscriber($this->responder(), enabled: false);
        $request = $this->createRequestEvent('/en_US/products/mug', 'text/html')->getRequest();
        $response = new Response('<html></html>');

        $subscriber->onKernelResponse(new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame([], $response->getVary());
    }

    private function createRequestEvent(string $path, string $accept, string $method = Request::METHOD_GET): RequestEvent
    {
        $request = Request::create($path, $method, [], [], [], ['HTTP_ACCEPT' => $accept]);
        if (preg_match('#^/(?<locale>[^/]+)/products/(?<slug>[^/]+)$#', $path, $matches) === 1) {
            $request->attributes->add(['_route' => 'sylius_shop_product_show', '_locale' => $matches['locale'], 'slug' => rawurldecode($matches['slug'])]);
        }

        return new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
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
