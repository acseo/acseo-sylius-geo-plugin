<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Command;

use ACSEO\SyliusGeoPlugin\Checker\ConfigurationDiagnostics;
use ACSEO\SyliusGeoPlugin\Checker\ExportIssue;
use ACSEO\SyliusGeoPlugin\Checker\ExportIssueType;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnosticsInterface;
use ACSEO\SyliusGeoPlugin\Command\DebugProductCommand;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\InputReader;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\IssueWriter;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\EndpointSettings;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;
use Tests\ACSEO\SyliusGeoPlugin\Support\DebugTargetResolverFactory;

final class DebugProductCommandTest extends TestCase
{
    #[TestDox('An exportable product is reported and its Markdown can be previewed')]
    public function test_exportable_product_with_markdown_preview(): void
    {
        $tester = $this->runCommand(['slug' => 'mug', '--markdown' => true], $this->product());

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Product "Mug" is exportable for locale "en_US" on channel "WEB".', $tester->getDisplay());
        self::assertStringContainsString('# Mug', $tester->getDisplay());
    }

    #[TestDox('Blocking issues are listed and fail the command')]
    public function test_blocking_issues_fail_the_command(): void
    {
        $tester = $this->runCommand(['slug' => 'mug'], $this->product(), [new ExportIssue(ExportIssueType::ProductDisabled)]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Blocking issues', $tester->getDisplay());
        self::assertStringContainsString('Product is disabled.', $tester->getDisplay());
    }

    #[TestDox('Warnings are listed without failing the command')]
    public function test_warnings_do_not_fail_the_command(): void
    {
        $warning = new ExportIssue(ExportIssueType::VariantWithoutPricing, ['%variant%' => 'mug-s', '%channel%' => 'WEB']);

        $tester = $this->runCommand(['slug' => 'mug'], $this->product(), [], [$warning]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Variant "mug-s" has no channel pricing for channel "WEB"', $tester->getDisplay());
    }

    #[TestDox('An unknown product fails the command')]
    public function test_unknown_product_fails(): void
    {
        $tester = $this->runCommand(['slug' => 'ghost'], null);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Product "ghost" was not found on channel "WEB" for locale "en_US".', $tester->getDisplay());
    }

    #[TestDox('A disabled endpoint blocks the diagnostic')]
    public function test_disabled_endpoint_blocks_the_diagnostic(): void
    {
        $tester = $this->runCommand(['slug' => 'mug'], $this->product(), [], [], new EndpointSettings(productMarkdown: false));

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('The "product_markdown" endpoint is disabled by configuration.', $tester->getDisplay());
    }

    #[TestDox('A locale that is not enabled on the channel fails the command')]
    public function test_unavailable_locale_fails(): void
    {
        $tester = $this->runCommand(['slug' => 'mug', '--locale' => 'de_DE'], $this->product());

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Locale "de_DE" is not available on this channel.', $tester->getDisplay());
    }

    #[TestDox('A missing current channel fails the command')]
    public function test_missing_channel_fails(): void
    {
        $tester = $this->runCommand(['slug' => 'mug'], $this->product(), [], [], null, false);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('No current channel could be resolved.', $tester->getDisplay());
    }

    private function product(): ProductInterface
    {
        return CatalogFactory::product('mug', CatalogFactory::channel());
    }

    /**
     * @param array<string, mixed> $input
     * @param list<ExportIssue> $blocking
     * @param list<ExportIssue> $warnings
     */
    private function runCommand(array $input, ?ProductInterface $product, array $blocking = [], array $warnings = [], ?EndpointSettings $settings = null, bool $withChannel = true): CommandTester
    {
        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findOneByChannelAndSlug')->willReturn($product);

        $diagnostics = $this->createStub(ProductGeoExportDiagnosticsInterface::class);
        $diagnostics->method('exclusionReasons')->willReturn($blocking);
        $diagnostics->method('warnings')->willReturn($warnings);

        $generator = $this->createStub(ProductMarkdownGeneratorInterface::class);
        $generator->method('generate')->willReturn('# Mug');

        $translator = DebugTargetResolverFactory::translator();
        $channel = $withChannel ? CatalogFactory::channel() : null;

        $tester = new CommandTester(new DebugProductCommand(
            $repository,
            DebugTargetResolverFactory::create($channel),
            $diagnostics,
            new ConfigurationDiagnostics($settings ?? new EndpointSettings()),
            $generator,
            new InputReader(),
            new IssueWriter($translator),
            $translator,
        ));
        $tester->execute($input);

        return $tester;
    }
}
