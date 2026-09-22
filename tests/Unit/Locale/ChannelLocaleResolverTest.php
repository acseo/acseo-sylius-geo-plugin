<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Locale;

use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ChannelLocaleResolverTest extends TestCase
{
    public function testItResolvesRequestedLocaleWhenSupported(): void
    {
        $resolver = new ChannelLocaleResolver();
        $channel = $this->createChannel('en_US', 'fr_FR');

        self::assertSame('fr_FR', $resolver->resolve($channel, 'fr_FR'));
    }

    public function testItFallsBackToDefaultLocale(): void
    {
        $resolver = new ChannelLocaleResolver();
        $channel = $this->createChannel('en_US', 'fr_FR');

        self::assertSame('en_US', $resolver->resolve($channel, null));
    }

    public function testItRejectsUnsupportedLocale(): void
    {
        $resolver = new ChannelLocaleResolver();
        $channel = $this->createChannel('en_US');

        $this->expectException(NotFoundHttpException::class);

        $resolver->resolve($channel, 'de_DE');
    }

    public function testItReportsWhetherALocaleIsSupported(): void
    {
        $resolver = new ChannelLocaleResolver();
        $channel = $this->createChannel('en_US', 'fr_FR');

        self::assertTrue($resolver->supports($channel, 'fr_FR'));
        self::assertFalse($resolver->supports($channel, 'de_DE'));
    }

    public function testItRejectsChannelWithoutDefaultLocale(): void
    {
        $resolver = new ChannelLocaleResolver();

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getLocales')->willReturn(new ArrayCollection());
        $channel->method('getDefaultLocale')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $resolver->resolve($channel, null);
    }

    private function createChannel(string ...$localeCodes): ChannelInterface
    {
        $locales = [];
        foreach ($localeCodes as $localeCode) {
            $locale = $this->createMock(LocaleInterface::class);
            $locale->method('getCode')->willReturn($localeCode);
            $locales[] = $locale;
        }

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getLocales')->willReturn(new ArrayCollection($locales));
        $channel->method('getDefaultLocale')->willReturn($locales[0]);

        return $channel;
    }
}
