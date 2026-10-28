<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\TaxonMarkdownGenerationEvent;
use ACSEO\SyliusGeoPlugin\Exception\TaxonNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Provider\TaxonMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TaxonMarkdownGenerator implements TaxonMarkdownGeneratorInterface
{
    public function __construct(
        private readonly TaxonGeoVisibilityCheckerInterface $visibilityChecker,
        private readonly GeoUrlBuilderInterface $urlBuilder,
        private readonly bool $includeDescription = true,
        private readonly bool $includeChildren = true,
        /** @var iterable<TaxonMarkdownSectionProviderInterface> */
        private readonly iterable $sectionProviders = [],
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly MarkdownSanitizer $markdownSanitizer = new MarkdownSanitizer(),
    ) {
    }

    public function generate(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): string
    {
        if (!$this->visibilityChecker->isVisible($taxon, $channel)) {
            throw new TaxonNotAvailableForGeoException(\sprintf('Taxon "%s" is not available for GEO export.', (string) $taxon->getCode()));
        }

        $taxon->setCurrentLocale($localeCode);
        $taxon->setFallbackLocale($localeCode);

        $name = trim((string) $taxon->getName());
        if ('' === $name) {
            $name = (string) $taxon->getCode();
        }

        $slug = $taxon->getSlug();
        if (null === $slug || '' === trim($slug)) {
            throw new TaxonNotAvailableForGeoException(\sprintf('Taxon "%s" has no slug for locale "%s".', (string) $taxon->getCode(), $localeCode));
        }

        $lines = [
            '# ' . $this->markdownSanitizer->text($name),
            '',
        ];

        $event = new TaxonMarkdownGenerationEvent($taxon, $channel, $localeCode);
        $this->eventDispatcher?->dispatch($event, GeoEvents::BEFORE_TAXON_MARKDOWN_GENERATION);

        $description = $this->includeDescription ? $this->markdownSanitizer->plainText($taxon->getDescription()) : null;
        if (null !== $description) {
            $lines[] = $description;
            $lines[] = '';
        }

        $children = $this->includeChildren ? $this->visibleChildren($taxon, $channel, $localeCode) : [];
        if ([] !== $children) {
            $lines[] = '## Subcategories';
            $lines[] = '';

            foreach ($children as $child) {
                $childName = trim((string) $child->getName());
                if ('' === $childName) {
                    $childName = (string) $child->getCode();
                }

                $childSlug = $child->getSlug();
                if (null === $childSlug || '' === trim($childSlug)) {
                    continue;
                }

                $lines[] = \sprintf(
                    '- [%s](%s)',
                    $this->markdownSanitizer->linkText($childName),
                    $this->urlBuilder->absolute($channel, $this->urlBuilder->taxonCanonicalPath($localeCode, $childSlug)),
                );
            }

            $lines[] = '';
        }

        array_push($lines, ...$this->markdownSanitizer->renderSections($event->getSections()));
        array_push($lines, ...$this->markdownSanitizer->renderProvidedSections(
            $this->sectionProviders,
            static fn (TaxonMarkdownSectionProviderInterface $provider): bool => $provider->supports($taxon, $channel, $localeCode),
            static fn (TaxonMarkdownSectionProviderInterface $provider): iterable => $provider->provide($taxon, $channel, $localeCode),
        ));

        $lines[] = '## Canonical URL';
        $lines[] = '';
        $lines[] = $this->urlBuilder->absolute(
            $channel,
            $this->urlBuilder->taxonCanonicalPath($localeCode, $slug),
        );
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @return list<TaxonInterface> */
    private function visibleChildren(TaxonInterface $taxon, ChannelInterface $channel, string $localeCode): array
    {
        $children = [];
        foreach ($taxon->getChildren() as $child) {
            if (!$this->visibilityChecker->isVisible($child, $channel)) {
                continue;
            }

            $child->setCurrentLocale($localeCode);
            $child->setFallbackLocale($localeCode);
            $children[] = $child;
        }

        return $children;
    }
}
