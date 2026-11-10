<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command\Diagnostic;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use ACSEO\SyliusGeoPlugin\Translation\DiagnosticTranslator;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class DebugTargetResolver
{
    public function __construct(
        private CurrentChannelProvider $channels,
        private ChannelLocaleResolverInterface $localeResolver,
        private ExportContextWriter $contextWriter,
        private DiagnosticTranslator $translator,
    ) {
    }

    public function resolve(SymfonyStyle $io, ?string $requestedLocale, string $titleKey): ?DebugTarget
    {
        try {
            $channel = $this->channels->current();
        } catch (ChannelNotFoundException) {
            $io->error($this->translator->text('acseo_sylius_geo_plugin.command.channel_not_resolved'));

            return null;
        }

        try {
            $localeCode = $this->localeResolver->resolve($channel, $requestedLocale);
        } catch (NotFoundHttpException) {
            $io->error($this->translator->text('acseo_sylius_geo_plugin.command.locale_unavailable', ['%locale%' => (string) $requestedLocale]));
            $this->contextWriter->write($io, $channel, $requestedLocale);

            return null;
        }

        $io->title($this->translator->text($titleKey));
        $this->contextWriter->write($io, $channel, $localeCode);

        return new DebugTarget($channel, $localeCode);
    }
}
