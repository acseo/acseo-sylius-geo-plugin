<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Checker;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnostics;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityChecker;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTaxon;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\Taxon;

final class ProductGeoExportDiagnosticsTest extends TestCase
{
    public function testAnExportableProductHasNoReason(): void
    {
        $channel = $this->channel();
        $product = $this->product($channel, 'blue-mug', [$this->variant('MUG', $channel, 1999)]);

        self::assertSame([], $this->diagnostics()->exclusionReasons($product, $channel, 'en_US'));
        self::assertSame([], $this->diagnostics()->warnings($product, $channel));
    }

    public function testItReportsEveryBlockingReason(): void
    {
        $channel = $this->channel();
        $product = $this->product(new Channel(), '', []);
        $product->setEnabled(false);

        self::assertSame([
            'Product is disabled.',
            'Product is not assigned to channel "FASHION_WEB".',
            'Product visibility checker rejected this product.',
            'Product has no slug for locale "en_US".',
            'Product has no enabled variants.',
        ], $this->diagnostics()->exclusionReasons($product, $channel, 'en_US'));
    }

    public function testItReportsProductsExcludedByTaxon(): void
    {
        $channel = $this->channel();
        $product = $this->product($channel, 'blue-mug', [$this->variant('MUG', $channel, 1999)]);
        $taxon = new Taxon();
        $taxon->setCode('private');
        $productTaxon = new ProductTaxon();
        $productTaxon->setTaxon($taxon);
        $product->addProductTaxon($productTaxon);

        $diagnostics = new ProductGeoExportDiagnostics(new ProductGeoVisibilityChecker(['private']));

        self::assertSame(['Product visibility checker rejected this product.'], $diagnostics->exclusionReasons($product, $channel, 'en_US'));
        self::assertSame([], $this->diagnostics()->exclusionReasons($product, $channel, 'en_US'));
    }

    public function testItIgnoresDisabledVariants(): void
    {
        $channel = $this->channel();
        $variant = $this->variant('MUG', $channel, 1999);
        $variant->setEnabled(false);
        $product = $this->product($channel, 'blue-mug', [$variant]);

        self::assertSame(['Product has no enabled variants.'], $this->diagnostics()->exclusionReasons($product, $channel, 'en_US'));
    }

    public function testItWarnsAboutVariantsWithoutChannelPricing(): void
    {
        $channel = $this->channel();
        $product = $this->product($channel, 'blue-mug', [
            $this->variant('PRICED', $channel, 1999),
            $this->variant('UNPRICED', $channel, null),
        ]);

        self::assertSame(
            ['Variant "UNPRICED" has no channel pricing for channel "FASHION_WEB"; price will be omitted from GEO output.'],
            $this->diagnostics()->warnings($product, $channel),
        );
    }

    private function diagnostics(): ProductGeoExportDiagnostics
    {
        return new ProductGeoExportDiagnostics(new ProductGeoVisibilityChecker());
    }

    private function channel(): Channel
    {
        $channel = new Channel();
        $channel->setCode('FASHION_WEB');

        return $channel;
    }

    /** @param list<ProductVariant> $variants */
    private function product(Channel $channel, string $slug, array $variants): Product
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('MUG');
        $product->setName('Blue mug');
        if ('' !== $slug) {
            $product->setSlug($slug);
        }
        $product->addChannel($channel);
        foreach ($variants as $variant) {
            $product->addVariant($variant);
        }

        return $product;
    }

    private function variant(string $code, Channel $channel, ?int $price): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setCode($code);

        if (null !== $price) {
            $pricing = new ChannelPricing();
            $pricing->setChannelCode((string) $channel->getCode());
            $pricing->setPrice($price);
            $variant->addChannelPricing($pricing);
        }

        return $variant;
    }
}
