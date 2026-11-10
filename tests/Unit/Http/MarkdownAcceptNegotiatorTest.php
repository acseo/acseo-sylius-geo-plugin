<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Http;

use ACSEO\SyliusGeoPlugin\Http\MarkdownAcceptNegotiator;
use ACSEO\SyliusGeoPlugin\Http\Negotiation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class MarkdownAcceptNegotiatorTest extends TestCase
{
    /** @return iterable<string, array{string, Negotiation}> */
    public static function acceptHeaders(): iterable
    {
        yield 'explicit markdown' => ['text/markdown', Negotiation::Markdown];
        yield 'markdown and html with the same quality' => ['text/markdown, text/html', Negotiation::Markdown];
        yield 'markdown ahead of wildcards' => ['text/markdown;q=0.9, */*;q=0.8', Negotiation::Markdown];
        yield 'html preferred by quality' => ['text/markdown;q=0.5, text/html;q=0.9', Negotiation::Html];
        yield 'wildcard only' => ['*/*', Negotiation::Html];
        yield 'type wildcard only' => ['text/*', Negotiation::Html];
        yield 'markdown refused' => ['text/markdown;q=0', Negotiation::Html];
        yield 'markdown refused with html' => ['text/markdown;q=0, text/html', Negotiation::Html];
        yield 'unsupported type only' => ['application/json', Negotiation::NotAcceptable];
        yield 'xhtml counts as html' => ['text/markdown;q=0.4, application/xhtml+xml', Negotiation::Html];
    }

    #[DataProvider('acceptHeaders')]
    #[TestDox('The Accept header "$acceptHeader" is negotiated')]
    public function test_it_negotiates_the_representation(string $acceptHeader, Negotiation $expected): void
    {
        self::assertSame($expected, (new MarkdownAcceptNegotiator())->negotiate($acceptHeader));
    }
}
