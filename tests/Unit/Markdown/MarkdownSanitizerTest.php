<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Markdown;

use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use PHPUnit\Framework\TestCase;

final class MarkdownSanitizerTest extends TestCase
{
    private MarkdownSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new MarkdownSanitizer();
    }

    public function testItStripsTagsAndNormalizesText(): void
    {
        self::assertSame('Hello world', $this->sanitizer->text("<strong>Hello</strong>\nworld"));
    }

    public function testItEscapesMarkdownLinkText(): void
    {
        self::assertSame('A \\[safe\\] title', $this->sanitizer->linkText('A [safe] title'));
    }

    public function testItSanitizesSectionTitles(): void
    {
        self::assertSame('Title', $this->sanitizer->sectionTitle('### Title'));
    }

    public function testItKeepsHttpLinksAndNeutralizesUnsafeSchemes(): void
    {
        self::assertSame(
            '[safe](https://example.test) [unsafe](#) [relative](/product)',
            $this->sanitizer->sectionLine('[safe](https://example.test) [unsafe](javascript:alert(1)) [relative](/product)'),
        );
    }

    public function testItConvertsHtmlToPlainText(): void
    {
        self::assertSame('Nice mug in blue', $this->sanitizer->plainText("<p>Nice  mug</p>\n<p>in blue</p>"));
        self::assertNull($this->sanitizer->plainText(null));
        self::assertNull($this->sanitizer->plainText("<p> \n </p>"));
    }

    public function testItRendersASanitizedSection(): void
    {
        $section = new MarkdownSection('## Shipping', ['<b>Free</b> over 50', '[doc](javascript:alert(1))']);

        self::assertSame(
            ['## Shipping', '', 'Free over 50', '[doc](#)', ''],
            $this->sanitizer->renderSection($section),
        );
    }

    public function testItRendersNothingForEmptySections(): void
    {
        self::assertSame([], $this->sanitizer->renderSection(new MarkdownSection('Title', [])));
        self::assertSame([], $this->sanitizer->renderSection(new MarkdownSection('###', ['line'])));
    }

    public function testItRendersSeveralSectionsAndSkipsEmptyOnes(): void
    {
        $lines = $this->sanitizer->renderSections([
            new MarkdownSection('One', ['a']),
            new MarkdownSection('Empty', []),
            new MarkdownSection('Two', ['b']),
        ]);

        self::assertSame(['## One', '', 'a', '', '## Two', '', 'b', ''], $lines);
    }
}
