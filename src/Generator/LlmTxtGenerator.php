<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\LlmTxtGenerationEvent;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\LlmTxtOptions;
use ACSEO\SyliusGeoPlugin\Provider\LlmTxtSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class LlmTxtGenerator implements LlmTxtGeneratorInterface
{
    public function __construct(
        private readonly ChannelProductCatalogInterface $channelProductCatalog,
        private readonly ChannelTaxonCatalogInterface $channelTaxonCatalog,
        private readonly GeoUrlBuilderInterface $urlBuilder,
        private readonly LlmTxtChannelInfo $channelInfo,
        private readonly LlmTxtOptions $options = new LlmTxtOptions(),
        /** @var iterable<LlmTxtSectionProviderInterface> */
        private readonly iterable $sectionProviders = [],
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly MarkdownSanitizer $markdownSanitizer = new MarkdownSanitizer(),
    ) {
    }

    public function generate(ChannelInterface $channel, string $localeCode): string
    {
        $event = new LlmTxtGenerationEvent($channel, $localeCode);
        $this->eventDispatcher?->dispatch($event, GeoEvents::BEFORE_LLM_TXT_GENERATION);

        $shopName = $this->channelInfo->shopName($channel);

        $lines = [
            '# ' . $this->markdownSanitizer->text($shopName),
            '',
            '> ' . $this->buildSummary($channel, $localeCode),
            '',
            'Use linked Markdown product pages for accurate product facts. Cite the shop name and canonical product URLs. Prefer current prices from those pages; do not invent stock or promotions.',
            '',
        ];

        if ($this->options->includeChannelInfo) {
            $this->appendChannelInfo($lines, $channel, $shopName);
        }

        if ($this->options->includeCrawlerPolicy) {
            $this->appendCrawlerPolicy($lines, $localeCode);
        }

        array_push($lines, ...$this->markdownSanitizer->renderSections($event->getSections()));

        array_push($lines, ...[
            '## Crawler hints',
            '',
            \sprintf('- Locale index: %s', $this->urlBuilder->absolute($channel, $this->urlBuilder->llmTxtPath($localeCode))),
            \sprintf('- Root index redirect: %s', $this->urlBuilder->absolute($channel, $this->urlBuilder->rootLlmTxtPath())),
            \sprintf(
                '- Product Markdown pattern: %s',
                $this->urlBuilder->absolute($channel, $this->urlBuilder->productMarkdownPathPattern($localeCode)),
            ),
            '- List only enabled catalog entries for this channel; do not invent prices or promotions.',
            '',
        ]);

        foreach ($this->sectionProviders as $provider) {
            array_push($lines, ...$this->markdownSanitizer->renderSections($provider->provide($channel, $localeCode)));
        }

        $lines[] = '## Taxons';
        $lines[] = '';

        $linkedTaxons = $this->appendTaxons($lines, $channel, $localeCode);
        if (0 === $linkedTaxons) {
            $lines[] = '- No enabled taxons are currently listed for this channel.';
        }

        $lines[] = '';
        $lines[] = '## Products';
        $lines[] = '';

        $linkedProducts = $this->appendProducts($lines, $channel, $localeCode);
        if (0 === $linkedProducts) {
            $lines[] = '- No enabled products are currently listed for this channel.';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param list<string> $lines */
    private function appendTaxons(array &$lines, ChannelInterface $channel, string $localeCode): int
    {
        $linked = 0;
        foreach ($this->channelTaxonCatalog->findEnabledTaxons($channel, $localeCode) as $taxon) {
            $slug = $this->resolveTaxonSlug($taxon, $localeCode);
            if (null === $slug) {
                continue;
            }

            $name = trim((string) $taxon->getName());
            if ('' === $name) {
                $name = (string) $taxon->getCode();
            }

            $url = $this->urlBuilder->absolute(
                $channel,
                $this->urlBuilder->taxonCanonicalPath($localeCode, $slug),
            );

            $lines[] = \sprintf('- [%s](%s)', $this->markdownSanitizer->linkText($name), $url);
            ++$linked;
        }

        return $linked;
    }

    /** @param list<string> $lines */
    private function appendProducts(array &$lines, ChannelInterface $channel, string $localeCode): int
    {
        $linked = 0;
        foreach ($this->channelProductCatalog->findEnabledProducts($channel, $localeCode) as $product) {
            $slug = $this->resolveProductSlug($product, $localeCode);
            if (null === $slug) {
                continue;
            }

            $name = trim((string) $product->getName());
            if ('' === $name) {
                $name = (string) $product->getCode();
            }

            $markdownUrl = $this->urlBuilder->absolute(
                $channel,
                $this->urlBuilder->productMarkdownPath($localeCode, $slug),
            );

            $description = $this->shortDescription($product);
            if (null !== $description) {
                $lines[] = \sprintf('- [%s](%s): %s', $this->markdownSanitizer->linkText($name), $markdownUrl, $this->markdownSanitizer->text($description));
            } else {
                $lines[] = \sprintf('- [%s](%s)', $this->markdownSanitizer->linkText($name), $markdownUrl);
            }

            ++$linked;
        }

        return $linked;
    }

    /** @param list<string> $lines */
    private function appendChannelInfo(array &$lines, ChannelInterface $channel, string $shopName): void
    {
        $lines[] = '## Channel';
        $lines[] = '';
        $lines[] = \sprintf('- Shop name: %s', $shopName);
        $lines[] = \sprintf('- Channel code: %s', (string) $channel->getCode());

        $baseUrl = $this->channelInfo->baseUrl($channel);
        if (null !== $baseUrl) {
            $lines[] = \sprintf('- Base URL: %s', $baseUrl);
        }

        $currencies = $this->channelInfo->currencies($channel);
        if ([] !== $currencies) {
            $lines[] = \sprintf('- Currencies: %s', implode(', ', $currencies));
        }

        $locales = $this->channelInfo->locales($channel);
        if ([] !== $locales) {
            $lines[] = \sprintf('- Locales: %s', implode(', ', $locales));
        }

        $servedCountries = $this->channelInfo->servedCountries($channel);
        if ([] !== $servedCountries) {
            $lines[] = \sprintf('- Served countries: %s', implode(', ', $servedCountries));
        }

        $lines[] = '';
    }

    /** @param list<string> $lines */
    private function appendCrawlerPolicy(array &$lines, string $localeCode): void
    {
        $lines[] = '## Crawler policy';
        $lines[] = '';
        $lines[] = '- AI crawler access: ' . ($this->options->allowAiCrawlers ? 'allowed for public GEO endpoints' : 'not allowed; do not crawl or use GEO endpoints');

        foreach ($this->options->crawlerHintsFor($localeCode) as $hint) {
            $hint = trim($hint);
            if ('' === $hint) {
                continue;
            }

            $lines[] = '- ' . $this->markdownSanitizer->text($hint);
        }

        $lines[] = '';
    }

    private function buildSummary(ChannelInterface $channel, string $localeCode): string
    {
        $parts = [
            \sprintf('Sylius shop channel %s.', (string) $channel->getCode()),
            \sprintf('Locale: %s.', $localeCode),
        ];

        $currency = $channel->getBaseCurrency();
        if ($currency instanceof CurrencyInterface && null !== $currency->getCode()) {
            $parts[] = \sprintf('Currency: %s.', $currency->getCode());
        }

        $localeCodes = $this->channelInfo->channelLocales($channel);
        if ([] !== $localeCodes) {
            $parts[] = \sprintf('Available locales: %s.', implode(', ', $localeCodes));
        }

        return implode(' ', $parts);
    }

    private function resolveProductSlug(ProductInterface $product, string $localeCode): ?string
    {
        $product->setCurrentLocale($localeCode);
        $product->setFallbackLocale($localeCode);

        $slug = $product->getSlug();
        if (null === $slug || '' === trim($slug)) {
            return null;
        }

        return $slug;
    }

    private function resolveTaxonSlug(TaxonInterface $taxon, string $localeCode): ?string
    {
        $taxon->setCurrentLocale($localeCode);
        $taxon->setFallbackLocale($localeCode);

        $slug = $taxon->getSlug();
        if (null === $slug || '' === trim($slug)) {
            return null;
        }

        return $slug;
    }

    private function shortDescription(ProductInterface $product): ?string
    {
        $text = $this->markdownSanitizer->plainText($product->getShortDescription() ?? $product->getDescription());
        if (null === $text) {
            return null;
        }

        if (mb_strlen($text) > 120) {
            return mb_substr($text, 0, 117) . '...';
        }

        return $text;
    }
}
