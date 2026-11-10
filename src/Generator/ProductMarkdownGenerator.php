<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\ProductMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Exception\ProductNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductJsonLdBuilder;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownContext;
use ACSEO\SyliusGeoPlugin\Generator\Product\ProductMarkdownSectionBuilderInterface;
use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownDocument;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSectionRenderer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use ACSEO\SyliusGeoPlugin\Provider\ProductMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ProductMarkdownGenerator implements ProductMarkdownGeneratorInterface
{
    /**
     * @param iterable<ProductMarkdownSectionBuilderInterface> $sectionBuilders
     * @param iterable<ProductMarkdownSectionProviderInterface> $sectionProviders
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        private ProductGeoVisibilityCheckerInterface $visibilityChecker,
        private ResourceLocalizer $localizer,
        private GeoUrlBuilderInterface $urlBuilder,
        private ProductJsonLdBuilder $jsonLdBuilder,
        private MarkdownSectionRenderer $renderer,
        private MarkdownSanitizer $sanitizer,
        private CrawlerTextTranslator $texts,
        private EventDispatcherInterface $eventDispatcher,
        private iterable $sectionBuilders,
        private iterable $sectionProviders,
    ) {
    }

    #[\Override]
    public function generate(ProductInterface $product, ChannelInterface $channel, string $localeCode): string
    {
        if (!$this->visibilityChecker->isVisible($product, $channel)) {
            throw new ProductNotAvailableForGeoException();
        }

        $slug = $this->localizer->productSlug($product, $localeCode) ?? throw new ProductNotAvailableForGeoException();

        $event = new ProductMarkdownGenerationEvent($product, $channel, $localeCode);
        $this->eventDispatcher->dispatch($event, GeoEvents::BEFORE_PRODUCT_MARKDOWN_GENERATION);

        return $this->document(new ProductMarkdownContext($product, $channel, $localeCode), $event, $slug)->toString();
    }

    private function document(ProductMarkdownContext $context, ProductMarkdownGenerationEvent $event, string $slug): MarkdownDocument
    {
        $name = $this->localizer->productName($context->product, $context->localeCode);
        $description = $this->sanitizer->plainText($context->product->getDescription() ?? $context->product->getShortDescription());
        $canonicalUrl = $this->urlBuilder->absolute($context->channel, $this->urlBuilder->productCanonicalPath($context->localeCode, $slug));

        $document = (new MarkdownDocument($this->sanitizer))->title($name);
        if (null !== $description) {
            $document->paragraph($description);
        }

        return $document
            ->append($this->renderer->renderAll($this->builtInSections($context)))
            ->append($this->renderer->renderAll($event->getSections()))
            ->append($this->renderer->renderProvided(
                $this->sectionProviders,
                static fn (ProductMarkdownSectionProviderInterface $provider): bool => $provider->supports($context->product, $context->channel, $context->localeCode),
                static fn (ProductMarkdownSectionProviderInterface $provider): array => $provider->provide($context->product, $context->channel, $context->localeCode),
            ))
            ->append($this->renderer->render(new MarkdownSection($this->texts->translate('acseo_sylius_geo_plugin.output.product.canonical_url'), [$canonicalUrl])))
            ->append($this->jsonLdBuilder->build($context, $name, $description, $canonicalUrl));
    }

    /** @return list<MarkdownSection> */
    private function builtInSections(ProductMarkdownContext $context): array
    {
        $sections = [];
        foreach ($this->sectionBuilders as $builder) {
            $section = $builder->build($context);
            if (null !== $section) {
                $sections[] = $section;
            }
        }

        return $sections;
    }
}
