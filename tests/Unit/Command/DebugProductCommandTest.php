<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Command;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnostics;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Command\DebugProductCommand;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugProductCommandTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;

    private ChannelContextInterface&MockObject $channelContext;

    private ProductGeoVisibilityCheckerInterface&MockObject $visibilityChecker;

    private ProductMarkdownGeneratorInterface&MockObject $markdownGenerator;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->visibilityChecker = $this->createMock(ProductGeoVisibilityCheckerInterface::class);
        $this->markdownGenerator = $this->createMock(ProductMarkdownGeneratorInterface::class);

        $this->commandTester = new CommandTester(new DebugProductCommand(
            $this->productRepository,
            $this->channelContext,
            new ChannelLocaleResolver(),
            new ProductGeoExportDiagnostics($this->visibilityChecker),
            $this->markdownGenerator,
        ));
    }

    public function testItReportsExportableProduct(): void
    {
        $channel = $this->createChannel('en_US');
        $variant = $this->createMock(ProductVariantInterface::class);
        $product = $this->createProduct('Blue Mug', 'blue-mug', true, true, [$variant]);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository
            ->method('findOneByChannelAndSlug')
            ->with($channel, 'en_US', 'blue-mug')
            ->willReturn($product)
        ;
        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);

        $statusCode = $this->commandTester->execute(['slug' => 'blue-mug', '--locale' => 'en_US']);

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('is exportable', $this->commandTester->getDisplay());
    }

    public function testItReportsInvalidLocale(): void
    {
        $this->channelContext->method('getChannel')->willReturn($this->createChannel('en_US'));

        $statusCode = $this->commandTester->execute(['slug' => 'blue-mug', '--locale' => 'fr_FR']);

        self::assertSame(Command::FAILURE, $statusCode);
        self::assertStringContainsString('Locale "fr_FR" is not available on this channel.', $this->commandTester->getDisplay());
    }

    public function testItReportsMissingProduct(): void
    {
        $channel = $this->createChannel('en_US');

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->willReturn(null);

        $statusCode = $this->commandTester->execute(['slug' => 'missing', '--locale' => 'en_US']);

        self::assertSame(Command::FAILURE, $statusCode);
        self::assertStringContainsString('Product "missing" was not found', $this->commandTester->getDisplay());
    }

    public function testItReportsBlockingIssues(): void
    {
        $channel = $this->createChannel('en_US');
        $product = $this->createProduct('Disabled Mug', '', false, false, []);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->willReturn($product);
        $this->visibilityChecker->method('isVisible')->willReturn(false);

        $statusCode = $this->commandTester->execute(['slug' => 'disabled-mug', '--locale' => 'en_US']);
        $display = $this->commandTester->getDisplay();

        self::assertSame(Command::FAILURE, $statusCode);
        self::assertStringContainsString('Product is disabled.', $display);
        self::assertStringContainsString('Product is not assigned to channel "FASHION_WEB".', $display);
        self::assertStringContainsString('Product has no slug for locale "en_US".', $display);
        self::assertStringContainsString('Product has no enabled variants.', $display);
    }

    public function testItReportsRouteDisabledByConfiguration(): void
    {
        $this->commandTester = new CommandTester(new DebugProductCommand(
            $this->productRepository,
            $this->channelContext,
            new ChannelLocaleResolver(),
            new ProductGeoExportDiagnostics($this->visibilityChecker),
            $this->markdownGenerator,
            false,
        ));
        $this->channelContext->method('getChannel')->willReturn($this->createChannel('en_US'));
        $this->productRepository->expects(self::never())->method('findOneByChannelAndSlug');

        $statusCode = $this->commandTester->execute(['slug' => 'blue-mug', '--locale' => 'en_US']);

        self::assertSame(Command::FAILURE, $statusCode);
        self::assertStringContainsString('GEO product Markdown route is disabled by configuration.', $this->commandTester->getDisplay());
    }

    public function testItReportsMissingChannelPricingAsWarning(): void
    {
        $channel = $this->createChannel('en_US');
        $variant = $this->createVariant('BLUE-MUG', null);
        $product = $this->createProduct('Blue Mug', 'blue-mug', true, true, [$variant]);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->willReturn($product);
        $this->visibilityChecker->method('isVisible')->with($product, $channel)->willReturn(true);

        $statusCode = $this->commandTester->execute(['slug' => 'blue-mug', '--locale' => 'en_US']);
        $display = $this->commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('Warnings', $display);
        self::assertStringContainsString('Variant "BLUE-MUG" has no channel pricing for channel "FASHION_WEB"', $display);
    }

    public function testItCanPrintMarkdownPreview(): void
    {
        $channel = $this->createChannel('en_US');
        $variant = $this->createMock(ProductVariantInterface::class);
        $product = $this->createProduct('Blue Mug', 'blue-mug', true, true, [$variant]);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->productRepository->method('findOneByChannelAndSlug')->willReturn($product);
        $this->visibilityChecker->method('isVisible')->willReturn(true);
        $this->markdownGenerator->method('generate')->with($product, $channel, 'en_US')->willReturn("# Blue Mug\n");

        $statusCode = $this->commandTester->execute(['slug' => 'blue-mug', '--locale' => 'en_US', '--markdown' => true]);

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('# Blue Mug', $this->commandTester->getDisplay());
    }

    private function createChannel(string $localeCode): ChannelInterface
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn($localeCode);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('FASHION_WEB');
        $channel->method('getLocales')->willReturn(new ArrayCollection([$locale]));

        return $channel;
    }

    /** @param list<ProductVariantInterface> $enabledVariants */
    private function createProduct(
        string $name,
        string $slug,
        bool $enabled,
        bool $hasChannel,
        array $enabledVariants,
    ): ProductInterface {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getName')->willReturn($name);
        $product->method('getCode')->willReturn(strtoupper($name));
        $product->method('getSlug')->willReturn($slug);
        $product->method('isEnabled')->willReturn($enabled);
        $product->method('hasChannel')->willReturn($hasChannel);
        $product->method('getEnabledVariants')->willReturn(new ArrayCollection($enabledVariants));
        $product->expects(self::any())->method('setCurrentLocale');
        $product->expects(self::any())->method('setFallbackLocale');

        return $product;
    }

    private function createVariant(string $code, ?int $price): ProductVariantInterface
    {
        $channelPricing = null;
        if (null !== $price) {
            $channelPricing = $this->createMock(ChannelPricingInterface::class);
            $channelPricing->method('getPrice')->willReturn($price);
        }

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getCode')->willReturn($code);
        $variant->method('getChannelPricingForChannel')->willReturn($channelPricing);

        return $variant;
    }
}
