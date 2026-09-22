<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Catalog;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalog;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;

final class ChannelProductCatalogTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;

    private ProductGeoVisibilityCheckerInterface&MockObject $visibilityChecker;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->visibilityChecker = $this->createMock(ProductGeoVisibilityCheckerInterface::class);
    }

    public function testItFetchesProductsForRequestedChannelLocaleAndLimit(): void
    {
        $frChannel = $this->createMock(ChannelInterface::class);
        $usChannel = $this->createMock(ChannelInterface::class);
        $frProduct = $this->createMock(ProductInterface::class);
        $usProduct = $this->createMock(ProductInterface::class);
        $this->visibilityChecker->method('isVisible')->willReturn(true);

        $this->productRepository
            ->expects(self::exactly(2))
            ->method('findLatestByChannel')
            ->willReturnMap([
                [$frChannel, 'fr_FR', 7, [$frProduct]],
                [$usChannel, 'en_US', 7, [$usProduct]],
            ])
        ;

        $catalog = new ChannelProductCatalog($this->productRepository, 7, $this->visibilityChecker);

        self::assertSame([$frProduct], $catalog->findEnabledProducts($frChannel, 'fr_FR'));
        self::assertSame([$usProduct], $catalog->findEnabledProducts($usChannel, 'en_US'));
    }

    public function testItFetchesMoreProductsWhenHiddenOnesFillTheLimit(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $hiddenA = $this->createMock(ProductInterface::class);
        $hiddenB = $this->createMock(ProductInterface::class);
        $visibleA = $this->createMock(ProductInterface::class);
        $visibleB = $this->createMock(ProductInterface::class);
        $this->visibilityChecker
            ->method('isVisible')
            ->willReturnCallback(static fn (ProductInterface $product): bool => \in_array($product, [$visibleA, $visibleB], true))
        ;
        $this->productRepository
            ->method('findLatestByChannel')
            ->willReturnCallback(static fn (ChannelInterface $channel, string $locale, int $limit): array => \array_slice([$hiddenA, $hiddenB, $visibleA, $visibleB], 0, $limit))
        ;

        $catalog = new ChannelProductCatalog($this->productRepository, 2, $this->visibilityChecker);

        self::assertSame([$visibleA, $visibleB], $catalog->findEnabledProducts($channel, 'en_US'));
    }

    public function testItStopsWhenTheCatalogIsExhausted(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $visible = $this->createMock(ProductInterface::class);
        $hidden = $this->createMock(ProductInterface::class);
        $this->visibilityChecker->method('isVisible')->willReturnCallback(static fn (ProductInterface $product): bool => $product === $visible);
        $this->productRepository
            ->expects(self::exactly(2))
            ->method('findLatestByChannel')
            ->willReturnCallback(static fn (ChannelInterface $channel, string $locale, int $limit): array => \array_slice([$hidden, $visible], 0, $limit))
        ;

        $catalog = new ChannelProductCatalog($this->productRepository, 2, $this->visibilityChecker);

        self::assertSame([$visible], $catalog->findEnabledProducts($channel, 'en_US'));
    }

    public function testCandidatesIncludeHiddenProductsForDiagnostics(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $hidden = $this->createMock(ProductInterface::class);
        $this->visibilityChecker->method('isVisible')->willReturn(false);
        $this->productRepository->method('findLatestByChannel')->with($channel, 'en_US', 3)->willReturn([$hidden]);

        $catalog = new ChannelProductCatalog($this->productRepository, 3, $this->visibilityChecker);

        self::assertSame([$hidden], $catalog->findCandidateProducts($channel, 'en_US'));
        self::assertSame([], $catalog->findEnabledProducts($channel, 'en_US'));
    }

    public function testAZeroLimitListsNothing(): void
    {
        $this->productRepository->expects(self::never())->method('findLatestByChannel');

        $catalog = new ChannelProductCatalog($this->productRepository, 0, $this->visibilityChecker);

        self::assertSame([], $catalog->findEnabledProducts($this->createMock(ChannelInterface::class), 'en_US'));
    }

    public function testTheScanWindowIsConfigurable(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $hidden = $this->createMock(ProductInterface::class);
        $visible = $this->createMock(ProductInterface::class);
        $this->visibilityChecker->method('isVisible')->willReturnCallback(static fn (ProductInterface $product): bool => $product === $visible);
        $all = [...array_fill(0, 7, $hidden), $visible];
        $requests = [];
        $this->productRepository
            ->method('findLatestByChannel')
            ->willReturnCallback(static function (ChannelInterface $channel, string $locale, int $limit) use ($all, &$requests): array {
                $requests[] = $limit;

                return \array_slice($all, 0, $limit);
            })
        ;

        $narrow = new ChannelProductCatalog($this->productRepository, 1, $this->visibilityChecker, 4);
        self::assertSame([], $narrow->findEnabledProducts($channel, 'en_US'));
        self::assertSame([1, 2, 4], $requests);

        $wide = new ChannelProductCatalog($this->productRepository, 1, $this->visibilityChecker, 8);
        self::assertSame([$visible], $wide->findEnabledProducts($channel, 'en_US'));
    }

    public function testTheScanWindowNeverDropsBelowTheLimit(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $products = [$this->createMock(ProductInterface::class), $this->createMock(ProductInterface::class), $this->createMock(ProductInterface::class)];
        $this->visibilityChecker->method('isVisible')->willReturn(true);
        $this->productRepository->method('findLatestByChannel')->with($channel, 'en_US', 3)->willReturn($products);

        $catalog = new ChannelProductCatalog($this->productRepository, 3, $this->visibilityChecker, 1);

        self::assertSame($products, $catalog->findEnabledProducts($channel, 'en_US'));
    }
}
