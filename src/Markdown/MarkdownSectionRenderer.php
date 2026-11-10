<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Markdown;

use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;

final readonly class MarkdownSectionRenderer
{
    public function __construct(private MarkdownSanitizer $sanitizer)
    {
    }

    /** @return list<string> */
    public function render(MarkdownSection $section): array
    {
        $title = $this->sanitizer->sectionTitle($section->title);
        if ('' === trim($title) || [] === $section->lines) {
            return [];
        }

        return [
            '## ' . $title,
            '',
            ...array_map($this->sanitizer->sectionLine(...), $section->lines),
            '',
        ];
    }

    /**
     * @param iterable<MarkdownSection> $sections
     *
     * @return list<string>
     */
    public function renderAll(iterable $sections): array
    {
        $lines = [];
        foreach ($sections as $section) {
            array_push($lines, ...$this->render($section));
        }

        return $lines;
    }

    /**
     * @template T of object
     *
     * @param iterable<T> $providers
     * @param \Closure(T): bool $supports
     * @param \Closure(T): iterable<MarkdownSection> $provide
     *
     * @return list<string>
     */
    public function renderProvided(iterable $providers, \Closure $supports, \Closure $provide): array
    {
        $lines = [];
        foreach ($providers as $provider) {
            if ($supports($provider)) {
                array_push($lines, ...$this->renderAll($provide($provider)));
            }
        }

        return $lines;
    }
}
