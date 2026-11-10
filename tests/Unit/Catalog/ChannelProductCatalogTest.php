<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Catalog;

use ACSEO\SyliusGeoPlugin\Catalog\CandidateProductReaderInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalog;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;

final class ChannelProductCatalogTest extends TestCase
{
    private ChannelInterface $channel;

    #[\Override]
    protected function setUp(): void
    {
        $this->channel = CatalogFactory::channel();
    }

    #[TestDox('Hidden products do not consume a slot of the limit')]
    public function test_hidden_products_do_not_consume_slots(): void
    {
        $reader = $this->reader(['hidden-1', 'hidden-2', 'mug-1', 'mug-2', 'mug-3']);

        $products = $this->catalog($reader, productLimit: 2, scanLimit: 100)->findEnabledProducts($this->channel, 'en_US');

        self::assertSame(['mug-1', 'mug-2'], $this->codes($products));
    }

    #[TestDox('The scan stops at the scan limit')]
    public function test_scan_is_bounded(): void
    {
        $reader = $this->reader(['hidden-1', 'hidden-2', 'hidden-3', 'mug-1']);

        $products = $this->catalog($reader, productLimit: 2, scanLimit: 3)->findEnabledProducts($this->channel, 'en_US');

        self::assertSame([], $products);
        self::assertSame(3, $reader->scanned());
    }

    #[TestDox('The scan never reads fewer products than the limit')]
    public function test_scan_limit_is_raised_to_the_product_limit(): void
    {
        $reader = $this->reader(['mug-1', 'mug-2', 'mug-3']);

        $products = $this->catalog($reader, productLimit: 3, scanLimit: 1)->findEnabledProducts($this->channel, 'en_US');

        self::assertSame(['mug-1', 'mug-2', 'mug-3'], $this->codes($products));
    }

    #[TestDox('The scan stops when the catalog is exhausted')]
    public function test_scan_stops_when_the_catalog_is_exhausted(): void
    {
        $reader = $this->reader(['mug-1']);

        $products = $this->catalog($reader, productLimit: 10, scanLimit: 1000)->findEnabledProducts($this->channel, 'en_US');

        self::assertSame(['mug-1'], $this->codes($products));
        self::assertSame(1, $reader->calls());
    }

    #[TestDox('A zero limit exports nothing and reads nothing')]
    public function test_zero_limit_reads_nothing(): void
    {
        $reader = $this->reader(['mug-1']);
        $catalog = $this->catalog($reader, productLimit: 0, scanLimit: 10);

        self::assertSame([], $catalog->findEnabledProducts($this->channel, 'en_US'));
        self::assertSame([], $catalog->findCandidateProducts($this->channel, 'en_US'));
        self::assertSame(0, $reader->calls());
    }

    #[TestDox('Candidates are the latest products before the visibility filter')]
    public function test_candidates_are_not_filtered(): void
    {
        $reader = $this->reader(['hidden-1', 'mug-1', 'mug-2']);

        $candidates = $this->catalog($reader, productLimit: 2, scanLimit: 10)->findCandidateProducts($this->channel, 'en_US');

        self::assertSame(['hidden-1', 'mug-1'], $this->codes($candidates));
    }

    /** @param list<ProductInterface> $products */
    private function codes(array $products): array
    {
        return array_map(static fn (ProductInterface $product): ?string => $product->getCode(), $products);
    }

    /** @param list<string> $codes */
    private function reader(array $codes): object
    {
        $products = array_map(fn (string $code): ProductInterface => CatalogFactory::product($code, $this->channel), $codes);

        return new class($products) implements CandidateProductReaderInterface {
            private int $calls = 0;

            private int $scanned = 0;

            /** @param list<ProductInterface> $products */
            public function __construct(private readonly array $products)
            {
            }

            public function page(ChannelInterface $channel, string $localeCode, int $offset, int $size, bool $withVariants = false): array
            {
                ++$this->calls;
                $page = \array_slice($this->products, $offset, $size);
                $this->scanned += \count($page);

                return $page;
            }

            public function calls(): int
            {
                return $this->calls;
            }

            public function scanned(): int
            {
                return $this->scanned;
            }
        };
    }

    private function catalog(CandidateProductReaderInterface $reader, int $productLimit, int $scanLimit): ChannelProductCatalog
    {
        $checker = new class() implements ProductGeoVisibilityCheckerInterface {
            public function isVisible(ProductInterface $product, ChannelInterface $channel): bool
            {
                return !str_starts_with((string) $product->getCode(), 'hidden');
            }
        };

        return new ChannelProductCatalog($reader, $checker, $productLimit, $scanLimit);
    }
}
