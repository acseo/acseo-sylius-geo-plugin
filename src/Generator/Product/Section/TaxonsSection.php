<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product\Section;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownContext;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownSectionBuilderInterface;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 40)]
final readonly class TaxonsSection implements ProductMarkdownSectionBuilderInterface
{
    public function __construct(
        private ProductMarkdownOptions $options,
        private TaxonGeoVisibilityCheckerInterface $visibilityChecker,
        private GeoUrlBuilderInterface $urlBuilder,
        private ResourceLocalizer $localizer,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    #[\Override]
    public function build(ProductMarkdownContext $context): ?MarkdownSection
    {
        if (!$this->options->includeTaxons) {
            return null;
        }

        $lines = [];
        foreach ($context->product->getProductTaxons() as $productTaxon) {
            $taxon = $productTaxon->getTaxon();
            if (null === $taxon || !$this->visibilityChecker->isVisible($taxon, $context->channel)) {
                continue;
            }

            $slug = $this->localizer->taxonSlug($taxon, $context->localeCode);
            if (null !== $slug) {
                $lines[] = \sprintf(
                    '- [%s](%s)',
                    $this->sanitizer->linkText($this->localizer->taxonName($taxon, $context->localeCode)),
                    $this->urlBuilder->absolute($context->channel, $this->urlBuilder->taxonCanonicalPath($context->localeCode, $slug)),
                );
            }
        }

        return new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.product.taxons'), $lines);
    }
}
