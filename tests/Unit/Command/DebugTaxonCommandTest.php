<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Command;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Command\DebugTaxonCommand;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugTaxonCommandTest extends TestCase
{
    private TaxonRepositoryInterface&MockObject $taxonRepository;

    private ChannelContextInterface&MockObject $channelContext;

    private TaxonGeoVisibilityCheckerInterface&MockObject $visibilityChecker;

    private TaxonMarkdownGeneratorInterface&MockObject $markdownGenerator;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->taxonRepository = $this->createMock(TaxonRepositoryInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->visibilityChecker = $this->createMock(TaxonGeoVisibilityCheckerInterface::class);
        $this->markdownGenerator = $this->createMock(TaxonMarkdownGeneratorInterface::class);

        $this->commandTester = new CommandTester(new DebugTaxonCommand(
            $this->taxonRepository,
            $this->channelContext,
            new ChannelLocaleResolver(),
            $this->visibilityChecker,
            $this->markdownGenerator,
        ));
    }

    public function testItReportsExportableTaxon(): void
    {
        $channel = $this->createChannel('fr_FR');
        $taxon = $this->createTaxon('Robes', 'robes', true);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->taxonRepository->method('findOneBySlug')->with('robes', 'fr_FR')->willReturn($taxon);
        $this->visibilityChecker->method('isVisible')->with($taxon)->willReturn(true);

        $statusCode = $this->commandTester->execute(['slug' => 'robes', '--locale' => 'fr_FR']);

        self::assertSame(Command::SUCCESS, $statusCode);
        self::assertStringContainsString('is exportable', $this->commandTester->getDisplay());
    }

    public function testItReportsDisabledTaxonAndMissingLocalizedSlug(): void
    {
        $channel = $this->createChannel('fr_FR');
        $taxon = $this->createTaxon('Robes', '', false);

        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->taxonRepository->method('findOneBySlug')->willReturn($taxon);
        $this->visibilityChecker->method('isVisible')->with($taxon)->willReturn(false);

        $statusCode = $this->commandTester->execute(['slug' => 'robes', '--locale' => 'fr_FR']);
        $display = $this->commandTester->getDisplay();

        self::assertSame(Command::FAILURE, $statusCode);
        self::assertStringContainsString('Taxon is disabled.', $display);
        self::assertStringContainsString('Taxon has no slug for locale "fr_FR".', $display);
    }

    public function testItReportsRouteDisabledByConfiguration(): void
    {
        $this->commandTester = new CommandTester(new DebugTaxonCommand(
            $this->taxonRepository,
            $this->channelContext,
            new ChannelLocaleResolver(),
            $this->visibilityChecker,
            $this->markdownGenerator,
            false,
        ));
        $this->channelContext->method('getChannel')->willReturn($this->createChannel('fr_FR'));
        $this->taxonRepository->expects(self::never())->method('findOneBySlug');

        $statusCode = $this->commandTester->execute(['slug' => 'robes', '--locale' => 'fr_FR']);

        self::assertSame(Command::FAILURE, $statusCode);
        self::assertStringContainsString('GEO taxon Markdown route is disabled by configuration.', $this->commandTester->getDisplay());
    }

    private function createChannel(string $localeCode): ChannelInterface
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn($localeCode);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('FASHION_WEB');
        $channel->method('getLocales')->willReturn(new ArrayCollection([$locale]));

        return $channel;
    }

    private function createTaxon(string $name, string $slug, bool $enabled): TaxonInterface
    {
        $taxon = $this->createMock(TaxonInterface::class);
        $taxon->method('getName')->willReturn($name);
        $taxon->method('getCode')->willReturn(strtoupper($name));
        $taxon->method('getSlug')->willReturn($slug);
        $taxon->method('isEnabled')->willReturn($enabled);
        $taxon->expects(self::any())->method('setCurrentLocale');
        $taxon->expects(self::any())->method('setFallbackLocale');

        return $taxon;
    }
}
