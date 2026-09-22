<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Command;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnostics;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Command\AuditCommand;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AuditCommandTest extends TestCase
{
    private ChannelRepositoryInterface&MockObject $channelRepository;

    private ChannelProductCatalogInterface&MockObject $productCatalog;

    private ChannelTaxonCatalogInterface&MockObject $taxonCatalog;

    private ProductGeoVisibilityCheckerInterface&MockObject $productVisibilityChecker;

    private TaxonGeoVisibilityCheckerInterface&MockObject $taxonVisibilityChecker;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $this->productCatalog = $this->createMock(ChannelProductCatalogInterface::class);
        $this->taxonCatalog = $this->createMock(ChannelTaxonCatalogInterface::class);
        $this->productVisibilityChecker = $this->createMock(ProductGeoVisibilityCheckerInterface::class);
        $this->taxonVisibilityChecker = $this->createMock(TaxonGeoVisibilityCheckerInterface::class);

        $this->commandTester = new CommandTester(new AuditCommand(
            $this->channelRepository,
            $this->productCatalog,
            $this->taxonCatalog,
            new ChannelLocaleResolver(),
            new ProductGeoExportDiagnostics($this->productVisibilityChecker),
            $this->taxonVisibilityChecker,
            new GeoUrlBuilder(),
            true,
            true,
            true,
        ));
    }

    public function testItAuditsGeoExportState(): void
    {
        $channel = $this->createChannel('FASHION_WEB', 'fr_FR', '');
        $exportableProduct = $this->createProduct('Robe bleue', 'DRESS', 'robe-bleue', true, true, [$this->createMock(ProductVariantInterface::class)]);
        $excludedProduct = $this->createProduct('Produit brouillon', 'DRAFT', '', false, false, []);
        $taxon = $this->createTaxon('Robes', 'DRESSES', 'robes');

        $this->channelRepository->method('findOneByCode')->with('FASHION_WEB')->willReturn($channel);
        $this->productCatalog->method('findCandidateProducts')->with($channel, 'fr_FR')->willReturn([$exportableProduct, $excludedProduct]);
        $this->taxonCatalog->method('findCandidateTaxons')->with($channel, 'fr_FR')->willReturn([$taxon]);
        $this->productVisibilityChecker
            ->method('isVisible')
            ->willReturnMap([
                [$exportableProduct, $channel, true],
                [$excludedProduct, $channel, false],
            ])
        ;
        $this->taxonVisibilityChecker->method('isVisible')->with($taxon)->willReturn(true);

        $statusCode = $this->commandTester->execute([
            '--channel' => 'FASHION_WEB',
            '--locale' => 'fr_FR',
        ]);

        $display = $this->commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('Exportable products (1)', $display);
        self::assertStringContainsString('Robe bleue [DRESS]', $display);
        self::assertStringContainsString('Excluded products (1)', $display);
        self::assertStringContainsString('Product is disabled.', $display);
        self::assertStringContainsString('Product has no slug for locale "fr_FR".', $display);
        self::assertStringContainsString('Exportable taxons (1)', $display);
        self::assertStringContainsString('Robes [DRESSES]', $display);
        self::assertStringContainsString('Channel hostname is empty; generated URLs will be relative paths.', $display);
        self::assertStringContainsString('/fr_FR/llm.txt', $display);
        self::assertStringContainsString('/fr_FR/geo/products/robe-bleue.md', $display);
        self::assertStringContainsString('/fr_FR/taxons/robes', $display);
    }

    public function testItFailsWhenChannelIsMissing(): void
    {
        $this->channelRepository->method('findOneByCode')->with('UNKNOWN')->willReturn(null);

        $statusCode = $this->commandTester->execute([
            '--channel' => 'UNKNOWN',
            '--locale' => 'fr_FR',
        ]);

        self::assertSame(Command::FAILURE, $statusCode);
        self::assertStringContainsString('Channel "UNKNOWN" was not found.', $this->commandTester->getDisplay());
    }

    public function testItFailsWhenLocaleIsInvalid(): void
    {
        $this->channelRepository->method('findOneByCode')->willReturn($this->createChannel('FASHION_WEB', 'en_US', 'shop.example.test'));

        $statusCode = $this->commandTester->execute([
            '--channel' => 'FASHION_WEB',
            '--locale' => 'fr_FR',
        ]);

        self::assertSame(Command::FAILURE, $statusCode);
        self::assertStringContainsString('Locale "fr_FR" is not available on this channel.', $this->commandTester->getDisplay());
    }

    public function testItFailsWhenRequiredOptionsAreMissing(): void
    {
        $this->channelRepository->expects(self::never())->method('findOneByCode');

        $statusCode = $this->commandTester->execute([]);

        self::assertSame(Command::FAILURE, $statusCode);
        self::assertStringContainsString('Both --channel and --locale are required.', $this->commandTester->getDisplay());
    }

    public function testItReportsDisabledRoutesAndAbsoluteUrls(): void
    {
        $this->commandTester = new CommandTester(new AuditCommand(
            $this->channelRepository,
            $this->productCatalog,
            $this->taxonCatalog,
            new ChannelLocaleResolver(),
            new ProductGeoExportDiagnostics($this->productVisibilityChecker),
            $this->taxonVisibilityChecker,
            new GeoUrlBuilder(),
            false,
            false,
            false,
        ));
        $channel = $this->createChannel('FASHION_WEB', 'fr_FR', 'shop.example.test');
        $product = $this->createProduct('Robe bleue', 'DRESS', 'robe-bleue', true, true, [$this->createMock(ProductVariantInterface::class)]);
        $taxon = $this->createTaxon('Robes', 'DRESSES', 'robes');

        $this->channelRepository->method('findOneByCode')->with('FASHION_WEB')->willReturn($channel);
        $this->productCatalog->method('findCandidateProducts')->with($channel, 'fr_FR')->willReturn([$product]);
        $this->taxonCatalog->method('findCandidateTaxons')->with($channel, 'fr_FR')->willReturn([$taxon]);
        $this->productVisibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);
        $this->taxonVisibilityChecker->method('isVisible')->with($taxon)->willReturn(true);

        $statusCode = $this->commandTester->execute([
            '--channel' => 'FASHION_WEB',
            '--locale' => 'fr_FR',
        ]);
        $display = $this->commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('The llm.txt route is disabled.', $display);
        self::assertStringContainsString('The product Markdown route is disabled.', $display);
        self::assertStringContainsString('The taxon Markdown route is disabled.', $display);
        self::assertStringContainsString('https://shop.example.test/fr_FR/llm.txt', $display);
        self::assertStringContainsString('https://shop.example.test/fr_FR/geo/products/robe-bleue.md', $display);
        self::assertStringContainsString('https://shop.example.test/fr_FR/taxons/robes', $display);
    }

    public function testItReportsProductsWithoutCoreVariants(): void
    {
        $channel = $this->createChannel('FASHION_WEB', 'fr_FR', 'shop.example.test');
        $product = $this->createProduct('Produit incomplet', 'BROKEN', 'produit-incomplet', true, true, [new \stdClass()]);

        $this->channelRepository->method('findOneByCode')->with('FASHION_WEB')->willReturn($channel);
        $this->productCatalog->method('findCandidateProducts')->with($channel, 'fr_FR')->willReturn([$product]);
        $this->taxonCatalog->method('findCandidateTaxons')->with($channel, 'fr_FR')->willReturn([]);
        $this->productVisibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);

        $statusCode = $this->commandTester->execute([
            '--channel' => 'FASHION_WEB',
            '--locale' => 'fr_FR',
        ]);

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('Product enabled variants are not Sylius core product variants.', $this->commandTester->getDisplay());
    }

    private function createChannel(string $code, string $localeCode, string $hostname): ChannelInterface
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn($localeCode);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn($code);
        $channel->method('getLocales')->willReturn(new ArrayCollection([$locale]));
        $channel->method('getHostname')->willReturn($hostname);

        return $channel;
    }

    /** @param list<mixed> $enabledVariants */
    private function createProduct(
        string $name,
        string $code,
        string $slug,
        bool $enabled,
        bool $hasChannel,
        array $enabledVariants,
    ): ProductInterface {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getName')->willReturn($name);
        $product->method('getCode')->willReturn($code);
        $product->method('getSlug')->willReturn($slug);
        $product->method('isEnabled')->willReturn($enabled);
        $product->method('hasChannel')->willReturn($hasChannel);
        $product->method('getEnabledVariants')->willReturn(new ArrayCollection($enabledVariants));
        $product->expects(self::any())->method('setCurrentLocale');
        $product->expects(self::any())->method('setFallbackLocale');

        return $product;
    }

    private function createTaxon(string $name, string $code, string $slug): TaxonInterface
    {
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getName')->willReturn($name);
        $taxon->method('getCode')->willReturn($code);
        $taxon->method('getSlug')->willReturn($slug);
        $taxon->expects(self::any())->method('setCurrentLocale');
        $taxon->expects(self::any())->method('setFallbackLocale');

        return $taxon;
    }
}
