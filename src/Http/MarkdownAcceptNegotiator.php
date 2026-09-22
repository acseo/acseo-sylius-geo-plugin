<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

use Symfony\Component\HttpFoundation\AcceptHeader;

final class MarkdownAcceptNegotiator
{
    public const MARKDOWN = 'markdown';

    public const HTML = 'html';

    public const NOT_ACCEPTABLE = 'not_acceptable';

    /** @return self::MARKDOWN|self::HTML|self::NOT_ACCEPTABLE */
    public function negotiate(string $acceptHeader): string
    {
        $accepted = AcceptHeader::fromString($acceptHeader);
        $markdownQuality = ($accepted->all()['text/markdown'] ?? null)?->getQuality() ?? 0.0;
        $htmlQuality = max(
            $this->qualityFor($accepted, 'text/html'),
            $this->qualityFor($accepted, 'application/xhtml+xml'),
        );

        if ($markdownQuality <= 0.0 && $htmlQuality <= 0.0 && $this->acceptsSomething($accepted)) {
            return self::NOT_ACCEPTABLE;
        }

        return $markdownQuality > 0.0 && $markdownQuality >= $htmlQuality ? self::MARKDOWN : self::HTML;
    }

    private function acceptsSomething(AcceptHeader $accepted): bool
    {
        foreach ($accepted->all() as $item) {
            if ($item->getQuality() > 0.0) {
                return true;
            }
        }

        return false;
    }

    private function qualityFor(AcceptHeader $accepted, string $mimeType): float
    {
        $exact = $accepted->get($mimeType);
        if (null !== $exact) {
            return $exact->getQuality();
        }

        [$type] = explode('/', $mimeType, 2);
        $typeWildcard = $accepted->get($type . '/*');
        if (null !== $typeWildcard) {
            return $typeWildcard->getQuality();
        }

        return $accepted->get('*/*')?->getQuality() ?? 0.0;
    }
}
