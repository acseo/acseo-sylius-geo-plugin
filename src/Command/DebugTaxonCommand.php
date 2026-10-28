<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Exception\TaxonNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'acseo:geo:debug-taxon',
    description: 'Explains whether a taxon can be exported by ACSEO GEO Markdown.',
)]
final class DebugTaxonCommand extends Command
{
    /** @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository */
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly ChannelLocaleResolverInterface $localeResolver,
        private readonly TaxonGeoVisibilityCheckerInterface $visibilityChecker,
        private readonly TaxonMarkdownGeneratorInterface $markdownGenerator,
        private readonly bool $taxonMarkdownRouteEnabled = true,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('slug', InputArgument::REQUIRED, 'Taxon slug to diagnose.')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale code. Defaults to the current channel default locale.')
            ->addOption('markdown', null, InputOption::VALUE_NONE, 'Print generated Markdown when the taxon is exportable.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $slug = $input->getArgument('slug');
        Assert::string($slug);
        $requestedLocale = $input->getOption('locale');

        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        try {
            $localeCode = $this->localeResolver->resolve(
                $channel,
                \is_string($requestedLocale) && '' !== trim($requestedLocale) ? $requestedLocale : null,
            );
        } catch (NotFoundHttpException $exception) {
            $io->error($exception->getMessage());
            $this->writeContext($io, $channel, \is_string($requestedLocale) ? $requestedLocale : null);

            return Command::FAILURE;
        }

        $io->title('ACSEO GEO taxon diagnostic');
        $this->writeContext($io, $channel, $localeCode);

        if (!$this->taxonMarkdownRouteEnabled) {
            $io->section('Blocking issues');
            $io->listing(['GEO taxon Markdown route is disabled by configuration.']);

            return Command::FAILURE;
        }

        $taxon = $this->taxonRepository->findOneBySlug($slug, $localeCode);
        if (!$taxon instanceof TaxonInterface) {
            $io->error(\sprintf('Taxon "%s" was not found for locale "%s".', $slug, $localeCode));

            return Command::FAILURE;
        }

        $taxon->setCurrentLocale($localeCode);
        $taxon->setFallbackLocale($localeCode);

        $issues = $this->collectIssues($taxon, $channel, $localeCode);
        if ([] !== $issues) {
            $io->section('Blocking issues');
            $io->listing($issues);

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Taxon "%s" is exportable for locale "%s" on channel "%s".',
            (string) ($taxon->getName() ?? $taxon->getCode()),
            $localeCode,
            (string) $channel->getCode(),
        ));

        if (true === $input->getOption('markdown')) {
            try {
                $io->section('Markdown preview');
                $io->writeln($this->markdownGenerator->generate($taxon, $channel, $localeCode));
            } catch (TaxonNotAvailableForGeoException $exception) {
                $io->error($exception->getMessage());

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function writeContext(SymfonyStyle $io, ChannelInterface $channel, ?string $localeCode): void
    {
        $io->definitionList(
            ['Channel' => (string) $channel->getCode()],
            ['Locale' => $localeCode ?? '(not resolved)'],
        );
    }

    /** @return list<string> */
    private function collectIssues(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): array
    {
        $issues = [];

        if (!$taxon->isEnabled()) {
            $issues[] = 'Taxon is disabled.';
        }

        if (!$this->visibilityChecker->isVisible($taxon, $channel)) {
            $issues[] = 'Taxon visibility checker rejected this taxon.';
        }

        $slug = $taxon->getSlug();
        if (null === $slug || '' === trim($slug)) {
            $issues[] = \sprintf('Taxon has no slug for locale "%s".', $localeCode);
        }

        return array_values(array_unique($issues));
    }
}
