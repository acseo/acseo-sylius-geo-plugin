<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command\Diagnostic;

use ACSEO\SyliusGeoPlugin\Translation\DiagnosticTranslator;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ExportContextWriter
{
    public function __construct(private DiagnosticTranslator $translator)
    {
    }

    public function write(SymfonyStyle $io, ChannelInterface $channel, ?string $localeCode): void
    {
        $io->definitionList(
            [$this->translator->text('acseo_sylius_geo_plugin.command.channel') => (string) $channel->getCode()],
            [$this->translator->text('acseo_sylius_geo_plugin.command.locale') => $localeCode ?? $this->translator->text('acseo_sylius_geo_plugin.command.locale_not_resolved')],
        );
    }
}
