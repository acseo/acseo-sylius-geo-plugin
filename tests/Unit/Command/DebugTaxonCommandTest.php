<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Command;

use ACSEO\SyliusGeoPlugin\Checker\ConfigurationDiagnostics;
use ACSEO\SyliusGeoPlugin\Checker\ExportIssue;
use ACSEO\SyliusGeoPlugin\Checker\ExportIssueType;
use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoExportDiagnosticsInterface;
use ACSEO\SyliusGeoPlugin\Command\DebugTaxonCommand;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\InputReader;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\IssueWriter;
use ACSEO\SyliusGeoPlugin\Exception\TaxonNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\EndpointSettings;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;
use Tests\ACSEO\SyliusGeoPlugin\Support\DebugTargetResolverFactory;

final class DebugTaxonCommandTest extends TestCase
{
    #[TestDox('An exportable taxon is reported and its Markdown can be previewed')]
    public function test_exportable_taxon_with_markdown_preview(): void
    {
        $tester = $this->runCommand(['slug' => 'mugs', '--markdown' => true], CatalogFactory::taxon('mugs'));

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Taxon "Mugs" is exportable for locale "en_US" on channel "WEB".', $tester->getDisplay());
        self::assertStringContainsString('# Mugs', $tester->getDisplay());
    }

    #[TestDox('Blocking issues are listed and fail the command')]
    public function test_blocking_issues_fail_the_command(): void
    {
        $tester = $this->runCommand(['slug' => 'mugs'], CatalogFactory::taxon('mugs'), [new ExportIssue(ExportIssueType::TaxonDisabled)]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Taxon is disabled.', $tester->getDisplay());
    }

    #[TestDox('An unknown taxon fails the command')]
    public function test_unknown_taxon_fails(): void
    {
        $tester = $this->runCommand(['slug' => 'ghost'], null);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Taxon "ghost" was not found for locale "en_US".', $tester->getDisplay());
    }

    #[TestDox('A disabled endpoint blocks the diagnostic')]
    public function test_disabled_endpoint_blocks_the_diagnostic(): void
    {
        $tester = $this->runCommand(['slug' => 'mugs'], CatalogFactory::taxon('mugs'), [], new EndpointSettings(taxonMarkdown: false));

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('The "taxon_markdown" endpoint is disabled by configuration.', $tester->getDisplay());
    }

    #[TestDox('A taxon rejected by the generator fails the Markdown preview')]
    public function test_generator_rejection_fails_the_preview(): void
    {
        $tester = $this->runCommand(['slug' => 'mugs', '--markdown' => true], CatalogFactory::taxon('mugs'), [], null, true);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('The Markdown preview is not available for this resource.', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     * @param list<ExportIssue> $blocking
     */
    private function runCommand(array $input, ?TaxonInterface $taxon, array $blocking = [], ?EndpointSettings $settings = null, bool $generatorRejects = false): CommandTester
    {
        $repository = $this->createStub(TaxonRepositoryInterface::class);
        $repository->method('findOneBySlug')->willReturn($taxon);

        $diagnostics = $this->createStub(TaxonGeoExportDiagnosticsInterface::class);
        $diagnostics->method('exclusionReasons')->willReturn($blocking);

        $generator = $this->createStub(TaxonMarkdownGeneratorInterface::class);
        if ($generatorRejects) {
            $generator->method('generate')->willThrowException(new TaxonNotAvailableForGeoException());
        } else {
            $generator->method('generate')->willReturn('# Mugs');
        }

        $translator = DebugTargetResolverFactory::translator();
        $tester = new CommandTester(new DebugTaxonCommand(
            $repository,
            DebugTargetResolverFactory::create(CatalogFactory::channel()),
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
