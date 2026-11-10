<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Checker;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityChecker;
use ACSEO\SyliusGeoPlugin\Model\ExclusionRules;
use ACSEO\SyliusGeoPlugin\Taxon\TaxonAncestry;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;

final class TaxonGeoVisibilityCheckerTest extends TestCase
{
    #[TestDox('A taxon below the menu taxon is visible')]
    public function test_taxon_below_the_menu_taxon_is_visible(): void
    {
        $channel = CatalogFactory::channel();
        $menu = CatalogFactory::taxon('category');
        $channel->setMenuTaxon($menu);

        self::assertTrue($this->checker()->isVisible(CatalogFactory::taxon('mugs', $menu), $channel));
        self::assertTrue($this->checker()->isVisible($menu, $channel));
    }

    #[TestDox('Nothing is visible on a channel without menu taxon')]
    public function test_channel_without_menu_taxon_exposes_nothing(): void
    {
        self::assertFalse($this->checker()->isVisible(CatalogFactory::taxon('mugs'), CatalogFactory::channel()));
    }

    #[TestDox('A taxon outside the menu taxon subtree is hidden')]
    public function test_taxon_outside_the_menu_subtree_is_hidden(): void
    {
        $channel = CatalogFactory::channel();
        $channel->setMenuTaxon(CatalogFactory::taxon('category'));

        self::assertFalse($this->checker()->isVisible(CatalogFactory::taxon('outlet'), $channel));
    }

    #[TestDox('A taxon with a disabled ancestor is hidden')]
    public function test_taxon_with_a_disabled_ancestor_is_hidden(): void
    {
        $channel = CatalogFactory::channel();
        $menu = CatalogFactory::taxon('category');
        $channel->setMenuTaxon($menu);
        $disabled = CatalogFactory::taxon('hidden', $menu, false);

        self::assertFalse($this->checker()->isVisible(CatalogFactory::taxon('mugs', $disabled), $channel));
    }

    #[TestDox('A taxon in an excluded subtree is hidden')]
    public function test_taxon_in_an_excluded_subtree_is_hidden(): void
    {
        $channel = CatalogFactory::channel();
        $menu = CatalogFactory::taxon('category');
        $channel->setMenuTaxon($menu);
        $private = CatalogFactory::taxon('geo_private', $menu);

        self::assertFalse($this->checker(new ExclusionRules(taxonCodes: ['geo_private']))->isVisible(CatalogFactory::taxon('internal', $private), $channel));
    }

    private function checker(?ExclusionRules $rules = null): TaxonGeoVisibilityChecker
    {
        return new TaxonGeoVisibilityChecker($rules ?? new ExclusionRules(), new TaxonAncestry());
    }
}
