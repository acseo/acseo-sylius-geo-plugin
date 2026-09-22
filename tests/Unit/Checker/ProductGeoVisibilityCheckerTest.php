<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Checker;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityChecker;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
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
