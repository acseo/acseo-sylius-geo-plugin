<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Checker;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityChecker;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final class TaxonGeoVisibilityCheckerTest extends TestCase
{
    public function testItRequiresChannelMenuTaxon(): void
    {
        $checker = new TaxonGeoVisibilityChecker();
        $channel = $this->channel(null);

        self::assertFalse($checker->isVisible($this->taxon('caps'), $channel));
    }

    public function testItRequiresEnabledTaxon(): void
    {
        $checker = new TaxonGeoVisibilityChecker();
        $menu = $this->taxon('category');
        $channel = $this->channel($menu);

        self::assertTrue($checker->isVisible($this->taxon('caps', parent: $menu), $channel));
        self::assertFalse($checker->isVisible($this->taxon('caps', enabled: false), $channel));
    }

    public function testItHidesTaxonsWhoseAncestorIsDisabled(): void
    {
        $checker = new TaxonGeoVisibilityChecker();
        $parent = $this->taxon('clothing', enabled: false);
        $menu = $this->taxon('category');

        self::assertFalse($checker->isVisible($this->taxon('caps', parent: $parent), $this->channel($menu)));
    }

    public function testItOnlyShowsTaxonsUnderTheChannelMenuTaxon(): void
    {
        $checker = new TaxonGeoVisibilityChecker();
        $menu = $this->taxon('category');
        $channel = $this->channel($menu);

        $child = $this->taxon('caps', parent: $menu);
        $grandChild = $this->taxon('simple', parent: $child);
        $foreign = $this->taxon('other-shop', parent: $this->taxon('other-root'));

        self::assertTrue($checker->isVisible($menu, $channel));
        self::assertTrue($checker->isVisible($child, $channel));
        self::assertTrue($checker->isVisible($grandChild, $channel));
        self::assertFalse($checker->isVisible($foreign, $channel));
    }

    public function testItMatchesTheMenuTaxonByCodeWhenInstancesDiffer(): void
    {
        $checker = new TaxonGeoVisibilityChecker();
        $channel = $this->channel($this->taxon('category'));

        self::assertTrue($checker->isVisible($this->taxon('caps', parent: $this->taxon('category')), $channel));
    }

    public function testItHidesExcludedTaxonsAndTheirDescendants(): void
    {
        $checker = new TaxonGeoVisibilityChecker(['private']);
        $menu = $this->taxon('category');
        $channel = $this->channel($menu);
        $excluded = $this->taxon('private');

        self::assertFalse($checker->isVisible($excluded, $channel));
        self::assertFalse($checker->isVisible($this->taxon('secret', parent: $excluded), $channel));
        self::assertTrue($checker->isVisible($this->taxon('public', parent: $menu), $channel));
    }

    private function channel(?TaxonInterface $menuTaxon): ChannelInterface
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getMenuTaxon')->willReturn($menuTaxon);

        return $channel;
    }

    private function taxon(string $code, bool $enabled = true, ?TaxonInterface $parent = null): TaxonInterface
    {
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getCode')->willReturn($code);
        $taxon->method('isEnabled')->willReturn($enabled);
        $taxon->method('getParent')->willReturn($parent);

        return $taxon;
    }
}
