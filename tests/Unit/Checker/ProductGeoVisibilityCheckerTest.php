<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Checker;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityChecker;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\TaxonInterface;

final class ProductGeoVisibilityCheckerTest extends TestCase
{
    private ProductGeoVisibilityChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new ProductGeoVisibilityChecker();
    }

    public function testItAcceptsEnabledProductOnChannel(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection());
        $product->method('getAttributes')->willReturn(new ArrayCollection());

        self::assertTrue($this->checker->isVisible($product, $channel));
    }

    public function testItRejectsDisabledProduct(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $product->method('isEnabled')->willReturn(false);
        $product->method('hasChannel')->willReturn(true);

        self::assertFalse($this->checker->isVisible($product, $channel));
    }

    public function testItRejectsProductOutsideChannel(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(false);

        self::assertFalse($this->checker->isVisible($product, $channel));
    }

    public function testItRejectsProductBeforeAvailabilityWindow(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createAvailableProductMock();
        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getAvailableOn')->willReturn(new \DateTimeImmutable('+1 day'));
        $product->method('getAvailableUntil')->willReturn(null);

        self::assertFalse($this->checker->isVisible($product, $channel));
    }

    public function testItRejectsProductAfterAvailabilityWindow(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createAvailableProductMock();
        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getAvailableOn')->willReturn(null);
        $product->method('getAvailableUntil')->willReturn(new \DateTimeImmutable('-1 day'));

        self::assertFalse($this->checker->isVisible($product, $channel));
    }

    public function testItAcceptsProductInsideAvailabilityWindow(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createAvailableProductMock();
        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getAvailableOn')->willReturn(new \DateTimeImmutable('-1 day'));
        $product->method('getAvailableUntil')->willReturn(new \DateTimeImmutable('+1 day'));
        $product->method('getProductTaxons')->willReturn(new ArrayCollection());
        $product->method('getAttributes')->willReturn(new ArrayCollection());

        self::assertTrue($this->checker->isVisible($product, $channel));
    }

    public function testItRejectsProductAssignedToExcludedTaxon(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedTaxonCodes: ['PRIVATE']);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getCode')->willReturn('PRIVATE');
        $productTaxon = $this->createMock(ProductTaxonInterface::class);
        $productTaxon->method('getTaxon')->willReturn($taxon);

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection([$productTaxon]));

        self::assertFalse($checker->isVisible($product, $channel));
    }

    public function testItRejectsProductAssignedToADescendantOfAnExcludedTaxon(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedTaxonCodes: ['PRIVATE']);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $excluded = $this->createMock(TaxonInterface::class);
        $excluded->method('getCode')->willReturn('PRIVATE');
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getCode')->willReturn('PRIVATE_SUB');
        $taxon->method('getParent')->willReturn($excluded);
        $productTaxon = $this->createMock(ProductTaxonInterface::class);
        $productTaxon->method('getTaxon')->willReturn($taxon);

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection([$productTaxon]));

        self::assertFalse($checker->isVisible($product, $channel));
    }

    public function testItRejectsProductWhoseMainTaxonIsInAnExcludedSubtree(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedTaxonCodes: ['PRIVATE']);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $excluded = $this->createMock(TaxonInterface::class);
        $excluded->method('getCode')->willReturn('PRIVATE');
        $mainTaxon = $this->createMock(TaxonInterface::class);
        $mainTaxon->method('getCode')->willReturn('PRIVATE_SUB');
        $mainTaxon->method('getParent')->willReturn($excluded);

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection());
        $product->method('getMainTaxon')->willReturn($mainTaxon);

        self::assertFalse($checker->isVisible($product, $channel));
    }

    public function testItAcceptsProductWhoseTaxonLineageIsNotExcluded(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedTaxonCodes: ['PRIVATE']);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $parent = $this->createMock(TaxonInterface::class);
        $parent->method('getCode')->willReturn('CATEGORY');
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getCode')->willReturn('MUGS');
        $taxon->method('getParent')->willReturn($parent);
        $productTaxon = $this->createMock(ProductTaxonInterface::class);
        $productTaxon->method('getTaxon')->willReturn($taxon);

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection([$productTaxon]));
        $product->method('getAttributes')->willReturn(new ArrayCollection());

        self::assertTrue($checker->isVisible($product, $channel));
    }

    public function testItRejectsProductWithExcludedAttributeCode(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedAttributeCodes: ['internal_only']);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $attribute = $this->createAttribute('internal_only', true);

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection());
        $product->method('getAttributes')->willReturn(new ArrayCollection([$attribute]));

        self::assertFalse($checker->isVisible($product, $channel));
    }

    public function testItRejectsProductWithExcludedAttributeValue(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedAttributeValues: ['visibility' => ['hidden']]);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $attribute = $this->createAttribute('visibility', 'hidden');

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection());
        $product->method('getAttributes')->willReturn(new ArrayCollection([$attribute]));

        self::assertFalse($checker->isVisible($product, $channel));
    }

    public function testItRejectsProductWithExcludedAttributeValueInsideIterable(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedAttributeValues: ['flags' => ['internal']]);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $attribute = $this->createAttribute('flags', new ArrayCollection(['public', 'internal']));

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection());
        $product->method('getAttributes')->willReturn(new ArrayCollection([$attribute]));

        self::assertFalse($checker->isVisible($product, $channel));
    }

    public function testItAcceptsProductWhenExcludedIterableValuesDoNotMatch(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedAttributeValues: ['flags' => ['internal']]);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $attribute = $this->createAttribute('flags', new ArrayCollection(['public', new \stdClass()]));

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection());
        $product->method('getAttributes')->willReturn(new ArrayCollection([$attribute]));

        self::assertTrue($checker->isVisible($product, $channel));
    }

    public function testItRejectsProductWhoseSelectAttributeLabelIsExcluded(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedAttributeValues: ['geo_visibility' => ['Privé']]);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $attribute = $this->createSelectAttribute('geo_visibility', ['private'], ['private' => ['en_US' => 'Private', 'fr_FR' => 'Privé']]);

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection());
        $product->method('getAttributes')->willReturn(new ArrayCollection([$attribute]));

        self::assertFalse($checker->isVisible($product, $channel));
    }

    public function testItRejectsProductWhoseSelectAttributeKeyIsExcluded(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedAttributeValues: ['geo_visibility' => ['private']]);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $attribute = $this->createSelectAttribute('geo_visibility', ['private'], ['private' => ['en_US' => 'Private']]);

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection());
        $product->method('getAttributes')->willReturn(new ArrayCollection([$attribute]));

        self::assertFalse($checker->isVisible($product, $channel));
    }

    public function testItAcceptsProductWhoseSelectAttributeHasAnotherChoice(): void
    {
        $checker = new ProductGeoVisibilityChecker(excludedAttributeValues: ['geo_visibility' => ['Privé']]);
        $channel = $this->createMock(ChannelInterface::class);
        $product = $this->createMock(ProductInterface::class);
        $attribute = $this->createSelectAttribute('geo_visibility', ['public'], ['private' => ['fr_FR' => 'Privé'], 'public' => ['fr_FR' => 'Public']]);

        $product->method('isEnabled')->willReturn(true);
        $product->method('hasChannel')->with($channel)->willReturn(true);
        $product->method('getProductTaxons')->willReturn(new ArrayCollection());
        $product->method('getAttributes')->willReturn(new ArrayCollection([$attribute]));

        self::assertTrue($checker->isVisible($product, $channel));
    }

    /** @param array<string, array<string, string>> $choices */
    private function createSelectAttribute(string $code, mixed $selected, array $choices): AttributeValueInterface
    {
        $definition = $this->createMock(AttributeInterface::class);
        $definition->method('getType')->willReturn('select');
        $definition->method('getConfiguration')->willReturn(['choices' => $choices, 'multiple' => true]);
        $attribute = $this->createMock(AttributeValueInterface::class);
        $attribute->method('getCode')->willReturn($code);
        $attribute->method('getValue')->willReturn($selected);
        $attribute->method('getAttribute')->willReturn($definition);

        return $attribute;
    }

    private function createAttribute(string $code, mixed $value): AttributeValueInterface
    {
        $attribute = $this->createMock(AttributeValueInterface::class);
        $attribute->method('getCode')->willReturn($code);
        $attribute->method('getValue')->willReturn($value);

        return $attribute;
    }

    /**
     * @return ProductInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createAvailableProductMock(): ProductInterface
    {
        return $this->createMock(AvailableProductInterface::class);
    }
}

interface AvailableProductInterface extends ProductInterface
{
    public function getAvailableOn(): ?\DateTimeInterface;

    public function getAvailableUntil(): ?\DateTimeInterface;
}
