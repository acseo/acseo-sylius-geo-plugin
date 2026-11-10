<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command;

use ACSEO\SyliusGeoPlugin\Checker\ConfigurationDiagnostics;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnosticsInterface;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\DebugTarget;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\DebugTargetResolver;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\InputReader;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\IssueWriter;
use ACSEO\SyliusGeoPlugin\Exception\ProductNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\Endpoint;
use ACSEO\SyliusGeoPlugin\Translation\DiagnosticTranslator;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'acseo_sylius_geo_plugin:debug-product')]
final class DebugProductCommand extends Command
{
    /** @param ProductRepositoryInterface<ProductInterface> $productRepository */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly DebugTargetResolver $targetResolver,
        private readonly ProductGeoExportDiagnosticsInterface $diagnostics,
        private readonly ConfigurationDiagnostics $configuration,
        private readonly ProductMarkdownGeneratorInterface $markdownGenerator,
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
            ->setDescription($this->translator->text('acseo_sylius_geo_plugin.command.debug_product.description'))
            ->addArgument('slug', InputArgument::REQUIRED, $this->translator->text('acseo_sylius_geo_plugin.command.debug_product.slug'))
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, $this->translator->text('acseo_sylius_geo_plugin.command.locale_option'))
            ->addOption('markdown', null, InputOption::VALUE_NONE, $this->translator->text('acseo_sylius_geo_plugin.command.debug_product.markdown_option'))
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = $this->targetResolver->resolve($io, $this->inputReader->option($input, 'locale'), 'acseo_sylius_geo_plugin.command.debug_product.title');
        $product = $target instanceof DebugTarget ? $this->findExportable($io, $target, $this->inputReader->slug($input)) : null;
        if (!$target instanceof DebugTarget || !$product instanceof ProductInterface) {
            return Command::FAILURE;
        }

        $io->success($this->translator->text('acseo_sylius_geo_plugin.command.debug_product.exportable', [
            '%name%' => (string) ($product->getName() ?? $product->getCode()),
            '%locale%' => $target->localeCode,
            '%channel%' => (string) $target->channel->getCode(),
        ]));

        return $this->inputReader->wantsMarkdown($input) ? $this->previewMarkdown($io, $product, $target) : Command::SUCCESS;
    }

    private function findExportable(SymfonyStyle $io, DebugTarget $target, string $slug): ?ProductInterface
    {
        $disabled = $this->configuration->endpointIssues(Endpoint::ProductMarkdown);
        if ([] !== $disabled) {
            $this->issueWriter->blocking($io, $disabled);

            return null;
        }

        $product = $this->productRepository->findOneByChannelAndSlug($target->channel, $target->localeCode, $slug);
        if (!$product instanceof ProductInterface) {
            $io->error($this->notFoundMessage($target, $slug));

            return null;
        }

        $blocking = $this->diagnostics->exclusionReasons($product, $target->channel, $target->localeCode);
        if ([] !== $blocking) {
            $this->issueWriter->blocking($io, $blocking);

            return null;
        }

        $this->reportWarnings($io, $product, $target);

        return $product;
    }

    private function reportWarnings(SymfonyStyle $io, ProductInterface $product, DebugTarget $target): void
    {
        $warnings = $this->diagnostics->warnings($product, $target->channel);
        if ([] !== $warnings) {
            $this->issueWriter->warnings($io, $warnings);
        }
    }

    private function notFoundMessage(DebugTarget $target, string $slug): string
    {
        return $this->translator->text('acseo_sylius_geo_plugin.command.debug_product.not_found', [
            '%slug%' => $slug,
            '%channel%' => (string) $target->channel->getCode(),
            '%locale%' => $target->localeCode,
        ]);
    }

    private function previewMarkdown(SymfonyStyle $io, ProductInterface $product, DebugTarget $target): int
    {
        try {
            $markdown = $this->markdownGenerator->generate($product, $target->channel, $target->localeCode);
        } catch (ProductNotAvailableForGeoException) {
            $io->error($this->translator->text('acseo_sylius_geo_plugin.command.markdown_unavailable'));

            return Command::FAILURE;
        }

        $io->section($this->translator->text('acseo_sylius_geo_plugin.command.markdown_preview'));
        $io->writeln($markdown);

        return Command::SUCCESS;
    }
}
