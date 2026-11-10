<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command\Diagnostic;

use ACSEO\SyliusGeoPlugin\Checker\ExportIssue;
use ACSEO\SyliusGeoPlugin\Translation\DiagnosticTranslator;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class IssueWriter
{
    public function __construct(private DiagnosticTranslator $translator)
    {
    }

    /** @param list<ExportIssue> $issues */
    public function blocking(SymfonyStyle $io, array $issues): void
    {
        $this->section($io, 'acseo_sylius_geo_plugin.command.blocking_issues', $issues);
    }

    /** @param list<ExportIssue> $issues */
    public function warnings(SymfonyStyle $io, array $issues): void
    {
        $this->section($io, 'acseo_sylius_geo_plugin.command.warnings', $issues);
    }

    /** @param list<ExportIssue> $issues */
    private function section(SymfonyStyle $io, string $titleKey, array $issues): void
    {
        $io->section($this->translator->text($titleKey));
        $io->listing($this->translator->issues($issues));
    }
}
