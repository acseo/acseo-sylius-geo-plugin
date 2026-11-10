<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Markdown;

final class MarkdownSanitizer
{
    private const string UNSAFE_LINK_PATTERN = '/\]\(\s*(?:javascript|data|vbscript):[^\s]*\)/i';

    private const string LINK_PATTERN = '/\]\(([^)\s]+)\)/';

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
        $line = preg_replace(self::UNSAFE_LINK_PATTERN, '](#)', $this->text($value)) ?? '';

        return preg_replace_callback(self::LINK_PATTERN, $this->neutralizeUnsafeScheme(...), $line) ?? '';
    }

    public function plainText(?string $raw): ?string
    {
        if (null === $raw) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');

        return '' === $text ? null : $text;
    }

    /** @param array<int|string, string> $matches */
    private function neutralizeUnsafeScheme(array $matches): string
    {
        $scheme = parse_url($matches[1], \PHP_URL_SCHEME);

        return !\is_string($scheme) || \in_array(mb_strtolower($scheme), ['http', 'https'], true) ? $matches[0] : '](#)';
    }
}
