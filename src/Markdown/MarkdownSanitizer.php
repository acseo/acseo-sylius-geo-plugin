<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Markdown;

use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;

final class MarkdownSanitizer
{
    public function text(string $value): string
    {
        return str_replace(["\r\n", "\r", "\n"], ' ', strip_tags($value));
    }

    public function linkText(string $value): string
    {
        return str_replace(['[', ']'], ['\\[', '\\]'], $this->text($value));
    }

    public function sectionTitle(string $value): string
    {
        return ltrim($this->text($value), '# ');
    }

    public function sectionLine(string $value): string
    {
        $line = $this->text($value);
        $line = preg_replace('/\]\(\s*(?:javascript|data|vbscript):[^\s]*\)/i', '](#)', $line) ?? '';

        return preg_replace_callback(
            '/\]\(([^)\s]+)\)/',
            static function (array $matches): string {
                $url = $matches[1];
                $scheme = parse_url($url, \PHP_URL_SCHEME);
                if (!\is_string($scheme) || \in_array(mb_strtolower($scheme), ['http', 'https'], true)) {
                    return '](' . $url . ')';
                }

                return '](#)';
            },
            $line,
        ) ?? '';
    }

    public function plainText(?string $raw): ?string
    {
        if (null === $raw) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');

        return '' === $text ? null : $text;
    }

    /** @return list<string> */
    public function renderSection(MarkdownSection $section): array
    {
        $title = $this->sectionTitle($section->title);
        if ('' === trim($title) || [] === $section->lines) {
            return [];
        }

        return [
            '## ' . $title,
            '',
            ...array_map($this->sectionLine(...), $section->lines),
            '',
        ];
    }

    /**
     * @param iterable<MarkdownSection> $sections
     *
     * @return list<string>
     */
    public function renderSections(iterable $sections): array
    {
        $lines = [];
        foreach ($sections as $section) {
            array_push($lines, ...$this->renderSection($section));
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
    public function renderProvidedSections(iterable $providers, \Closure $supports, \Closure $provide): array
    {
        $lines = [];
        foreach ($providers as $provider) {
            if ($supports($provider)) {
                array_push($lines, ...$this->renderSections($provide($provider)));
            }
        }

        return $lines;
    }
}
