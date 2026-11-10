<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command;

use ACSEO\SyliusGeoPlugin\Checker\ConfigurationDiagnostics;
use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoExportDiagnosticsInterface;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\DebugTarget;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\DebugTargetResolver;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\InputReader;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\IssueWriter;
use ACSEO\SyliusGeoPlugin\Exception\TaxonNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\Endpoint;
use ACSEO\SyliusGeoPlugin\Translation\DiagnosticTranslator;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'acseo_sylius_geo_plugin:debug-taxon')]
final class DebugTaxonCommand extends Command
{
    /** @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository */
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly DebugTargetResolver $targetResolver,
        private readonly TaxonGeoExportDiagnosticsInterface $diagnostics,
        private readonly ConfigurationDiagnostics $configuration,
        private readonly TaxonMarkdownGeneratorInterface $markdownGenerator,
        private readonly InputReader $inputReader,
        private readonly IssueWriter $issueWriter,
        private readonly DiagnosticTranslator $translator,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setDescription($this->translator->text('acseo_sylius_geo_plugin.command.debug_taxon.description'))
            ->addArgument('slug', InputArgument::REQUIRED, $this->translator->text('acseo_sylius_geo_plugin.command.debug_taxon.slug'))
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, $this->translator->text('acseo_sylius_geo_plugin.command.locale_option'))
            ->addOption('markdown', null, InputOption::VALUE_NONE, $this->translator->text('acseo_sylius_geo_plugin.command.debug_taxon.markdown_option'))
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = $this->targetResolver->resolve($io, $this->inputReader->option($input, 'locale'), 'acseo_sylius_geo_plugin.command.debug_taxon.title');
        $taxon = $target instanceof DebugTarget ? $this->findExportable($io, $target, $this->inputReader->slug($input)) : null;
        if (!$target instanceof DebugTarget || !$taxon instanceof TaxonInterface) {
            return Command::FAILURE;
        }

        $io->success($this->translator->text('acseo_sylius_geo_plugin.command.debug_taxon.exportable', [
            '%name%' => (string) ($taxon->getName() ?? $taxon->getCode()),
            '%locale%' => $target->localeCode,
            '%channel%' => (string) $target->channel->getCode(),
        ]));

        return $this->inputReader->wantsMarkdown($input) ? $this->previewMarkdown($io, $taxon, $target) : Command::SUCCESS;
    }

    private function findExportable(SymfonyStyle $io, DebugTarget $target, string $slug): ?TaxonInterface
    {
        $disabled = $this->configuration->endpointIssues(Endpoint::TaxonMarkdown);
        if ([] !== $disabled) {
            $this->issueWriter->blocking($io, $disabled);

            return null;
        }

        $taxon = $this->taxonRepository->findOneBySlug($slug, $target->localeCode);
        if (!$taxon instanceof TaxonInterface) {
            $io->error($this->translator->text('acseo_sylius_geo_plugin.command.debug_taxon.not_found', ['%slug%' => $slug, '%locale%' => $target->localeCode]));

            return null;
        }

        $blocking = $this->diagnostics->exclusionReasons($taxon, $target->channel, $target->localeCode);
        if ([] !== $blocking) {
            $this->issueWriter->blocking($io, $blocking);

            return null;
        }

        return $taxon;
    }

    private function previewMarkdown(SymfonyStyle $io, TaxonInterface $taxon, DebugTarget $target): int
    {
        try {
            $markdown = $this->markdownGenerator->generate($taxon, $target->channel, $target->localeCode);
        } catch (TaxonNotAvailableForGeoException) {
            $io->error($this->translator->text('acseo_sylius_geo_plugin.command.markdown_unavailable'));

            return Command::FAILURE;
        }

        $io->section($this->translator->text('acseo_sylius_geo_plugin.command.markdown_preview'));
        $io->writeln($markdown);

        return Command::SUCCESS;
    }
}
