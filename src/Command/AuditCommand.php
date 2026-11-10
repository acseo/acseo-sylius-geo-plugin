<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command;

use ACSEO\SyliusGeoPlugin\Checker\ConfigurationDiagnostics;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\AuditReportWriter;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\AuditSnapshotCollector;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\DebugTarget;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\ExportContextWriter;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\InputReader;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use ACSEO\SyliusGeoPlugin\Translation\DiagnosticTranslator;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsCommand(name: 'acseo_sylius_geo_plugin:audit')]
final class AuditCommand extends Command
{
    /** @param ChannelRepositoryInterface<ChannelInterface> $channelRepository */
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly ChannelLocaleResolverInterface $localeResolver,
        private readonly AuditSnapshotCollector $collector,
        private readonly ConfigurationDiagnostics $configuration,
        private readonly InputReader $inputReader,
        private readonly ExportContextWriter $contextWriter,
        private readonly AuditReportWriter $report,
        private readonly DiagnosticTranslator $translator,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setDescription($this->translator->text('acseo_sylius_geo_plugin.command.audit.description'))
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, $this->translator->text('acseo_sylius_geo_plugin.command.audit.channel_option'))
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, $this->translator->text('acseo_sylius_geo_plugin.command.locale_option'))
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = $this->resolveTarget($io, $input);
        if (!$target instanceof DebugTarget) {
            return Command::FAILURE;
        }

        $io->title($this->translator->text('acseo_sylius_geo_plugin.command.audit.title'));
        $this->contextWriter->write($io, $target->channel, $target->localeCode);
        $this->report->warnings($io, $this->configuration->warnings($target->channel));

        $snapshot = $this->collector->collect($target->channel, $target->localeCode);
        $this->report->exportableProducts($io, $snapshot->exportableProducts, $target->localeCode);
        $this->report->excludedProducts($io, $snapshot->excludedProducts, $target->localeCode);
        $this->report->exportableTaxons($io, $snapshot->exportableTaxons, $target->localeCode);
        $this->report->generatedUrls($io, $target->channel, $target->localeCode, $snapshot->exportableProducts, $snapshot->exportableTaxons);

        return Command::SUCCESS;
    }

    private function resolveTarget(SymfonyStyle $io, InputInterface $input): ?DebugTarget
    {
        $channelCode = $this->inputReader->option($input, 'channel');
        $requestedLocale = $this->inputReader->option($input, 'locale');
        if (null === $channelCode || null === $requestedLocale) {
            $io->error($this->translator->text('acseo_sylius_geo_plugin.command.audit.options_required'));

            return null;
        }

        $channel = $this->channelRepository->findOneByCode($channelCode);
        if (!$channel instanceof ChannelInterface) {
            $io->error($this->translator->text('acseo_sylius_geo_plugin.command.audit.channel_not_found', ['%channel%' => $channelCode]));

            return null;
        }

        try {
            return new DebugTarget($channel, $this->localeResolver->resolve($channel, $requestedLocale));
        } catch (NotFoundHttpException) {
            $io->error($this->translator->text('acseo_sylius_geo_plugin.command.locale_unavailable', ['%locale%' => $requestedLocale]));

            return null;
        }
    }
}
