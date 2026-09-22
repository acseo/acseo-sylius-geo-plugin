<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Provider;

use ACSEO\SyliusGeoPlugin\Provider\CmsPageLink;
use ACSEO\SyliusGeoPlugin\Provider\CmsPageProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\LlmTxtCmsPagesSectionProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;

final class LlmTxtCmsPagesSectionProviderTest extends TestCase
{
    public function testItListsSafePagesForTheRequestedLocale(): void
    {
        $provider = new LlmTxtCmsPagesSectionProvider([$this->pages([
            new CmsPageLink('About [us]', 'https://shop.test/about', "Who we\nare"),
            new CmsPageLink('Dupe', 'https://shop.test/about'),
            new CmsPageLink('About [us]', 'https://shop.test/about', "Who we\nare"),
            new CmsPageLink('FR only', 'https://shop.test/fr', null, 'fr_FR'),
            new CmsPageLink('Script', 'javascript:alert(1)'),
            new CmsPageLink('Local', '/relative'),
        ])]);
        $channel = new Channel();

        self::assertTrue($provider->supports($channel, 'en_US'));

        $sections = $provider->provide($channel, 'en_US');

        self::assertCount(1, $sections);
        self::assertSame('CMS pages', $sections[0]->title);
        self::assertSame([
            '- [About \\[us\\]](https://shop.test/about): Who we are',
            '- [Dupe](https://shop.test/about)',
        ], $sections[0]->lines);
    }

    public function testItIsNotSupportedWithoutUsablePages(): void
    {
        $provider = new LlmTxtCmsPagesSectionProvider([$this->pages([
            new CmsPageLink('FR only', 'https://shop.test/fr', null, 'fr_FR'),
            new CmsPageLink('Script', 'javascript:alert(1)'),
        ])]);
        $channel = new Channel();

        self::assertFalse($provider->supports($channel, 'en_US'));
        self::assertSame([], $provider->provide($channel, 'en_US'));
    }

    public function testItQueriesPagesOnlyOncePerChannelAndLocale(): void
    {
        $cmsProvider = $this->createMock(CmsPageProviderInterface::class);
        $cmsProvider->expects(self::exactly(2))
            ->method('findEnabledPages')
            ->willReturn([new CmsPageLink('About', 'https://shop.test/about')]);
        $provider = new LlmTxtCmsPagesSectionProvider([$cmsProvider]);
        $channel = new Channel();

        self::assertTrue($provider->supports($channel, 'en_US'));
        self::assertCount(1, $provider->provide($channel, 'en_US'));

        $provider->reset();

        self::assertTrue($provider->supports($channel, 'en_US'));
        self::assertCount(1, $provider->provide($channel, 'en_US'));
    }

    /** @param list<CmsPageLink> $pages */
    private function pages(array $pages): CmsPageProviderInterface
    {
        return new class($pages) implements CmsPageProviderInterface {

            public function __construct(private readonly array $pages)
            {
            }

            public function findEnabledPages(ChannelInterface $channel, string $localeCode): array
            {
                return $this->pages;
            }
        };
    }
}
