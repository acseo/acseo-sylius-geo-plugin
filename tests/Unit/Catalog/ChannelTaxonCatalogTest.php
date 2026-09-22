<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Catalog;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalog;
use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;

final class ChannelTaxonCatalogTest extends TestCase
{
    private TaxonRepositoryInterface&MockObject $taxonRepository;

    protected function setUp(): void
    {
        $this->taxonRepository = $this->createMock(TaxonRepositoryInterface::class);
    }

    public function testItUsesMenuTaxonChildrenAndAppliesLimit(): void
    {
        $menuTaxon = $this->taxon(true);
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getMenuTaxon')->willReturn($menuTaxon);

        $taxons = [$this->taxon(true, $menuTaxon), $this->taxon(true, $menuTaxon), $this->taxon(true, $menuTaxon)];

        $this->taxonRepository
            ->expects(self::once())
            ->method('findChildrenByChannelMenuTaxon')
            ->with($menuTaxon, 'en_US')
            ->willReturn($taxons)
        ;

        $catalog = new ChannelTaxonCatalog($this->taxonRepository, 2, new TaxonGeoVisibilityChecker());

        self::assertCount(2, $catalog->findEnabledTaxons($channel, 'en_US'));
    }

    public function testItReturnsNothingWithoutMenuTaxon(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getMenuTaxon')->willReturn(null);

        $this->taxonRepository
            ->expects(self::never())
            ->method('findRootNodes')
        ;

        $catalog = new ChannelTaxonCatalog($this->taxonRepository, 20, new TaxonGeoVisibilityChecker());

        self::assertSame([], $catalog->findEnabledTaxons($channel, 'en_US'));
        self::assertSame([], $catalog->findCandidateTaxons($channel, 'en_US'));
    }

    public function testTheLimitIsAppliedAfterTheVisibilityFilter(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $menu = $this->taxon(true);
        $channel->method('getMenuTaxon')->willReturn($menu);
        $visibleA = $this->taxon(true, $menu);
        $visibleB = $this->taxon(true, $menu);
        $this->taxonRepository->method('findChildrenByChannelMenuTaxon')->willReturn([
            $this->taxon(false, $menu),
            $this->taxon(false, $menu),
            $visibleA,
            $visibleB,
        ]);

        $catalog = new ChannelTaxonCatalog($this->taxonRepository, 2, new TaxonGeoVisibilityChecker());

        self::assertSame([$visibleA, $visibleB], $catalog->findEnabledTaxons($channel, 'en_US'));
        self::assertCount(2, $catalog->findCandidateTaxons($channel, 'en_US'));
    }

    public function testCandidatesIncludeHiddenTaxonsForDiagnostics(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $menu = $this->taxon(true);
        $channel->method('getMenuTaxon')->willReturn($menu);
        $hidden = $this->taxon(false, $menu);
        $visible = $this->taxon(true, $menu);
        $this->taxonRepository->method('findChildrenByChannelMenuTaxon')->willReturn([$hidden, $visible]);

        $catalog = new ChannelTaxonCatalog($this->taxonRepository, 5, new TaxonGeoVisibilityChecker());

        self::assertSame([$hidden, $visible], $catalog->findCandidateTaxons($channel, 'en_US'));
        self::assertSame([$visible], $catalog->findEnabledTaxons($channel, 'en_US'));
    }

    public function testAZeroLimitListsNothing(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $catalog = new ChannelTaxonCatalog($this->taxonRepository, 0, new TaxonGeoVisibilityChecker());

        self::assertSame([], $catalog->findEnabledTaxons($channel, 'en_US'));
        self::assertSame([], $catalog->findCandidateTaxons($channel, 'en_US'));
    }

    private function taxon(bool $enabled, ?TaxonInterface $parent = null): TaxonInterface&MockObject
    {
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('isEnabled')->willReturn($enabled);
        $taxon->method('getParent')->willReturn($parent);

        return $taxon;
    }
}
