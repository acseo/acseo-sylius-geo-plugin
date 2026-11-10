<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Session;
use Behat\Step\Then;
use Webmozart\Assert\Assert;

final readonly class GeoResponseContext implements Context
{
    public function __construct(private Session $minkSession)
    {
    }

    #[Then('the GEO response status code should be :statusCode')]
    public function theGeoResponseStatusCodeShouldBe(int $statusCode): void
    {
        Assert::same($this->minkSession->getStatusCode(), $statusCode);
    }

    #[Then('the GEO response content type should contain :contentType')]
    public function theGeoResponseContentTypeShouldContain(string $contentType): void
    {
        $this->theGeoResponseHeaderShouldContain('Content-Type', $contentType);
    }

    #[Then('the GEO response header :name should contain :value')]
    public function theGeoResponseHeaderShouldContain(string $name, string $value): void
    {
        $headerValue = $this->minkSession->getResponseHeader($name);
        Assert::string($headerValue, \sprintf('Header "%s" is missing.', $name));
        Assert::contains(strtolower($headerValue), strtolower($value));
    }

    #[Then('the GEO response should have an ETag header')]
    public function theGeoResponseShouldHaveAnEtagHeader(): void
    {
        Assert::stringNotEmpty($this->minkSession->getResponseHeader('ETag'));
    }

    #[Then('the GEO response should contain :text')]
    public function theGeoResponseShouldContain(string $text): void
    {
        Assert::contains($this->minkSession->getPage()->getContent(), $text);
    }

    #[Then('the GEO response should not contain :text')]
    public function theGeoResponseShouldNotContain(string $text): void
    {
        Assert::notContains($this->minkSession->getPage()->getContent(), $text);
    }
}
