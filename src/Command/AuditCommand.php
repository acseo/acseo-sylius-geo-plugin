<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnosticsInterface;
use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsCommand(
    name: 'acseo:geo:audit',
    description: 'Explains the ACSEO GEO export state for a channel and locale.',
)]
final class AuditCommand extends Command
{
    /** @param ChannelRepositoryInterface<ChannelInterface> $channelRepository */
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly ChannelProductCatalogInterface $productCatalog,
        private readonly ChannelTaxonCatalogInterface $taxonCatalog,
        private readonly ChannelLocaleResolverInterface $localeResolver,
        private readonly ProductGeoExportDiagnosticsInterface $productDiagnostics,
        private readonly TaxonGeoVisibilityCheckerInterface $taxonVisibilityChecker,
        private readonly GeoUrlBuilderInterface $urlBuilder,
        private readonly bool $llmTxtRouteEnabled,
        private readonly bool $productMarkdownRouteEnabled,
        private readonly bool $taxonMarkdownRouteEnabled,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Channel code to audit.')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale code to audit.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $channelCode = $this->requiredOption($input, 'channel');
        $requestedLocale = $this->requiredOption($input, 'locale');

        if (null === $channelCode || null === $requestedLocale) {
            $io->error('Both --channel and --locale are required.');

            return Command::FAILURE;
        }

        $channel = $this->channelRepository->findOneByCode($channelCode);
        if (!$channel instanceof ChannelInterface) {
            $io->error(\sprintf('Channel "%s" was not found.', $channelCode));

            return Command::FAILURE;
        }

        try {
            $localeCode = $this->localeResolver->resolve($channel, $requestedLocale);
        } catch (NotFoundHttpException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->title('ACSEO GEO audit');
        $io->definitionList(
            ['Channel' => $channelCode],
            ['Locale' => $localeCode],
        );

        $warnings = $this->configurationWarnings($channel);
        $io->section('Configuration warnings');
        if ([] === $warnings) {
            $io->success('No configuration warnings.');
        } else {
            $io->listing($warnings);
        }

        $products = $this->productCatalog->findCandidateProducts($channel, $localeCode);
        $exportableProducts = [];
        $excludedProducts = [];
        foreach ($products as $product) {
            $product->setCurrentLocale($localeCode);
            $product->setFallbackLocale($localeCode);

            $reasons = $this->productDiagnostics->exclusionReasons($product, $channel, $localeCode);
            if ([] === $reasons) {
                $exportableProducts[] = $product;
            } else {
                $excludedProducts[] = [$product, $reasons];
            }
        }

        $io->section(\sprintf('Exportable products (%d)', \count($exportableProducts)));
        $this->writeProductRows($io, $exportableProducts);

        $io->section(\sprintf('Excluded products (%d)', \count($excludedProducts)));
        if ([] === $excludedProducts) {
            $io->writeln('None.');
        } else {
            foreach ($excludedProducts as [$product, $reasons]) {
                $io->writeln(\sprintf('- %s', $this->productLabel($product)));
                foreach ($reasons as $reason) {
                    $io->writeln(\sprintf('  - %s', $reason));
                }
            }
        }

        $taxons = array_values(array_filter(
            $this->taxonCatalog->findCandidateTaxons($channel, $localeCode),
            fn (TaxonInterface $taxon): bool => $this->isExportableTaxon($taxon, $channel, $localeCode),
        ));

        $io->section(\sprintf('Exportable taxons (%d)', \count($taxons)));
        $this->writeTaxonRows($io, $taxons, $localeCode);

        $io->section('Generated URLs');
        $this->writeGeneratedUrls($io, $channel, $localeCode, $exportableProducts, $taxons);

        return Command::SUCCESS;
    }

    private function requiredOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        return trim($value);
    }

    /** @return list<string> */
    private function configurationWarnings(ChannelInterface $channel): array
    {
        $warnings = [];

        if (!$this->llmTxtRouteEnabled) {
            $warnings[] = 'The llm.txt route is disabled.';
        }

        if (!$this->productMarkdownRouteEnabled) {
            $warnings[] = 'The product Markdown route is disabled.';
        }

        if (!$this->taxonMarkdownRouteEnabled) {
            $warnings[] = 'The taxon Markdown route is disabled.';
        }

        if ('' === trim((string) $channel->getHostname())) {
            $warnings[] = 'Channel hostname is empty; generated URLs will be relative paths.';
        }

        return $warnings;
    }

    /** @param list<ProductInterface> $products */
    private function writeProductRows(SymfonyStyle $io, array $products): void
    {
        if ([] === $products) {
            $io->writeln('None.');

            return;
        }

        foreach ($products as $product) {
            $io->writeln(\sprintf('- %s', $this->productLabel($product)));
        }
    }

    private function productLabel(ProductInterface $product): string
    {
        return \sprintf(
            '%s [%s]',
            trim((string) ($product->getName() ?? $product->getCode())),
            (string) $product->getCode(),
        );
    }

    private function isExportableTaxon(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): bool
    {
        $taxon->setCurrentLocale($localeCode);
        $taxon->setFallbackLocale($localeCode);

        $slug = $taxon->getSlug();

        return $this->taxonVisibilityChecker->isVisible($taxon, $channel) && null !== $slug && '' !== trim($slug);
    }

    /** @param list<TaxonInterface> $taxons */
    private function writeTaxonRows(SymfonyStyle $io, array $taxons, string $localeCode): void
    {
        if ([] === $taxons) {
            $io->writeln('None.');

            return;
        }

        foreach ($taxons as $taxon) {
            $taxon->setCurrentLocale($localeCode);
            $taxon->setFallbackLocale($localeCode);
            $name = trim((string) ($taxon->getName() ?? $taxon->getCode()));
            $io->writeln(\sprintf('- %s [%s]', $name, (string) $taxon->getCode()));
        }
    }

    /**
     * @param list<ProductInterface> $products
     * @param list<TaxonInterface> $taxons
     */
    private function writeGeneratedUrls(
        SymfonyStyle $io,
        ChannelInterface $channel,
        string $localeCode,
        array $products,
        array $taxons,
    ): void {
        $io->writeln(\sprintf('- llm.txt: %s', $this->urlBuilder->absolute($channel, $this->urlBuilder->llmTxtPath($localeCode))));

        foreach ($products as $product) {
            $slug = (string) $product->getSlug();
            $io->writeln(\sprintf(
                '- product %s: %s',
                (string) $product->getCode(),
                $this->urlBuilder->absolute($channel, $this->urlBuilder->productMarkdownPath($localeCode, $slug)),
            ));
        }

        foreach ($taxons as $taxon) {
            $slug = (string) $taxon->getSlug();
            $io->writeln(\sprintf(
                '- taxon %s: %s',
                (string) $taxon->getCode(),
                $this->urlBuilder->absolute($channel, $this->urlBuilder->taxonCanonicalPath($localeCode, $slug)),
            ));
        }
    }
}
