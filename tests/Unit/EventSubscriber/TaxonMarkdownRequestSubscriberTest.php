<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\EventSubscriber;

use ACSEO\SyliusGeoPlugin\EventSubscriber\TaxonMarkdownRequestSubscriber;
use ACSEO\SyliusGeoPlugin\Exception\TaxonNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Http\TaxonMarkdownResponder;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class TaxonMarkdownRequestSubscriberTest extends TestCase
{
    private TaxonRepositoryInterface&MockObject $taxonRepository;

    private TaxonMarkdownGeneratorInterface&MockObject $generator;

    private ChannelContextInterface&MockObject $channelContext;

    private HttpKernelInterface&MockObject $kernel;

    private TaxonMarkdownRequestSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->taxonRepository = $this->createMock(TaxonRepositoryInterface::class);
        $this->generator = $this->createMock(TaxonMarkdownGeneratorInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
        $this->subscriber = new TaxonMarkdownRequestSubscriber(
            $this->responder(),
        );
    }

    public function testItServesMarkdownOnCanonicalTaxonUrlWhenMarkdownIsAccepted(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $taxon = $this->createMock(TaxonInterface::class);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->taxonRepository
            ->expects(self::once())
            ->method('findOneBySlug')
            ->with('caps', 'en_US')
            ->willReturn($taxon)
        ;
        $this->generator->method('generate')->with($taxon, $channel, 'en_US')->willReturn("# Caps\n");

        $event = $this->createRequestEvent('/en_US/taxons/caps', 'text/markdown');
        $this->subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame("# Caps\n", $response->getContent());
        self::assertSame('text/markdown; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('Accept', $response->headers->get('Vary'));
    }

    public function testItLetsSyliusServeHtmlWhenHtmlHasHigherQuality(): void
    {
        $this->taxonRepository->expects(self::never())->method('findOneBySlug');

        $event = $this->createRequestEvent('/en_US/taxons/caps', 'text/markdown;q=0.5,text/html;q=0.9');
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItDoesNotServeMarkdownForWildcardAccept(): void
    {
        $this->taxonRepository->expects(self::never())->method('findOneBySlug');

        $event = $this->createRequestEvent('/en_US/taxons/caps', '*/*');
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItServesMarkdownForNestedTaxonSlug(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $taxon = $this->createMock(TaxonInterface::class);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->taxonRepository
            ->expects(self::once())
            ->method('findOneBySlug')
            ->with('caps/simple', 'en_US')
            ->willReturn($taxon)
        ;
        $this->generator->method('generate')->willReturn("# Simple\n");

        $event = $this->createRequestEvent('/en_US/taxons/caps/simple', 'text/markdown');
        $this->subscriber->onKernelRequest($event);

        self::assertSame("# Simple\n", $event->getResponse()?->getContent());
    }

    public function testItReturns406WhenNoSupportedContentTypeIsAccepted(): void
    {
        $this->taxonRepository->expects(self::never())->method('findOneBySlug');

        $event = $this->createRequestEvent('/en_US/taxons/caps', 'application/json');
        $this->subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(Response::HTTP_NOT_ACCEPTABLE, $event->getResponse()->getStatusCode());
        self::assertSame('Accept', $event->getResponse()->headers->get('Vary'));
    }

    public function testItIgnoresRequestsWithoutAcceptHeader(): void
    {
        $this->taxonRepository->expects(self::never())->method('findOneBySlug');

        $event = $this->createRequestEvent('/en_US/taxons/caps', '');
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItIgnoresUnsupportedHttpMethods(): void
    {
        $this->taxonRepository->expects(self::never())->method('findOneBySlug');

        $event = $this->createRequestEvent('/en_US/taxons/caps', 'text/markdown', Request::METHOD_POST);
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItDoesNotInterceptCanonicalTaxonUrlWhenDisabled(): void
    {
        $subscriber = new TaxonMarkdownRequestSubscriber(
            $this->responder(),
            enabled: false,
        );

        $this->taxonRepository->expects(self::never())->method('findOneBySlug');

        $event = $this->createRequestEvent('/en_US/taxons/caps', 'text/markdown');
        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testItReturns404WhenTaxonNotAvailableForGeo(): void
    {
        $channel = $this->createChannelWithLocale('en_US');
        $taxon = $this->createMock(TaxonInterface::class);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->taxonRepository->method('findOneBySlug')->willReturn($taxon);
        $this->generator->method('generate')->willThrowException(new TaxonNotAvailableForGeoException('disabled'));

        $this->expectException(NotFoundHttpException::class);

        $event = $this->createRequestEvent('/en_US/taxons/caps', 'text/markdown');
        $this->subscriber->onKernelRequest($event);
    }

    public function testItReturns404WhenLocaleIsUnsupported(): void
    {
        $channel = $this->createChannelWithLocale('en_US');

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->taxonRepository->expects(self::never())->method('findOneBySlug');

        $this->expectException(NotFoundHttpException::class);

        $event = $this->createRequestEvent('/fr_FR/taxons/caps', 'text/markdown');
        $this->subscriber->onKernelRequest($event);
    }

    public function testItReturns404WhenTaxonIsMissing(): void
    {
        $channel = $this->createChannelWithLocale('en_US');

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->taxonRepository->method('findOneBySlug')->with('missing', 'en_US')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $event = $this->createRequestEvent('/en_US/taxons/missing', 'text/markdown');
        $this->subscriber->onKernelRequest($event);
    }

    private function createRequestEvent(string $path, string $accept, string $method = Request::METHOD_GET): RequestEvent
    {
        return new RequestEvent(
            $this->kernel,
            Request::create($path, $method, [], [], [], ['HTTP_ACCEPT' => $accept]),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function createChannelWithLocale(string $localeCode): ChannelInterface
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn($localeCode);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getLocales')->willReturn(new ArrayCollection([$locale]));

        return $channel;
    }

    private function responder(): TaxonMarkdownResponder
    {
        return new TaxonMarkdownResponder(
            $this->taxonRepository,
            $this->generator,
            $this->channelContext,
            new ChannelLocaleResolver(),
            new GeoResponseFactory(3600),
        );
    }
}
