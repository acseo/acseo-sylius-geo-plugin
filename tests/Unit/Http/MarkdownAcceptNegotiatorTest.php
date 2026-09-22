<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Http;

use ACSEO\SyliusGeoPlugin\Http\MarkdownAcceptNegotiator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarkdownAcceptNegotiatorTest extends TestCase
{
    #[DataProvider('provideAcceptHeaders')]
    public function testItNegotiatesTheRepresentation(string $accept, string $expected): void
    {
        self::assertSame($expected, (new MarkdownAcceptNegotiator())->negotiate($accept));
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideAcceptHeaders(): iterable
    {
        yield 'markdown' => ['text/markdown', MarkdownAcceptNegotiator::MARKDOWN];
        yield 'markdown over wildcard html' => ['text/markdown;q=0.9,*/*;q=0.8', MarkdownAcceptNegotiator::MARKDOWN];
        yield 'markdown and html at equal quality' => ['text/markdown, text/html', MarkdownAcceptNegotiator::MARKDOWN];
        yield 'html preferred by quality' => ['text/markdown;q=0.5,text/html;q=0.9', MarkdownAcceptNegotiator::HTML];
        yield 'any type' => ['*/*', MarkdownAcceptNegotiator::HTML];
        yield 'text wildcard' => ['text/*', MarkdownAcceptNegotiator::HTML];
        yield 'browser' => ['text/html,application/xhtml+xml,*/*;q=0.8', MarkdownAcceptNegotiator::HTML];
        yield 'markdown refused' => ['text/markdown;q=0', MarkdownAcceptNegotiator::HTML];
        yield 'markdown refused with html' => ['text/markdown;q=0, text/html', MarkdownAcceptNegotiator::HTML];
        yield 'unsupported type' => ['application/json', MarkdownAcceptNegotiator::NOT_ACCEPTABLE];
        yield 'unsupported type and markdown refused' => ['application/json, text/markdown;q=0', MarkdownAcceptNegotiator::NOT_ACCEPTABLE];
    }
}
