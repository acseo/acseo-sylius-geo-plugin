<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Attribute\Factory\AttributeFactoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class GeoHttpContext implements Context
{
    private ?string $browsedChannelCode = null;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param FactoryInterface<ProductAttributeValueInterface> $attributeValueFactory
     */
    public function __construct(
        private readonly Session $minkSession,
        private readonly SharedStorageInterface $sharedStorage,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly AttributeFactoryInterface $attributeFactory,
        private readonly FactoryInterface $attributeValueFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @Given I browse the shop through the :channelName channel */
    public function iBrowseTheShopThroughTheChannel(string $channelName): void
    {
        $channel = $this->channelRepository->findOneBy(['name' => $channelName]);
        Assert::isInstanceOf($channel, ChannelInterface::class, \sprintf('Channel "%s" does not exist.', $channelName));

        $this->browsedChannelCode = $channel->getCode();
        $this->sharedStorage->set('channel', $channel);
    }

    /** @Given this product has the text attribute :code with value :value */
    public function thisProductHasTheTextAttributeWithValue(string $code, string $value): void
    {
        $product = $this->sharedStorage->get('product');
        Assert::isInstanceOf($product, ProductInterface::class);

        $attribute = $this->entityManager->getRepository($this->attributeFactory->createTyped('text')::class)->findOneBy(['code' => $code]);
        if (null === $attribute) {
            $attribute = $this->attributeFactory->createTyped('text');
            $attribute->setCode($code);
            $attribute->setName($code);
            $attribute->setTranslatable(false);
            $this->entityManager->persist($attribute);
        }

        $attributeValue = $this->attributeValueFactory->createNew();
        $attributeValue->setAttribute($attribute);
        $attributeValue->setValue($value);
        $product->addAttribute($attributeValue);

        $this->entityManager->flush();
    }

    /** @When I request the public GEO llm.txt index */
    public function iRequestThePublicGeoLlmTxtIndex(): void
    {
        $this->visit('/llm.txt');
    }

    /** @When I request the public GEO shop API llm-txt endpoint */
    public function iRequestThePublicGeoShopApiLlmTxtEndpoint(): void
    {
        $this->visit('/api/v2/shop/geo/llm-txt');
    }

    /** @When I request the GEO Markdown page for the :product product */
    public function iRequestTheGeoMarkdownPageForTheProduct(ProductInterface $product): void
    {
        $localeCode = $this->resolveLocaleCode();
        $product->setCurrentLocale($localeCode);
        $product->setFallbackLocale($localeCode);

        $slug = $product->getSlug();
        Assert::stringNotEmpty($slug, 'Product slug is required to request GEO Markdown.');

        $this->visit(\sprintf('/%s/geo/products/%s.md', rawurlencode($localeCode), rawurlencode($slug)));
    }

    /** @When I request the GEO shop API Markdown for the :product product */
    public function iRequestTheGeoShopApiMarkdownForTheProduct(ProductInterface $product): void
    {
        $localeCode = $this->resolveLocaleCode();
        $product->setCurrentLocale($localeCode);
        $product->setFallbackLocale($localeCode);

        $slug = $product->getSlug();
        Assert::stringNotEmpty($slug, 'Product slug is required to request GEO Markdown.');

        $this->visit(\sprintf('/api/v2/shop/geo/products/%s', rawurlencode($slug)));
    }

    /** @When I request the public GEO llm.txt index for the :localeCode locale */
    public function iRequestThePublicGeoLlmTxtIndexForTheLocale(string $localeCode): void
    {
        $this->visit(\sprintf('/%s/llm.txt', rawurlencode($localeCode)));
    }

    /** @When I request the public GEO sitemap for the :localeCode locale */
    public function iRequestThePublicGeoSitemapForTheLocale(string $localeCode): void
    {
        $this->visit(\sprintf('/%s/geo/sitemap.xml', rawurlencode($localeCode)));
    }

    /** @When I request the GEO Markdown page for the unknown :slug product */
    public function iRequestTheGeoMarkdownPageForTheUnknownProduct(string $slug): void
    {
        $this->visit(\sprintf('/%s/geo/products/%s.md', rawurlencode($this->resolveLocaleCode()), rawurlencode($slug)));
    }

    /** @When I request the GEO shop API Markdown for the unknown :slug product */
    public function iRequestTheGeoShopApiMarkdownForTheUnknownProduct(string $slug): void
    {
        $this->visit(\sprintf('/api/v2/shop/geo/products/%s', rawurlencode($slug)));
    }

    /** @When I request the canonical page of the :product product accepting :accept */
    public function iRequestTheCanonicalPageOfTheProductAccepting(ProductInterface $product, string $accept): void
    {
        $localeCode = $this->resolveLocaleCode();
        $product->setCurrentLocale($localeCode);
        $product->setFallbackLocale($localeCode);

        $slug = $product->getSlug();
        Assert::stringNotEmpty($slug, 'Product slug is required to request the canonical page.');

        $this->minkSession->setRequestHeader('Accept', $accept);
        $this->visit(\sprintf('/%s/products/%s', rawurlencode($localeCode), rawurlencode($slug)));
    }

    /** @When I request the same GEO page again with its ETag */
    public function iRequestTheSameGeoPageAgainWithItsEtag(): void
    {
        $url = $this->minkSession->getCurrentUrl();
        $etag = $this->resolveHeaderValue($this->minkSession->getResponseHeaders(), 'etag');
        Assert::notNull($etag, 'The previous response has no ETag header.');

        $this->minkSession->setRequestHeader('If-None-Match', $etag);
        $this->visit($url);
    }

    /** @Then the GEO response header :name should contain :value */
    public function theGeoResponseHeaderShouldContain(string $name, string $value): void
    {
        $headerValue = $this->resolveHeaderValue($this->minkSession->getResponseHeaders(), $name);
        Assert::notNull($headerValue, \sprintf('Header "%s" is missing.', $name));
        Assert::contains(strtolower($headerValue), strtolower($value));
    }

    /** @Then the GEO response should have an ETag header */
    public function theGeoResponseShouldHaveAnEtagHeader(): void
    {
        Assert::notNull($this->resolveHeaderValue($this->minkSession->getResponseHeaders(), 'etag'), 'ETag header is missing.');
    }

    /** @Then the GEO response status code should be :statusCode */
    public function theGeoResponseStatusCodeShouldBe(int $statusCode): void
    {
        Assert::same($this->minkSession->getStatusCode(), $statusCode);
    }

    /** @Then the GEO response content type should contain :contentType */
    public function theGeoResponseContentTypeShouldContain(string $contentType): void
    {
        $headers = $this->minkSession->getResponseHeaders();
        $headerValue = $this->resolveHeaderValue($headers, 'content-type');
        Assert::notNull($headerValue, 'Content-Type header is missing.');
        Assert::contains(strtolower($headerValue), strtolower($contentType));
    }

    /** @Then the GEO response should contain :text */
    public function theGeoResponseShouldContain(string $text): void
    {
        Assert::contains($this->minkSession->getPage()->getContent(), $text);
    }

    /** @Then the GEO response should not contain :text */
    public function theGeoResponseShouldNotContain(string $text): void
    {
        Assert::notContains($this->minkSession->getPage()->getContent(), $text);
    }

    private function visit(string $path): void
    {
        if (null !== $this->browsedChannelCode && !str_contains($path, '_channel_code=')) {
            $path .= (str_contains($path, '?') ? '&' : '?') . '_channel_code=' . rawurlencode($this->browsedChannelCode);
        }

        $this->minkSession->visit($path);
    }

    private function resolveLocaleCode(): string
    {
        $channel = $this->sharedStorage->get('channel');
        Assert::isInstanceOf($channel, ChannelInterface::class);

        $defaultLocale = $channel->getDefaultLocale();
        Assert::isInstanceOf($defaultLocale, LocaleInterface::class);
        Assert::notNull($defaultLocale->getCode());

        return $defaultLocale->getCode();
    }

    /** @param array<string, mixed> $headers */
    private function resolveHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $headerName => $value) {
            if (strtolower((string) $headerName) !== strtolower($name)) {
                continue;
            }

            if (\is_array($value)) {
                return isset($value[0]) ? (string) $value[0] : null;
            }

            return (string) $value;
        }

        return null;
    }
}
