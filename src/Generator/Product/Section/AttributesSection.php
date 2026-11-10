<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product\Section;

use ACSEO\SyliusGeoPlugin\Generator\Product\AttributeValueFormatter;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductBrandResolver;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownContext;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownSectionBuilderInterface;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 70)]
final readonly class AttributesSection implements ProductMarkdownSectionBuilderInterface
{
    public function __construct(
        private ProductMarkdownOptions $options,
        private ProductBrandResolver $brandResolver,
        private AttributeValueFormatter $formatter,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
    ) {
    }

    #[\Override]
    public function build(ProductMarkdownContext $context): ?MarkdownSection
    {
        if (!$this->options->includeAttributes) {
            return null;
        }

        $lines = [];
        foreach ($context->product->getAttributesByLocale($context->localeCode, $context->localeCode) as $attribute) {
            $name = trim((string) ($attribute->getName() ?? $attribute->getCode()));
            $value = $this->formatter->forLocale($attribute, $context->localeCode);
            if (!$this->brandResolver->isBrandAttribute($attribute) && '' !== $name && null !== $value) {
                $lines[] = \sprintf('- %s: %s', $this->sanitizer->text($name), $this->sanitizer->text($value));
            }
        }

        return new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.product.attributes'), $lines);
    }
}
