<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnosticsInterface;
use ACSEO\SyliusGeoPlugin\Exception\ProductNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
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
    name: 'acseo:geo:debug-product',
    description: 'Explains whether a product can be exported by ACSEO GEO Markdown.',
)]
final class DebugProductCommand extends Command
{
    /** @param ProductRepositoryInterface<ProductInterface> $productRepository */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly ChannelLocaleResolverInterface $localeResolver,
        private readonly ProductGeoExportDiagnosticsInterface $diagnostics,
        private readonly ProductMarkdownGeneratorInterface $markdownGenerator,
        private readonly bool $productMarkdownRouteEnabled = true,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('slug', InputArgument::REQUIRED, 'Product slug to diagnose.')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale code. Defaults to the current channel default locale.')
            ->addOption('markdown', null, InputOption::VALUE_NONE, 'Print generated Markdown when the product is exportable.')
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

        $io->title('ACSEO GEO product diagnostic');
        $this->writeContext($io, $channel, $localeCode);

        if (!$this->productMarkdownRouteEnabled) {
            $io->section('Blocking issues');
            $io->listing(['GEO product Markdown route is disabled by configuration.']);

            return Command::FAILURE;
        }

        $product = $this->productRepository->findOneByChannelAndSlug($channel, $localeCode, $slug);
        if (!$product instanceof ProductInterface) {
            $io->error(\sprintf('Product "%s" was not found on channel "%s" for locale "%s".', $slug, (string) $channel->getCode(), $localeCode));

            return Command::FAILURE;
        }

        $product->setCurrentLocale($localeCode);
        $product->setFallbackLocale($localeCode);

        $issues = $this->diagnostics->exclusionReasons($product, $channel, $localeCode);
        if ([] !== $issues) {
            $io->section('Blocking issues');
            $io->listing($issues);

            return Command::FAILURE;
        }

        $warnings = $this->diagnostics->warnings($product, $channel);
        if ([] !== $warnings) {
            $io->section('Warnings');
            $io->listing($warnings);
        }

        $io->success(\sprintf(
            'Product "%s" is exportable for locale "%s" on channel "%s".',
            (string) ($product->getName() ?? $product->getCode()),
            $localeCode,
            (string) $channel->getCode(),
        ));

        if (true === $input->getOption('markdown')) {
            try {
                $io->section('Markdown preview');
                $io->writeln($this->markdownGenerator->generate($product, $channel, $localeCode));
            } catch (ProductNotAvailableForGeoException $exception) {
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
}
