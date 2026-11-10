<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Behat\Step\Given;
use Behat\Step\When;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Webmozart\Assert\Assert;

final class GeoRequestContext implements Context
{
    private ?string $browsedChannelCode = null;

    /** @param ChannelRepositoryInterface<ChannelInterface> $channelRepository */
    public function __construct(
        private readonly Session $minkSession,
        private readonly SharedStorageInterface $sharedStorage,
        private readonly ChannelRepositoryInterface $channelRepository,
    ) {
    }

    #[Given('I browse the shop through the :channelName channel')]
    public function iBrowseTheShopThroughTheChannel(string $channelName): void
    {
        $channel = $this->channelRepository->findOneBy(['name' => $channelName]);
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $this->browsedChannelCode = $channel->getCode();
        $this->sharedStorage->set('channel', $channel);
    }

    #[When('I request the public GEO llms.txt index')]
    public function iRequestThePublicGeoLlmTxtIndex(): void
    {
        $this->visit('/llms.txt');
    }

    #[When('I request the public GEO shop API llm-txt endpoint')]
    public function iRequestThePublicGeoShopApiLlmTxtEndpoint(): void
    {
        $this->visit('/api/v2/shop/geo/llm-txt');
    }

    #[When('I request the public GEO llms.txt index for the :localeCode locale')]
    public function iRequestThePublicGeoLlmTxtIndexForTheLocale(string $localeCode): void
    {
        $this->visit(\sprintf('/%s/llms.txt', rawurlencode($localeCode)));
    }

    #[When('I request the public GEO sitemap for the :localeCode locale')]
    public function iRequestThePublicGeoSitemapForTheLocale(string $localeCode): void
    {
        $this->visit(\sprintf('/%s/geo/sitemap.xml', rawurlencode($localeCode)));
    }

    #[When('I request the GEO Markdown page for the :product product')]
    public function iRequestTheGeoMarkdownPageForTheProduct(ProductInterface $product): void
    {
        $this->visit(\sprintf('/%s/geo/products/%s.md', rawurlencode($this->localeCode()), rawurlencode($this->productSlug($product))));
    }

    #[When('I request the GEO Markdown page for the unknown :slug product')]
    public function iRequestTheGeoMarkdownPageForTheUnknownProduct(string $slug): void
    {
        $this->visit(\sprintf('/%s/geo/products/%s.md', rawurlencode($this->localeCode()), rawurlencode($slug)));
    }

    #[When('I request the GEO shop API Markdown for the :product product')]
    public function iRequestTheGeoShopApiMarkdownForTheProduct(ProductInterface $product): void
    {
        $this->visit(\sprintf('/api/v2/shop/geo/products/%s', rawurlencode($this->productSlug($product))));
    }

    #[When('I request the GEO shop API Markdown for the unknown :slug product')]
    public function iRequestTheGeoShopApiMarkdownForTheUnknownProduct(string $slug): void
    {
        $this->visit(\sprintf('/api/v2/shop/geo/products/%s', rawurlencode($slug)));
    }

    #[When('I request the canonical page of the :product product accepting :accept')]
    public function iRequestTheCanonicalPageOfTheProductAccepting(ProductInterface $product, string $accept): void
    {
        $this->minkSession->setRequestHeader('Accept', $accept);
        $this->visit(\sprintf('/%s/products/%s', rawurlencode($this->localeCode()), rawurlencode($this->productSlug($product))));
    }

    #[When('/^I request the canonical page of the ("[^"]+" taxon) accepting "([^"]+)"$/')]
    public function iRequestTheCanonicalPageOfTheTaxonAccepting(TaxonInterface $taxon, string $accept): void
    {
        $taxon->setCurrentLocale($this->localeCode());
        $taxon->setFallbackLocale($this->localeCode());
        $slug = $taxon->getSlug();
        Assert::stringNotEmpty($slug);

        $this->minkSession->setRequestHeader('Accept', $accept);
        $this->visit(\sprintf('/%s/taxons/%s', rawurlencode($this->localeCode()), implode('/', array_map(rawurlencode(...), explode('/', $slug)))));
    }

    #[When('I request the same GEO page again with its ETag')]
    public function iRequestTheSameGeoPageAgainWithItsEtag(): void
    {
        $etag = $this->minkSession->getResponseHeader('ETag');
        Assert::stringNotEmpty($etag);

        $this->minkSession->setRequestHeader('If-None-Match', $etag);
        $this->minkSession->visit($this->minkSession->getCurrentUrl());
    }

    private function visit(string $path): void
    {
        if (null !== $this->browsedChannelCode) {
            $path .= (str_contains($path, '?') ? '&' : '?') . '_channel_code=' . rawurlencode($this->browsedChannelCode);
        }

        $this->minkSession->visit($path);
    }

    private function localeCode(): string
    {
        $channel = $this->sharedStorage->get('channel');
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $localeCode = $channel->getDefaultLocale()?->getCode();
        Assert::stringNotEmpty($localeCode);

        return $localeCode;
    }

    private function productSlug(ProductInterface $product): string
    {
        $product->setCurrentLocale($this->localeCode());
        $product->setFallbackLocale($this->localeCode());
        $slug = $product->getSlug();
        Assert::stringNotEmpty($slug);

        return $slug;
    }
}
