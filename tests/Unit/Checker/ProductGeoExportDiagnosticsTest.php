<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Checker;

use ACSEO\SyliusGeoPlugin\Checker\ExportIssueType;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnostics;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;

final class ProductGeoExportDiagnosticsTest extends TestCase
{
    #[TestDox('An exportable product has no exclusion reason')]
    public function test_exportable_product_has_no_reason(): void
    {
        $channel = CatalogFactory::channel();
        $product = CatalogFactory::product('mug', $channel);
        $product->addVariant($this->variant('mug-variant'));

        self::assertSame([], $this->diagnostics(true)->exclusionReasons($product, $channel, CatalogFactory::LOCALE));
    }

    #[TestDox('Every blocking cause is reported with its own reason')]
    public function test_blocking_causes_are_reported(): void
    {
        $channel = CatalogFactory::channel();
        $product = CatalogFactory::product('mug', CatalogFactory::channel('OTHER'), false);
        $product->setSlug(null);

        $types = array_map(
            static fn ($issue): ExportIssueType => $issue->type,
            $this->diagnostics(false)->exclusionReasons($product, $channel, CatalogFactory::LOCALE),
        );

        self::assertSame([
            ExportIssueType::ProductDisabled,
            ExportIssueType::ProductNotInChannel,
            ExportIssueType::ProductRejectedByChecker,
            ExportIssueType::ProductWithoutSlug,
            ExportIssueType::ProductWithoutEnabledVariants,
        ], $types);
    }

    #[TestDox('A variant without channel pricing is a warning')]
    public function test_variant_without_pricing_is_a_warning(): void
    {
        $channel = CatalogFactory::channel();
        $product = CatalogFactory::product('mug', $channel);
        $product->addVariant($this->variant('mug-variant'));

        $warnings = $this->diagnostics(true)->warnings($product, $channel);

        self::assertCount(1, $warnings);
        self::assertSame(ExportIssueType::VariantWithoutPricing, $warnings[0]->type);
        self::assertSame(['%variant%' => 'mug-variant', '%channel%' => 'WEB'], $warnings[0]->parameters);
    }

    private function variant(string $code): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setCode($code);

        return $variant;
    }

    private function diagnostics(bool $visible): ProductGeoExportDiagnostics
    {
        return new ProductGeoExportDiagnostics(
            new readonly class($visible) implements ProductGeoVisibilityCheckerInterface {
                public function __construct(private bool $visible)
                {
                }

                public function isVisible(ProductInterface $product, ChannelInterface $channel): bool
                {
                    return $this->visible;
                }
            },
            new ResourceLocalizer(),
        );
    }
}
