<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Checker;

use ACSEO\SyliusGeoPlugin\Attribute\SelectAttributeLabelResolver;
use ACSEO\SyliusGeoPlugin\Checker\ExcludedAttributeMatcher;
use ACSEO\SyliusGeoPlugin\Checker\ExcludedTaxonMatcher;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityChecker;
use ACSEO\SyliusGeoPlugin\Model\ExclusionRules;
use ACSEO\SyliusGeoPlugin\Taxon\TaxonAncestry;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;

final class ProductGeoVisibilityCheckerTest extends TestCase
{
    private ChannelInterface $channel;

    #[\Override]
    protected function setUp(): void
    {
        $this->channel = CatalogFactory::channel();
    }

    #[TestDox('An enabled product of the channel is visible')]
    public function test_enabled_product_of_the_channel_is_visible(): void
    {
        self::assertTrue($this->checker()->isVisible(CatalogFactory::product('mug', $this->channel), $this->channel));
    }

    #[TestDox('A disabled product is hidden')]
    public function test_disabled_product_is_hidden(): void
    {
        self::assertFalse($this->checker()->isVisible(CatalogFactory::product('mug', $this->channel, false), $this->channel));
    }

    #[TestDox('A product that is not sold on the channel is hidden')]
    public function test_product_of_another_channel_is_hidden(): void
    {
        self::assertFalse($this->checker()->isVisible(CatalogFactory::product('mug', CatalogFactory::channel('OTHER')), $this->channel));
    }

    #[TestDox('A product whose taxon or ancestor is excluded is hidden')]
    public function test_product_in_an_excluded_subtree_is_hidden(): void
    {
        $private = CatalogFactory::taxon('geo_private');
        $internal = CatalogFactory::taxon('internal', $private);
        $product = CatalogFactory::product('mug', $this->channel);
        CatalogFactory::classify($product, $internal);

        self::assertFalse($this->checker(new ExclusionRules(taxonCodes: ['geo_private']))->isVisible($product, $this->channel));
        self::assertTrue($this->checker(new ExclusionRules(taxonCodes: ['other']))->isVisible($product, $this->channel));
    }

    #[TestDox('A product whose main taxon is excluded is hidden')]
    public function test_product_with_an_excluded_main_taxon_is_hidden(): void
    {
        $product = CatalogFactory::product('mug', $this->channel);
        $product->setMainTaxon(CatalogFactory::taxon('geo_private'));

        self::assertFalse($this->checker(new ExclusionRules(taxonCodes: ['geo_private']))->isVisible($product, $this->channel));
    }

    #[TestDox('A product with an excluded attribute code is hidden')]
    public function test_product_with_an_excluded_attribute_code_is_hidden(): void
    {
        $product = CatalogFactory::product('mug', $this->channel);
        CatalogFactory::attribute($product, 'geo_hidden', 'whatever');

        self::assertFalse($this->checker(new ExclusionRules(attributeCodes: ['geo_hidden']))->isVisible($product, $this->channel));
    }

    #[TestDox('A product is hidden only when the attribute value is listed')]
    public function test_product_with_an_excluded_attribute_value_is_hidden(): void
    {
        $rules = new ExclusionRules(attributeValues: ['geo_visibility' => ['private']]);
        $private = CatalogFactory::product('secret', $this->channel);
        CatalogFactory::attribute($private, 'geo_visibility', 'private');
        $public = CatalogFactory::product('mug', $this->channel);
        CatalogFactory::attribute($public, 'geo_visibility', 'public');

        self::assertFalse($this->checker($rules)->isVisible($private, $this->channel));
        self::assertTrue($this->checker($rules)->isVisible($public, $this->channel));
    }

    #[TestDox('A select attribute is excluded by its key and by its label in any locale')]
    public function test_select_attribute_is_matched_by_key_and_label(): void
    {
        $configuration = ['choices' => ['private' => ['en_US' => 'Private', 'fr_FR' => 'Privé']]];
        $product = CatalogFactory::product('mug', $this->channel);
        CatalogFactory::attribute($product, 'geo_visibility', ['private'], 'select', $configuration);

        foreach (['private', 'Private', 'Privé'] as $excluded) {
            self::assertFalse(
                $this->checker(new ExclusionRules(attributeValues: ['geo_visibility' => [$excluded]]))->isVisible($product, $this->channel),
                $excluded,
            );
        }

        self::assertTrue($this->checker(new ExclusionRules(attributeValues: ['geo_visibility' => ['public']]))->isVisible($product, $this->channel));
    }

    private function checker(?ExclusionRules $rules = null): ProductGeoVisibilityChecker
    {
        $rules ??= new ExclusionRules();

        return new ProductGeoVisibilityChecker(
            new ExcludedTaxonMatcher($rules, new TaxonAncestry()),
            new ExcludedAttributeMatcher($rules, new SelectAttributeLabelResolver()),
        );
    }
}
