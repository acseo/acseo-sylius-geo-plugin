<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Markdown;

final class MarkdownDocument
{
    /** @var list<string> */
    private array $lines = [];

    public function __construct(private readonly MarkdownSanitizer $sanitizer)
    {
    }

    public function title(string $title): self
    {
        return $this->append(['# ' . $this->sanitizer->text($title), '']);
    }

    public function paragraph(string $text): self
    {
        return $this->append([$text, '']);
    }

    public function quote(string $text): self
    {
        return $this->append(['> ' . $text, '']);
    }

    /** @param list<string> $lines */
    public function append(array $lines): self
    {
        array_push($this->lines, ...$lines);

        return $this;
    }

    public function toString(): string
    {
        return implode("\n", $this->lines);
    }
}
