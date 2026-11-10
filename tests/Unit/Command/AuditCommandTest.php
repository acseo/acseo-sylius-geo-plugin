<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Command;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Checker\ConfigurationDiagnostics;
use ACSEO\SyliusGeoPlugin\Checker\ExportIssue;
use ACSEO\SyliusGeoPlugin\Checker\ExportIssueType;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnosticsInterface;
use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityChecker;
use ACSEO\SyliusGeoPlugin\Command\AuditCommand;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\AuditReportWriter;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\AuditSnapshotCollector;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\ExportContextWriter;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\InputReader;
use ACSEO\SyliusGeoPlugin\Http\EndpointSettings;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Model\ExclusionRules;
use ACSEO\SyliusGeoPlugin\Taxon\TaxonAncestry;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;
use Tests\ACSEO\SyliusGeoPlugin\Support\DebugTargetResolverFactory;

final class AuditCommandTest extends TestCase
{
    #[TestDox('Both the channel and the locale options are required')]
    public function test_options_are_required(): void
    {
        $tester = $this->runCommand(['--channel' => 'WEB'], CatalogFactory::channel());

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Both --channel and --locale are required.', $tester->getDisplay());
    }

    #[TestDox('An unknown channel fails the audit')]
    public function test_unknown_channel_fails(): void
    {
        $tester = $this->runCommand(['--channel' => 'NOPE', '--locale' => 'en_US'], null);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Channel "NOPE" was not found.', $tester->getDisplay());
    }

    #[TestDox('A locale that is not enabled on the channel fails the audit')]
    public function test_unavailable_locale_fails(): void
    {
        $tester = $this->runCommand(['--channel' => 'WEB', '--locale' => 'de_DE'], CatalogFactory::channel());

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Locale "de_DE" is not available on this channel.', $tester->getDisplay());
    }

    #[TestDox('The audit lists exportable and excluded products, exportable taxons and generated URLs')]
    public function test_audit_report(): void
    {
        $channel = CatalogFactory::channel();
        $menu = CatalogFactory::taxon('category');
        $channel->setMenuTaxon($menu);

        $tester = $this->runCommand(['--channel' => 'WEB', '--locale' => 'en_US'], $channel, [CatalogFactory::taxon('mugs', $menu)]);
        $display = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No configuration warnings.', $display);
        self::assertStringContainsString('Exportable products (1)', $display);
        self::assertStringContainsString('- Mug [mug]', $display);
        self::assertStringContainsString('Excluded products (1)', $display);
        self::assertStringContainsString("- Secret [secret]\n  - Product is disabled.", $this->normalize($display));
        self::assertStringContainsString('Exportable taxons (1)', $display);
        self::assertStringContainsString('- Mugs [mugs]', $display);
        self::assertStringContainsString('- llms.txt: https://shop.example.com/en_US/llms.txt', $display);
        self::assertStringContainsString('- product mug: https://shop.example.com/en_US/geo/products/mug.md', $display);
        self::assertStringContainsString('- taxon mugs: https://shop.example.com/en_US/taxons/mugs', $display);
    }

    #[TestDox('Disabled endpoints and an empty hostname are reported as configuration warnings')]
    public function test_configuration_warnings(): void
    {
        $channel = CatalogFactory::channel('WEB', '');

        $tester = $this->runCommand(['--channel' => 'WEB', '--locale' => 'en_US'], $channel, [], new EndpointSettings(llmTxt: false));

        self::assertStringContainsString('The "llm_txt" endpoint is disabled by configuration.', $tester->getDisplay());
        self::assertStringContainsString('Channel hostname is empty; generated URLs will be relative paths.', $tester->getDisplay());
    }

    private function normalize(string $display): string
    {
        return preg_replace('/ +\n/', "\n", $display) ?? $display;
    }

    /**
     * @param array<string, string> $input
     * @param list<TaxonInterface> $taxons
     */
    private function runCommand(array $input, ?ChannelInterface $channel, array $taxons = [], ?EndpointSettings $settings = null): CommandTester
    {
        $repository = $this->createStub(ChannelRepositoryInterface::class);
        $repository->method('findOneByCode')->willReturn($channel);

        $mug = CatalogFactory::product('mug', $channel);
        $secret = CatalogFactory::product('secret', $channel, false);

        $productCatalog = $this->createStub(ChannelProductCatalogInterface::class);
        $productCatalog->method('findCandidateProducts')->willReturn([$mug, $secret]);
        $taxonCatalog = $this->createStub(ChannelTaxonCatalogInterface::class);
        $taxonCatalog->method('findCandidateTaxons')->willReturn($taxons);

        $diagnostics = $this->createStub(ProductGeoExportDiagnosticsInterface::class);
        $diagnostics->method('exclusionReasons')->willReturnCallback(
            static fn ($product): array => $product->isEnabled() ? [] : [new ExportIssue(ExportIssueType::ProductDisabled)],
        );

        $translator = DebugTargetResolverFactory::translator();
        $localizer = new ResourceLocalizer();
        $tester = new CommandTester(new AuditCommand(
            $repository,
            new ChannelLocaleResolver(),
            new AuditSnapshotCollector($productCatalog, $taxonCatalog, $diagnostics, new TaxonGeoVisibilityChecker(new ExclusionRules(), new TaxonAncestry()), $localizer),
            new ConfigurationDiagnostics($settings ?? new EndpointSettings()),
            new InputReader(),
            new ExportContextWriter($translator),
            new AuditReportWriter($translator, $localizer, new GeoUrlBuilder()),
            $translator,
        ));
        $tester->execute($input);

        return $tester;
    }
}
