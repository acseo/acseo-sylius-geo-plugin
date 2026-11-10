<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Locale;

use ACSEO\SyliusGeoPlugin\Exception\DefaultLocaleMissingException;
use ACSEO\SyliusGeoPlugin\Exception\LocaleNotAvailableException;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;

final class ChannelLocaleResolverTest extends TestCase
{
    #[TestDox('A locale enabled on the channel is accepted')]
    public function test_enabled_locale_is_accepted(): void
    {
        $resolver = new ChannelLocaleResolver();
        $channel = CatalogFactory::channel();

        self::assertTrue($resolver->supports($channel, 'en_US'));
        self::assertSame('en_US', $resolver->resolve($channel, 'en_US'));
    }

    #[TestDox('The channel default locale is used when none is requested')]
    public function test_default_locale_is_used_without_request(): void
    {
        $resolver = new ChannelLocaleResolver();

        self::assertSame('en_US', $resolver->resolve(CatalogFactory::channel(), null));
        self::assertSame('en_US', $resolver->resolve(CatalogFactory::channel(), '  '));
    }

    #[TestDox('A locale that is not enabled on the channel is rejected')]
    public function test_unknown_locale_is_rejected(): void
    {
        $this->expectException(LocaleNotAvailableException::class);

        (new ChannelLocaleResolver())->resolve(CatalogFactory::channel(), 'de_DE');
    }

    #[TestDox('A channel without default locale cannot resolve a locale')]
    public function test_channel_without_default_locale_is_rejected(): void
    {
        $this->expectException(DefaultLocaleMissingException::class);

        (new ChannelLocaleResolver())->resolve(new Channel(), null);
    }
}
