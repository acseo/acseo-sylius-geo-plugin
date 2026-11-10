<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Provider;

use ACSEO\SyliusGeoPlugin\Provider\CmsPageProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\LlmTxtSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\ProductMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\TaxonMarkdownSectionProviderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

final class ExtensionTagsTest extends TestCase
{
    /** @return iterable<string, array{class-string, string}> */
    public static function extensionInterfaces(): iterable
    {
        yield 'product section provider' => [ProductMarkdownSectionProviderInterface::class, 'acseo_sylius_geo_plugin.product_markdown_section_provider'];
        yield 'taxon section provider' => [TaxonMarkdownSectionProviderInterface::class, 'acseo_sylius_geo_plugin.taxon_markdown_section_provider'];
        yield 'llms.txt section provider' => [LlmTxtSectionProviderInterface::class, 'acseo_sylius_geo_plugin.llm_txt_section_provider'];
        yield 'CMS page provider' => [CmsPageProviderInterface::class, 'acseo_sylius_geo_plugin.cms_page_provider'];
    }

    /** @param class-string $interface */
    #[DataProvider('extensionInterfaces')]
    #[TestDox('Implementations of $interface are autoconfigured with the documented tag')]
    public function test_extension_interfaces_are_autoconfigured_with_the_documented_tag(string $interface, string $tag): void
    {
        $attributes = (new \ReflectionClass($interface))->getAttributes(AutoconfigureTag::class);

        self::assertCount(1, $attributes);
        self::assertSame($tag, $attributes[0]->getArguments()[0] ?? null);
    }
}
