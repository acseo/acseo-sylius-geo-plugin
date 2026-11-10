<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Support;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\DebugTargetResolver;
use ACSEO\SyliusGeoPlugin\Command\Diagnostic\ExportContextWriter;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use ACSEO\SyliusGeoPlugin\Translation\DiagnosticTranslator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class DebugTargetResolverFactory
{
    public static function translator(): DiagnosticTranslator
    {
        return new DiagnosticTranslator(TranslatorFactory::create());
    }

    public static function create(?ChannelInterface $channel): DebugTargetResolver
    {
        $channelContext = new readonly class($channel) implements ChannelContextInterface {
            public function __construct(private ?ChannelInterface $channel)
            {
            }

            public function getChannel(): BaseChannelInterface
            {
                return $this->channel ?? throw new ChannelNotFoundException();
            }
        };

        return new DebugTargetResolver(
            new CurrentChannelProvider($channelContext),
            new ChannelLocaleResolver(),
            new ExportContextWriter(self::translator()),
            self::translator(),
        );
    }
}
