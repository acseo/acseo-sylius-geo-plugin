<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command\Diagnostic;

use ACSEO\SyliusGeoPlugin\Checker\ExportIssue;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Translation\DiagnosticTranslator;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class AuditReportWriter
{
    public function __construct(
        private DiagnosticTranslator $translator,
        private ResourceLocalizer $localizer,
        private GeoUrlBuilderInterface $urlBuilder,
    ) {
    }

    /** @param list<ExportIssue> $warnings */
    public function warnings(SymfonyStyle $io, array $warnings): void
    {
        $io->section($this->translator->text('acseo_sylius_geo_plugin.command.audit.configuration_warnings'));
        if ([] === $warnings) {
            $io->success($this->translator->text('acseo_sylius_geo_plugin.command.audit.no_warnings'));

            return;
        }

        $io->listing($this->translator->issues($warnings));
    }

    /** @param list<ProductInterface> $products */
    public function exportableProducts(SymfonyStyle $io, array $products, string $localeCode): void
    {
        $this->heading($io, 'acseo_sylius_geo_plugin.command.audit.exportable_products', \count($products));
        $this->rows($io, array_map(fn (ProductInterface $product): string => $this->productLabel($product, $localeCode), $products));
    }

    /** @param list<array{ProductInterface, list<ExportIssue>}> $excluded */
    public function excludedProducts(SymfonyStyle $io, array $excluded, string $localeCode): void
    {
        $this->heading($io, 'acseo_sylius_geo_plugin.command.audit.excluded_products', \count($excluded));
        if ([] === $excluded) {
            $io->writeln($this->translator->text('acseo_sylius_geo_plugin.command.audit.none'));
        }

        foreach ($excluded as [$product, $issues]) {
            $io->writeln('- ' . $this->productLabel($product, $localeCode));
            foreach ($this->translator->issues($issues) as $reason) {
                $io->writeln('  - ' . $reason);
            }
        }
    }

    /** @param list<TaxonInterface> $taxons */
    public function exportableTaxons(SymfonyStyle $io, array $taxons, string $localeCode): void
    {
        $this->heading($io, 'acseo_sylius_geo_plugin.command.audit.exportable_taxons', \count($taxons));
        $this->rows($io, array_map(fn (TaxonInterface $taxon): string => \sprintf('%s [%s]', $this->localizer->taxonName($taxon, $localeCode), (string) $taxon->getCode()), $taxons));
    }

    /**
     * @param list<ProductInterface> $products
     * @param list<TaxonInterface> $taxons
     */
    public function generatedUrls(SymfonyStyle $io, ChannelInterface $channel, string $localeCode, array $products, array $taxons): void
    {
        $io->section($this->translator->text('acseo_sylius_geo_plugin.command.audit.generated_urls'));
        $io->writeln(\sprintf('- llms.txt: %s', $this->urlBuilder->absolute($channel, $this->urlBuilder->llmTxtPath($localeCode))));

        foreach ($products as $product) {
            $path = $this->urlBuilder->productMarkdownPath($localeCode, (string) $this->localizer->productSlug($product, $localeCode));
            $io->writeln(\sprintf('- %s %s: %s', $this->translator->text('acseo_sylius_geo_plugin.command.audit.product'), (string) $product->getCode(), $this->urlBuilder->absolute($channel, $path)));
        }

        foreach ($taxons as $taxon) {
            $path = $this->urlBuilder->taxonCanonicalPath($localeCode, (string) $this->localizer->taxonSlug($taxon, $localeCode));
            $io->writeln(\sprintf('- %s %s: %s', $this->translator->text('acseo_sylius_geo_plugin.command.audit.taxon'), (string) $taxon->getCode(), $this->urlBuilder->absolute($channel, $path)));
        }
    }

    private function heading(SymfonyStyle $io, string $titleKey, int $count): void
    {
        $io->section($this->translator->text($titleKey, ['%count%' => $count]));
    }

    /** @param list<string> $labels */
    private function rows(SymfonyStyle $io, array $labels): void
    {
        if ([] === $labels) {
            $io->writeln($this->translator->text('acseo_sylius_geo_plugin.command.audit.none'));
        }

        foreach ($labels as $label) {
            $io->writeln('- ' . $label);
        }
    }

    private function productLabel(ProductInterface $product, string $localeCode): string
    {
        return \sprintf('%s [%s]', $this->localizer->productName($product, $localeCode), (string) $product->getCode());
    }
}
