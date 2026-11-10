<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\LlmTxt;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

final readonly class ProductsSection
{
    private const int DESCRIPTION_LIMIT = 120;

    public function __construct(
        private ChannelProductCatalogInterface $catalog,
        private GeoUrlBuilderInterface $urlBuilder,
        private ResourceLocalizer $localizer,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    public function build(ChannelInterface $channel, string $localeCode): MarkdownSection
    {
        $lines = [];
        foreach ($this->catalog->findEnabledProducts($channel, $localeCode) as $product) {
            $slug = $this->localizer->productSlug($product, $localeCode);
            if (null !== $slug) {
                $lines[] = $this->line($product, $channel, $localeCode, $slug);
            }
        }

        return new MarkdownSection(
            $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.products.title'),
            [] === $lines ? ['- ' . $this->texts->translate('acseo_sylius_geo_plugin.output.llm_txt.products.empty')] : $lines,
        );
    }

    private function line(ProductInterface $product, ChannelInterface $channel, string $localeCode, string $slug): string
    {
        $line = \sprintf(
            '- [%s](%s)',
            $this->sanitizer->linkText($this->localizer->productName($product, $localeCode)),
            $this->urlBuilder->absolute($channel, $this->urlBuilder->productMarkdownPath($localeCode, $slug)),
        );
        $description = $this->shortDescription($product);

        return null === $description ? $line : $line . ': ' . $this->sanitizer->text($description);
    }

    private function shortDescription(ProductInterface $product): ?string
    {
        $text = $this->sanitizer->plainText($product->getShortDescription() ?? $product->getDescription());

        return null !== $text && mb_strlen($text) > self::DESCRIPTION_LIMIT ? mb_substr($text, 0, self::DESCRIPTION_LIMIT - 3) . '...' : $text;
    }
}
