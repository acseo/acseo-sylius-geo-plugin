<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator;

use ACSEO\SyliusGeoPlugin\Event\GeoEvents;
use ACSEO\SyliusGeoPlugin\Event\LlmTxtGenerationEvent;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\ChannelSection;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\CrawlerHintsSection;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\CrawlerPolicySection;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\LlmTxtHeader;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\ProductsSection;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxt\TaxonsSection;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSectionRenderer;
use ACSEO\SyliusGeoPlugin\Provider\LlmTxtSectionProviderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class LlmTxtGenerator implements LlmTxtGeneratorInterface
{
    /** @param iterable<LlmTxtSectionProviderInterface> $sectionProviders */
    public function __construct(
        private LlmTxtHeader $header,
        private ChannelSection $channelSection,
        private CrawlerPolicySection $policySection,
        private CrawlerHintsSection $hintsSection,
        private TaxonsSection $taxonsSection,
        private ProductsSection $productsSection,
        private MarkdownSectionRenderer $renderer,
        private EventDispatcherInterface $eventDispatcher,
        private iterable $sectionProviders,
    ) {
    }

    #[\Override]
    public function generate(ChannelInterface $channel, string $localeCode): string
    {
        $event = new LlmTxtGenerationEvent($channel, $localeCode);
        $this->eventDispatcher->dispatch($event, GeoEvents::BEFORE_LLM_TXT_GENERATION);

        return $this->header->start($channel, $localeCode)
            ->append($this->renderer->renderAll(array_filter([
                $this->channelSection->build($channel),
                $this->policySection->build($localeCode),
            ])))
            ->append($this->renderer->renderAll($event->getSections()))
            ->append($this->renderer->render($this->hintsSection->build($channel, $localeCode)))
            ->append($this->renderer->renderProvided(
                $this->sectionProviders,
                static fn (LlmTxtSectionProviderInterface $provider): bool => $provider->supports($channel, $localeCode),
                static fn (LlmTxtSectionProviderInterface $provider): array => $provider->provide($channel, $localeCode),
            ))
            ->append($this->renderer->renderAll([
                $this->taxonsSection->build($channel, $localeCode),
                $this->productsSection->build($channel, $localeCode),
            ]))
            ->toString();
    }
}
