<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Markdown;

use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class MarkdownSanitizerTest extends TestCase
{
    private MarkdownSanitizer $sanitizer;

    #[\Override]
    protected function setUp(): void
    {
        $this->sanitizer = new MarkdownSanitizer();
    }

    #[TestDox('Tags are stripped and line breaks collapsed into spaces')]
    public function test_text_strips_tags_and_line_breaks(): void
    {
        self::assertSame('Hello  world again', $this->sanitizer->text("<b>Hello</b>\n world\r\nagain"));
    }

    #[TestDox('Brackets of link text are escaped')]
    public function test_link_text_escapes_brackets(): void
    {
        self::assertSame('a \\[b\\] c', $this->sanitizer->linkText('a [b] c'));
    }

    #[TestDox('A section title cannot open another heading')]
    public function test_section_title_removes_heading_markers(): void
    {
        self::assertSame('Title', $this->sanitizer->sectionTitle('## # Title'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function sectionLines(): iterable
    {
        yield 'http link kept' => ['- [a](https://shop.example.com/x)', '- [a](https://shop.example.com/x)'];
        yield 'javascript link neutralized' => ['- [a](javascript:alert(1))', '- [a](#)'];
        yield 'data link neutralized' => ['- [a](data:text/html;base64,AAAA)', '- [a](#)'];
        yield 'unknown scheme neutralized' => ['- [a](ftp://example.com/file)', '- [a](#)'];
        yield 'relative link kept' => ['- [a](/fr_FR/products/mug)', '- [a](/fr_FR/products/mug)'];
        yield 'html removed' => ['- <script>x</script>text', '- xtext'];
    }

    #[DataProvider('sectionLines')]
    #[TestDox('The section line "$line" is sanitized')]
    public function test_section_line_neutralizes_unsafe_links(string $line, string $expected): void
    {
        self::assertSame($expected, $this->sanitizer->sectionLine($line));
    }

    #[TestDox('Plain text is trimmed, collapsed and null when empty')]
    public function test_plain_text(): void
    {
        self::assertSame('One two', $this->sanitizer->plainText("  <p>One\n\n two</p> "));
        self::assertNull($this->sanitizer->plainText('  <br>  '));
        self::assertNull($this->sanitizer->plainText(null));
    }
}
